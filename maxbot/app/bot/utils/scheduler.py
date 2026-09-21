import asyncio
import logging
from datetime import datetime

from sqlalchemy.orm import selectinload
from apscheduler.schedulers.asyncio import AsyncIOScheduler

from app.db.models.booking import Booking
from app.db.base import async_session
from app.laundry_repo import (
    get_bookings_to_remind_max,
    mark_booking_reminded_max,
    get_expired_unconfirmed_bookings_to_cancel,
    autocancel_booking,
    get_autocanceled_to_notify_max,
    mark_autocanceled_notified_max,
    get_autocancel_penalty_info,
    get_bookings_to_notify_finish_max,
    mark_booking_finish_notified_max,
)
from app.bot.utils.translate import ALL_TEXTS
from app.bot.utils.broadcaster import broadcast_slot_freed
from app.bot.utils.text import strip_html
from app.bot.keyboards import get_confirm_keyboard
from app.bot.utils.timezone import get_kemerovo_now

scheduler = AsyncIOScheduler()


def _format_autocancel_text_max(user, booking_data: dict, disc_res: dict = None) -> str:
    lang = getattr(user, "language", "RU") if user else "RU"
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])

    cancel_notice = t.get(
        "booking_autocanceled",
        "❌ Ваша запись на {date} ({time}) была автоматически отменена, так как вы не подтвердили её вовремя."
    ).format(
        date=booking_data.get("date_str", ""),
        time=f"{booking_data.get('start_time_str', '')} - {booking_data.get('end_time_str', '')}"
    )
    if disc_res:
        penalty_text = t.get("discipline_autocancel_penalty", "").format(
            delta=disc_res.get("delta", -15),
            score=disc_res.get("new_score", 100)
        )
        if penalty_text:
            cancel_notice = f"{cancel_notice}\n\n{penalty_text}"
        if disc_res.get("is_banned"):
            banned_alert = t.get("discipline_banned_alert", "")
            if banned_alert:
                cancel_notice = f"{cancel_notice}\n\n{banned_alert}"

    return cancel_notice


async def _send_autocancel_notice_max(bot, user, booking_data: dict, disc_res: dict = None):
    max_id = getattr(user, "max_id", None) if user else None
    if not max_id:
        return
    text = _format_autocancel_text_max(user, booking_data, disc_res)
    try:
        await bot.send_message(user_id=max_id, text=text)
    except Exception as e:
        logging.warning(f"[MaxBot] Не удалось уведомить пользователя {max_id} об автоотмене: {e}")


