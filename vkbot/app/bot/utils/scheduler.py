import asyncio
import logging
from datetime import datetime

from sqlalchemy.orm import selectinload
from apscheduler.schedulers.asyncio import AsyncIOScheduler
from vkbottle.bot import Bot

from app.db.models.booking import Booking
from app.db.base import async_session
from app.laundry_repo import (
    get_bookings_to_remind_vk,
    mark_booking_reminded_vk,
    get_expired_unconfirmed_bookings_to_cancel,
    autocancel_booking,
    get_autocanceled_to_notify_vk,
    mark_autocanceled_notified_vk,
    get_autocancel_penalty_info,
)
from app.bot.utils.translate import ALL_TEXTS
from app.bot.utils.broadcaster import broadcast_slot_freed
from app.bot.utils.text import strip_html
from app.bot.keyboards import get_confirm_keyboard
from app.bot.utils.timezone import get_kemerovo_now

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


def _format_autocancel_text_vk(user, booking_data: dict, disc_res: dict = None) -> str:
    lang = str(getattr(user, "language", "RU") or "RU").strip().upper()
    if lang not in ALL_TEXTS:
        lang = "RU"
    t = ALL_TEXTS[lang]

    raw_type = booking_data.get("machine_type", "")
    if raw_type == "Стиральная":
        m_type = t.get("machine_type_wash", "Стиральная")
    elif raw_type == "Сушильная":
        m_type = t.get("machine_type_dry", "Сушильная")
    else:
        m_type = raw_type

    time_range = f"{booking_data.get('start_time_str', '')} – {booking_data.get('end_time_str', '')}"
    autocancel_text = strip_html(t.get(
        "booking_autocanceled",
        "❌ Your booking on {date} ({time_range}) {machine_type} №{machine_num} "
        "was cancelled automatically because you did not confirm it in time."
    ).format(
        date=booking_data.get("date_str", ""),
        time_range=time_range,
        machine_type=m_type,
        machine_num=booking_data.get("machine_num", ""),
    ))

    if disc_res:
        penalty_text = strip_html(t.get("discipline_autocancel_penalty", "").format(
            delta=disc_res.get("delta", -15),
            score=disc_res.get("new_score", 100)
        ))
        if penalty_text:
            autocancel_text = f"{autocancel_text}\n\n{penalty_text}"
        if disc_res.get("is_banned"):
            banned_alert = strip_html(t.get("discipline_banned_alert", ""))
            if banned_alert:
                autocancel_text = f"{autocancel_text}\n\n{banned_alert}"

    return autocancel_text


async def _send_autocancel_notice_vk(bot: Bot, user, booking_data: dict, disc_res: dict = None):
    if not user or not getattr(user, "vk_id", None):
        return
    text = _format_autocancel_text_vk(user, booking_data, disc_res)
    try:
        await bot.api.messages.send(peer_id=user.vk_id, message=text, random_id=0)
    except Exception as e:
        logging.error(f"Failed to notify VK {user.vk_id} about autocancel: {e}")


