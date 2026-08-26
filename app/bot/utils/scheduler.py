import asyncio
import logging
from datetime import datetime

from sqlalchemy.orm import selectinload
from apscheduler.schedulers.asyncio import AsyncIOScheduler
from vkbottle.bot import Bot

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
from app.bot.utils.text import strip_html
from app.bot.keyboards import get_confirm_keyboard

scheduler = AsyncIOScheduler()


async def _safe_create_task(coro):
    """
    Обёртка для asyncio.create_task с логированием исключений,
    чтобы фоновые задачи не "глотали" ошибки без следа.
    """
    task = asyncio.create_task(coro)

    def _task_done(t):
        try:
            exc = t.exception()
            if exc:
                logging.error(f"Background task exception: {exc}")
        except asyncio.CancelledError:
            logging.info("Background task cancelled")

    task.add_done_callback(_task_done)
    return task


async def check_confirmations(bot: Bot):
    now = datetime.now()
    logging.debug(f"check_confirmations run at {now.isoformat()}")

    # --- ЭТАП 1: Рассылка запросов на подтверждение (за 20 минут) ---
    try:
        bookings_to_remind = await get_bookings_to_remind(minutes_before=20)
    except Exception as e:
        logging.error(f"Failed to fetch bookings_to_remind: {e}")
        bookings_to_remind = []

    async with async_session() as sess:
        for b in bookings_to_remind:
            db_b = await sess.get(
                Booking,
                getattr(b, "id", None),
                options=[selectinload(Booking.user), selectinload(Booking.machine)]
            )
            if not db_b:
                logging.warning(f"Booking {getattr(b, 'id', None)} not found in DB when preparing reminder")
                continue

            user = getattr(db_b, "user", None)
            if not user or not getattr(user, "vk_id", None):
                continue

            lang = getattr(user, "language", None)
            t = ALL_TEXTS.get(lang) if lang else None
            if not t:
                t = ALL_TEXTS.get("RU") or ALL_TEXTS.get("ENG") or list(ALL_TEXTS.values())[0]

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
                machine_type = raw_type or "Неизвестная"

            machine_num = getattr(db_b.machine, "number_machine", "?") if getattr(db_b, "machine", None) else "?"

            confirm_text = t.get(
                "confirm_booking_prompt",
                "⏳ Booking confirmation\n\n"
                "You have scheduled {machine_type} machine №{machine_num} on {date} "
                "(time: {time_range}).\n"
                "Please confirm, otherwise it will be canceled in 10 minutes."
            ).format(
                machine_type=machine_type,
                machine_num=machine_num,
                date=date_str,
                time_range=time_range
            )
            confirm_text = strip_html(confirm_text)

            try:
                await bot.api.messages.send(
                    peer_id=user.vk_id,
                    message=confirm_text,
                    random_id=0,
                    keyboard=get_confirm_keyboard(db_b.id, lang or "RU"),
                )
                await set_booking_status(db_b.id, "Ожидание")
                logging.info(f"Sent confirmation request for booking {db_b.id} to {user.vk_id}")
                await asyncio.sleep(0.05)
            except Exception as e:
                logging.error(f"Failed to send confirm request to {getattr(user, 'vk_id', None)}: {e}")
                continue

    # --- ЭТАП 2: Авто-отмена (за 10 минут) ---
    try:
        expired = await get_expired_unconfirmed_bookings(minutes_before_deadline=10)
    except Exception as e:
        logging.error(f"Failed to fetch expired unconfirmed bookings: {e}")
        expired = []

    async with async_session() as sess:
        for b in expired:
            db_b = await sess.get(
                Booking,
                getattr(b, "id", None),
                options=[selectinload(Booking.user), selectinload(Booking.machine)]
            )
            if not db_b:
                logging.warning(f"Booking {getattr(b, 'id', None)} not found in DB when autocancel")
                continue

            try:
                await cancel_booking(db_b.id)
                logging.info(f"Autocanceled booking {db_b.id} due to no confirmation")
            except Exception as e:
                logging.error(f"Failed to cancel booking {db_b.id}: {e}")
                continue

            user = getattr(db_b, "user", None)
            booking_data = {
                "date_str": db_b.start_time.strftime("%d.%m"),
                "start_time_str": db_b.start_time.strftime("%H:%M"),
                "end_time_str": db_b.end_time.strftime("%H:%M"),
                "machine_type": getattr(db_b.machine, "type_machine", "") if getattr(db_b, "machine", None) else "",
                "machine_num": getattr(db_b.machine, "number_machine", "") if getattr(db_b, "machine", None) else ""
            }

            if user and getattr(user, "vk_id", None):
                lang = getattr(user, "language", None) or "RU"
                t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])

                raw_type = booking_data["machine_type"]
                if raw_type == "Стиральная":
                    m_type = t.get("machine_type_wash", "Стиральная")
                elif raw_type == "Сушильная":
                    m_type = t.get("machine_type_dry", "Сушильная")
                else:
                    m_type = raw_type

                time_range = f"{booking_data['start_time_str']} – {booking_data['end_time_str']}"
                autocancel_text = strip_html(t.get(
                    "booking_autocanceled",
                    "❌ Your booking on {date} ({time_range}) {machine_type} №{machine_num} "
                    "was cancelled automatically because you did not confirm it in time."
                ).format(
                    date=booking_data["date_str"],
                    time_range=time_range,
                    machine_type=m_type,
                    machine_num=booking_data["machine_num"],
                ))
                try:
                    await bot.api.messages.send(peer_id=user.vk_id, message=autocancel_text, random_id=0)
                except Exception as e:
                    logging.error(f"Failed to notify {user.vk_id} about autocancel: {e}")

            await _safe_create_task(
                broadcast_slot_freed(bot, booking_data, exclude_vk_id=getattr(user, "vk_id", None))
            )


def start_scheduler(bot: Bot):
    scheduler.add_job(
        check_confirmations,
        'interval',
        minutes=1,
        kwargs={"bot": bot},
        max_instances=1,
        coalesce=True
    )
    scheduler.start()
    logging.info("Scheduler started")