async def check_confirmations(bot):
    now = get_kemerovo_now()
    logging.debug(f"[MaxBot] check_confirmations at {now.isoformat()}")

    # --- ЭТАП 1: Рассылка запросов на подтверждение в MAX (за 1 час / 60 минут) ---
    try:
        bookings_to_remind = await get_bookings_to_remind_max(minutes_before=60, minutes_deadline=30)
    except Exception as e:
        logging.error(f"[MaxBot] Ошибка при выборке bookings_to_remind_max: {e}")
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
                # Если у жителя нет MAX, отмечаем reminded_max = True чтобы не опрашивать повторно
                await mark_booking_reminded_max(db_b.id)
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
                await mark_booking_reminded_max(db_b.id)
                logging.info(f"[MaxBot] Отправлено напоминание о брони {db_b.id} пользователю {user.max_id}")
            except Exception as e:
                logging.error(f"[MaxBot] Ошибка отправки напоминания пользователю {user.max_id}: {e}")
                await mark_booking_reminded_max(db_b.id)

    # --- ЭТАП 2.1: Первичная автоотмена просроченных записей (за 30 минут) ---
    try:
        to_cancel = await get_expired_unconfirmed_bookings_to_cancel(minutes_before_deadline=30)
    except Exception as e:
        logging.error(f"[MaxBot] Ошибка при выборке expired bookings: {e}")
        to_cancel = []

    async with async_session() as sess:
        for b in to_cancel:
            db_b = await sess.get(
                Booking,
                getattr(b, "id", None),
                options=[selectinload(Booking.user), selectinload(Booking.machine)]
            )
            if not db_b:
                continue

            user = getattr(db_b, "user", None)
            has_max = bool(user and getattr(user, "max_id", None))

            try:
                await autocancel_booking(db_b.id, platform="max" if has_max else "none")
                logging.info(f"[MaxBot] Автоматически отменена неподтверждённая бронь #{db_b.id}")
            except Exception as e:
                logging.error(f"[MaxBot] Ошибка отмены брони {db_b.id}: {e}")
                continue

            disc_res = None
            if user:
                try:
                    from app.services.discipline_service import apply_discipline_event, EVENT_AUTOCANCEL_MISSED
                    disc_res = await apply_discipline_event(user.id, db_b.id, EVENT_AUTOCANCEL_MISSED)
                except Exception as e:
                    logging.error(f"[MaxBot] Ошибка применения штрафа при автоотмене: {e}")

            dorm_id = getattr(db_b, "dormitory_id", None) or (db_b.machine.dormitory_id if getattr(db_b, "machine", None) else None) or (getattr(user, "dormitory_id", 1) if user else 1) or 1
            booking_data = {
                "dormitory_id": dorm_id,
                "machine_id": getattr(db_b, "inidmachine", None) or (db_b.machine.id if getattr(db_b, "machine", None) else None),
                "start_iso": db_b.start_time.strftime("%Y%m%d%H%M") if db_b.start_time else "",
                "date_str": db_b.start_time.strftime("%d.%m") if db_b.start_time else "",
                "start_time_str": db_b.start_time.strftime("%H:%M") if db_b.start_time else "",
                "end_time_str": db_b.end_time.strftime("%H:%M") if db_b.end_time else "",
                "machine_type": db_b.machine.type_machine if getattr(db_b, "machine", None) else "",
                "machine_num": db_b.machine.number_machine if getattr(db_b, "machine", None) else "",
            }

            if has_max:
                await _send_autocancel_notice_max(bot, user, booking_data, disc_res)

            asyncio.create_task(
                broadcast_slot_freed(bot, booking_data, exclude_max_id=getattr(user, "max_id", None) if user else None)
            )

    # --- ЭТАП 2.2: Досылка уведомлений в MAX, если бронь отменена другим ботом (TG / VK) ---
    try:
        pending_notices = await get_autocanceled_to_notify_max()
    except Exception as e:
        logging.error(f"[MaxBot] Ошибка при выборке autocanceled_to_notify_max: {e}")
        pending_notices = []

    async with async_session() as sess:
        for b in pending_notices:
            db_b = await sess.get(
                Booking,
                getattr(b, "id", None),
                options=[selectinload(Booking.user), selectinload(Booking.machine)]
            )
            if not db_b:
                await mark_autocanceled_notified_max(b.id)
                continue

            user = getattr(db_b, "user", None)
            if not user or not getattr(user, "max_id", None):
                await mark_autocanceled_notified_max(db_b.id)
                continue

            dorm_id = getattr(db_b, "dormitory_id", None) or (db_b.machine.dormitory_id if getattr(db_b, "machine", None) else None) or (getattr(user, "dormitory_id", 1) if user else 1) or 1
            booking_data = {
                "dormitory_id": dorm_id,
                "machine_id": getattr(db_b, "inidmachine", None) or (db_b.machine.id if getattr(db_b, "machine", None) else None),
                "start_iso": db_b.start_time.strftime("%Y%m%d%H%M") if db_b.start_time else "",
                "date_str": db_b.start_time.strftime("%d.%m") if db_b.start_time else "",
                "start_time_str": db_b.start_time.strftime("%H:%M") if db_b.start_time else "",
                "end_time_str": db_b.end_time.strftime("%H:%M") if db_b.end_time else "",
                "machine_type": db_b.machine.type_machine if getattr(db_b, "machine", None) else "",
                "machine_num": db_b.machine.number_machine if getattr(db_b, "machine", None) else "",
            }

            disc_res = await get_autocancel_penalty_info(db_b.id)
            await _send_autocancel_notice_max(bot, user, booking_data, disc_res)
            await mark_autocanceled_notified_max(db_b.id)

            asyncio.create_task(
                broadcast_slot_freed(bot, booking_data, exclude_max_id=user.max_id)
            )

    # --- ЭТАП 3: Напоминание об окончании стирки (за 15 минут до end_time) ---
    try:
        finish_to_notify = await get_bookings_to_notify_finish_max()
    except Exception as e:
        logging.error(f"[MaxBot] Ошибка при выборке finish bookings: {e}")
        finish_to_notify = []

    async with async_session() as sess:
        for b in finish_to_notify:
            db_b = await sess.get(
                Booking,
                getattr(b, "id", None),
                options=[selectinload(Booking.user), selectinload(Booking.machine)]
            )
            if not db_b:
                continue

            user = getattr(db_b, "user", None)
            if not user or not getattr(user, "max_id", None):
                await mark_booking_finish_notified_max(db_b.id)
                continue

            lang = str(getattr(user, "language", "RU") or "RU").strip().upper()
            if lang not in ALL_TEXTS:
                lang = "RU"
            t = ALL_TEXTS[lang]

            end_time_str = db_b.end_time.strftime("%H:%M") if db_b.end_time else ""
            raw_type = getattr(db_b.machine, "type_machine", "") if getattr(db_b, "machine", None) else ""
            if raw_type == "Стиральная":
                machine_type = t.get("machine_type_wash", "Стиральная")
            elif raw_type == "Сушильная":
                machine_type = t.get("machine_type_dry", "Сушильная")
            else:
                machine_type = raw_type or "Машина"

            machine_num = getattr(db_b.machine, "number_machine", "?") if getattr(db_b, "machine", None) else "?"

            finish_text = t.get(
                "wash_finishing_soon",
                "⏳ <b>Ваша стирка скоро завершится!</b>\n\n🧺 {machine_type} №{machine_num} завершает работу в <b>{end_time}</b>.\nПожалуйста, не забудьте вовремя забрать вещи!"
            ).format(
                machine_type=machine_type,
                machine_num=machine_num,
                end_time=end_time_str
            )
            finish_text = strip_html(finish_text)

            try:
                await bot.send_message(
                    user_id=user.max_id,
                    text=finish_text,
                )
                await mark_booking_finish_notified_max(db_b.id)
                logging.info(f"[MaxBot] Отправлено уведомление об окончании стирки {db_b.id} пользователю {user.max_id}")
                await asyncio.sleep(0.05)
            except Exception as e:
                logging.error(f"[MaxBot] Ошибка отправки уведомления об окончании пользователю {getattr(user, 'max_id', None)}: {e}")
                await mark_booking_finish_notified_max(db_b.id)
                continue



def start_scheduler(bot):
    if not scheduler.running:
        scheduler.add_job(check_confirmations, "interval", minutes=1, args=[bot])
        scheduler.start()
        logging.info("[MaxBot] Фоновый планировщик успешно запущен (интервал 1 минута)")
