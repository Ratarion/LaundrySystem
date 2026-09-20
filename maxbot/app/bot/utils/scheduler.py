import asyncio
import logging
from datetime import datetime

from sqlalchemy.orm import selectinload
from apscheduler.schedulers.asyncio import AsyncIOScheduler

from app.db.models.booking import Booking
from app.db.base import async_session
from app.laundry_repo import (
    get_bookings_to_remind,
    set_booking_status,
    get_expired_unconfirmed_bookings,
    cancel_booking,
)
from app.bot.utils.translate import ALL_TEXTS
from app.bot.utils.broadcaster import broadcast_slot_freed
from app.bot.keyboards import get_confirm_keyboard
from app.bot.utils.timezone import get_kemerovo_now

scheduler = AsyncIOScheduler()


async def check_confirmations(bot):
    now = get_kemerovo_now()
    logging.debug(f"[MaxBot] check_confirmations at {now.isoformat()}")

    # --- ЭТАП 1: Рассылка запросов на подтверждение (за 1 час / 60 минут) ---
    try:
        bookings_to_remind = await get_bookings_to_remind(minutes_before=60, minutes_deadline=30)
    except Exception as e:
        logging.error(f"[MaxBot] Ошибка при выборке bookings_to_remind: {e}")
        bookings_to_remind = []

    async with async_session() as sess:
        for b in bookings_to_remind:
            db_b = await sess.get(
                Booking,
                getattr(b, "id", None),
                options=[selectinload(Booking.user), selectinload(Booking.machine)]
            )
            if not db_b:
                continue

            user = getattr(db_b, "user", None)
            if not user or not getattr(user, "max_id", None):
                continue

            lang = getattr(user, "language", "RU") or "RU"
            t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])

            try:
                date_str = db_b.start_time.strftime("%d.%m")
                start_time_str = db_b.start_time.strftime("%H:%M")
                end_time_str = db_b.end_time.strftime("%H:%M")
            except Exception:
                date_str = ""
                start_time_str = ""
                end_time_str = ""

            time_range = f"{start_time_str} - {end_time_str}"
            raw_type = getattr(db_b.machine, "type_machine", "") if getattr(db_b, "machine", None) else ""
            if raw_type == "Стиральная":
                machine_type = t.get("machine_type_wash", "Стиральная")
            elif raw_type == "Сушильная":
                machine_type = t.get("machine_type_dry", "Сушильная")
            else:
                machine_type = raw_type or "Машина"

            machine_num = getattr(db_b.machine, "number_machine", "?") if getattr(db_b, "machine", None) else "?"

            confirm_text = t.get(
                "confirm_booking_prompt",
                "⏳ Подтверждение записи\n\n"
                "Вы записаны на {machine_type} №{machine_num} на {date} (время: {time_range}).\n"
                "Подтвердите, что придёте:"
            ).format(
                machine_type=machine_type,
                machine_num=machine_num,
                date=date_str,
                time_range=time_range
            )

            try:
                await bot.send_message(
                    user_id=user.max_id,
                    text=confirm_text,
                    keyboard=get_confirm_keyboard(db_b.id, lang),
                )
                await set_booking_status(db_b.id, "Ожидание подтверждения")
                logging.info(f"[MaxBot] Отправлено напоминание о брони {db_b.id} пользователю {user.max_id}")
            except Exception as e:
                logging.error(f"[MaxBot] Ошибка отправки напоминания пользователю {user.max_id}: {e}")

    # --- ЭТАП 2: Автоотмена просроченных записей (за 30 минут) ---
    try:
        expired_bookings = await get_expired_unconfirmed_bookings(minutes_before_deadline=30)
    except Exception as e:
        logging.error(f"[MaxBot] Ошибка при выборке expired_bookings: {e}")
        expired_bookings = []

    for b in expired_bookings:
        user = getattr(b, "user", None)
        max_id = getattr(user, "max_id", None) if user else None
        lang = getattr(user, "language", "RU") if user else "RU"
        t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])

        dorm_id = getattr(b, "dormitory_id", None) or (b.machine.dormitory_id if getattr(b, "machine", None) else None) or (getattr(user, "dormitory_id", 1) if user else 1) or 1
        booking_data = {
            "dormitory_id": dorm_id,
            "machine_id": getattr(b, "inidmachine", None) or (b.machine.id if getattr(b, "machine", None) else None),
            "start_iso": b.start_time.strftime("%Y%m%d%H%M") if b.start_time else "",
            "date_str": b.start_time.strftime("%d.%m") if b.start_time else "",
            "start_time_str": b.start_time.strftime("%H:%M") if b.start_time else "",
            "end_time_str": b.end_time.strftime("%H:%M") if b.end_time else "",
            "machine_type": b.machine.type_machine if getattr(b, "machine", None) else "",
            "machine_num": b.machine.number_machine if getattr(b, "machine", None) else "",
        }

        success = await cancel_booking(b.id)
        if success:
            logging.info(f"[MaxBot] Автоматически отменена неподтверждённая бронь #{b.id}")

            disc_res = None
            if user:
                try:
                    from app.services.discipline_service import apply_discipline_event, EVENT_AUTOCANCEL_MISSED
                    disc_res = await apply_discipline_event(user.id, b.id, EVENT_AUTOCANCEL_MISSED)
                except Exception as e:
                    logging.error(f"[MaxBot] Ошибка применения штрафа при автоотмене: {e}")

            if max_id:
                try:
                    cancel_notice = t.get(
                        "booking_autocanceled",
                        "❌ Ваша запись на {date} ({time}) была автоматически отменена, так как вы не подтвердили её вовремя."
                    ).format(
                        date=booking_data["date_str"],
                        time=f"{booking_data['start_time_str']} - {booking_data['end_time_str']}"
                    )
                    if disc_res:
                        penalty_text = t.get("discipline_autocancel_penalty", "").format(
                            delta=disc_res["delta"],
                            score=disc_res["new_score"]
                        )
                        if penalty_text:
                            cancel_notice = f"{cancel_notice}\n\n{penalty_text}"
                        if disc_res.get("is_banned"):
                            banned_alert = t.get("discipline_banned_alert", "")
                            if banned_alert:
                                cancel_notice = f"{cancel_notice}\n\n{banned_alert}"

                    await bot.send_message(user_id=max_id, text=cancel_notice)
                except Exception as e:
                    logging.warning(f"[MaxBot] Не удалось уведомить пользователя {max_id} об автоотмене: {e}")

            asyncio.create_task(
                broadcast_slot_freed(bot, booking_data, exclude_max_id=max_id)
            )


def start_scheduler(bot):
    if not scheduler.running:
        scheduler.add_job(check_confirmations, "interval", minutes=1, args=[bot])
        scheduler.start()
        logging.info("[MaxBot] Фоновый планировщик успешно запущен (интервал 1 минута)")