async def check_confirmations(bot: Bot):
    now = get_kemerovo_now()
    logging.debug(f"check_confirmations run at {now.isoformat()}")

    # --- ЭТАП 1: Рассылка запросов на подтверждение в VK (за 1 час / 60 минут) ---
    try:
        bookings_to_remind = await get_bookings_to_remind_vk(minutes_before=60, minutes_deadline=30)
    except Exception as e:
        logging.error(f"Failed to fetch bookings_to_remind_vk: {e}")
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
            if not user or not getattr(user, "vk_id", None):
                # Если у жителя нет VK, отмечаем reminded_vk = True чтобы не опрашивать повторно
                await mark_booking_reminded_vk(db_b.id)
                continue

            lang = str(getattr(user, "language", "RU") or "RU").strip().upper()
            if lang not in ALL_TEXTS:
                lang = "RU"
            t = ALL_TEXTS[lang]

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
                await mark_booking_reminded_vk(db_b.id)
                logging.info(f"Sent confirmation request for booking {db_b.id} to VK {user.vk_id}")
                await asyncio.sleep(0.05)
            except Exception as e:
                logging.error(f"Failed to send confirm request to VK {getattr(user, 'vk_id', None)}: {e}")
                await mark_booking_reminded_vk(db_b.id)
                continue

    # --- ЭТАП 2.1: Первичная авто-отмена просроченных записей (за 30 минут) ---
    try:
        to_cancel = await get_expired_unconfirmed_bookings_to_cancel(minutes_before_deadline=30)
    except Exception as e:
        logging.error(f"Failed to fetch expired unconfirmed bookings to cancel: {e}")
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
            has_vk = bool(user and getattr(user, "vk_id", None))

            try:
                await autocancel_booking(db_b.id, platform="vk" if has_vk else "none")
                logging.info(f"Autocanceled booking {db_b.id} due to no confirmation")
            except Exception as e:
                logging.error(f"Failed to cancel booking {db_b.id}: {e}")
                continue

            disc_res = None
            if user:
                try:
                    from app.services.discipline_service import apply_discipline_event, EVENT_AUTOCANCEL_MISSED
                    disc_res = await apply_discipline_event(user.id, db_b.id, EVENT_AUTOCANCEL_MISSED)
                except Exception as e:
                    logging.error(f"Failed to apply penalty for autocancel on booking {db_b.id}: {e}")

            dorm_id = getattr(db_b, "dormitory_id", None) or (db_b.machine.dormitory_id if getattr(db_b, "machine", None) else None) or getattr(user, "dormitory_id", 1) or 1
            booking_data = {
                "dormitory_id": dorm_id,
                "machine_id": getattr(db_b, "inidmachine", None) or (db_b.machine.id if getattr(db_b, "machine", None) else None),
                "start_iso": db_b.start_time.strftime("%Y%m%d%H%M"),
                "date_str": db_b.start_time.strftime("%d.%m"),
                "start_time_str": db_b.start_time.strftime("%H:%M"),
                "end_time_str": db_b.end_time.strftime("%H:%M"),
                "machine_type": getattr(db_b.machine, "type_machine", "") if getattr(db_b, "machine", None) else "",
                "machine_num": getattr(db_b.machine, "number_machine", "") if getattr(db_b, "machine", None) else ""
            }

            if has_vk:
                await _send_autocancel_notice_vk(bot, user, booking_data, disc_res)

            await _safe_create_task(
                broadcast_slot_freed(bot, booking_data, exclude_vk_id=getattr(user, "vk_id", None))
            )

    # --- ЭТАП 2.2: Досылка уведомлений в VK, если запись была отменена другим ботом (TG / MAX) ---
    try:
        pending_notices = await get_autocanceled_to_notify_vk()
    except Exception as e:
        logging.error(f"Failed to fetch autocanceled_to_notify_vk: {e}")
        pending_notices = []

    async with async_session() as sess:
        for b in pending_notices:
            db_b = await sess.get(
                Booking,
                getattr(b, "id", None),
                options=[selectinload(Booking.user), selectinload(Booking.machine)]
            )
            if not db_b:
                await mark_autocanceled_notified_vk(b.id)
                continue

            user = getattr(db_b, "user", None)
            if not user or not getattr(user, "vk_id", None):
                await mark_autocanceled_notified_vk(db_b.id)
                continue

            dorm_id = getattr(db_b, "dormitory_id", None) or (db_b.machine.dormitory_id if getattr(db_b, "machine", None) else None) or getattr(user, "dormitory_id", 1) or 1
            booking_data = {
                "dormitory_id": dorm_id,
                "machine_id": getattr(db_b, "inidmachine", None) or (db_b.machine.id if getattr(db_b, "machine", None) else None),
                "start_iso": db_b.start_time.strftime("%Y%m%d%H%M"),
                "date_str": db_b.start_time.strftime("%d.%m"),
                "start_time_str": db_b.start_time.strftime("%H:%M"),
                "end_time_str": db_b.end_time.strftime("%H:%M"),
                "machine_type": getattr(db_b.machine, "type_machine", "") if getattr(db_b, "machine", None) else "",
                "machine_num": getattr(db_b.machine, "number_machine", "") if getattr(db_b, "machine", None) else ""
            }

            disc_res = await get_autocancel_penalty_info(db_b.id)
            await _send_autocancel_notice_vk(bot, user, booking_data, disc_res)
            await mark_autocanceled_notified_vk(db_b.id)
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
