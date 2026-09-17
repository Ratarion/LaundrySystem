import asyncio
from sqlalchemy import select
from sqlalchemy.orm import selectinload
from app.db.models.booking import Booking

import logging
from datetime import datetime
from apscheduler.schedulers.asyncio import AsyncIOScheduler
from aiogram import Bot
from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton

from app.repositories.laundry_repo import (
    get_bookings_to_remind,
    set_booking_status,
    get_expired_unconfirmed_bookings,
    cancel_booking
)
from app.bot.utils.translate import ALL_TEXTS
from app.bot.utils.broadcaster import broadcast_slot_freed
from app.bot.keyboards import get_exit_keyboard

from app.db.base import async_session

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

    # --- ЭТАП 1: Рассылка запросов на подтверждение (за 40 минут) ---
    try:
        bookings_to_remind = await get_bookings_to_remind(minutes_before=20)
    except Exception as e:
        logging.error(f"Failed to fetch bookings_to_remind: {e}")
        bookings_to_remind = []

    # ЗАГРУЖАЕМ каждый booking в своей сессии (чтобы он был persistent)
    async with async_session() as sess:
        for b in bookings_to_remind:
            # Вместо refresh — получаем объект по id с eager-load связей
            db_b = await sess.get(
                Booking,
                getattr(b, "id", None),
                options=[selectinload(Booking.user), selectinload(Booking.machine)]
            )
            if not db_b:
                logging.warning(f"Booking {getattr(b, 'id', None)} not found in DB when preparing reminder")
                continue

            user = getattr(db_b, "user", None)
            if not user or not getattr(user, "tg_id", None):
                continue

            lang = getattr(user, "language", None)
            t = ALL_TEXTS.get(lang) if lang else None
            if not t:
                t = ALL_TEXTS.get("RU") or ALL_TEXTS.get("ENG") or list(ALL_TEXTS.values())[0]

            kb = InlineKeyboardMarkup(inline_keyboard=[
                [InlineKeyboardButton(text=t.get("confirm_btn", "Confirm"), callback_data=f"confirm_{db_b.id}")]
            ])

            # Формируем текст как раньше, но используем db_b
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
                "⏳ <b>Booking confirmation</b>\n\n"
                "You have scheduled {machine_type} machine №{machine_num} on <b>{date}</b> "
                "(time: {time_range}).\n"
                "Please confirm, otherwise it will be canceled in 10 minutes."
            ).format(
                machine_type=machine_type,
                machine_num=machine_num,
                date=date_str,
                time_range=time_range
            )

            try:
                await bot.send_message(user.tg_id, confirm_text, reply_markup=kb, parse_mode="HTML")
                await set_booking_status(db_b.id, "Ожидание подтверждения")
                logging.info(f"Sent confirmation request for booking {db_b.id} to {user.tg_id}")
                await asyncio.sleep(0.05)
            except Exception as e:
                logging.error(f"Failed to send confirm request to {getattr(user, 'tg_id', None)}: {e}")
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
            dorm_id = getattr(db_b, "dormitory_id", None) or (db_b.machine.dormitory_id if getattr(db_b, "machine", None) else None) or getattr(user, "dormitory_id", 1) or 1
            booking_data = {
                "dormitory_id": dorm_id,
                "date_str": db_b.start_time.strftime("%d.%m"),
                "start_time_str": db_b.start_time.strftime("%H:%M"),
                "end_time_str": db_b.end_time.strftime("%H:%M"),
                "machine_type": getattr(db_b.machine, "type_machine", "") if getattr(db_b, "machine", None) else "",
                "machine_num": getattr(db_b.machine, "number_machine", "") if getattr(db_b, "machine", None) else ""
            }

            if user and getattr(user, "tg_id", None):
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
                autocancel_text = t.get(
                    "booking_autocanceled",
                    "❌ Ваша запись на {date} ({time_range}) {machine_type} №{machine_num} "
                    "была отменена автоматически, так как вы не подтвердили её вовремя."
                ).format(
                    date=booking_data["date_str"],
                    time_range=time_range,
                    machine_type=m_type,
                    machine_num=booking_data["machine_num"],
                )
                try:
                    await bot.send_message(user.tg_id, autocancel_text, parse_mode="HTML")
                except Exception as e:
                    logging.error(f"Failed to notify {user.tg_id} about autocancel: {e}")

            await _safe_create_task(
                broadcast_slot_freed(bot, booking_data, exclude_tg_id=getattr(user, "tg_id", None))
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