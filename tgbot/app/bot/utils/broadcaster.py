# broadcaster.py (полный обновленный код)

import asyncio
import logging
from aiogram import Bot
from aiogram.exceptions import TelegramRetryAfter, TelegramForbiddenError
from app.bot.utils.translate import ALL_TEXTS
from app.repositories.laundry_repo import get_all_users_with_tg
from app.bot.keyboards import get_exit_keyboard, get_slot_freed_keyboard

async def broadcast_slot_freed(bot: Bot, booking_data: dict, exclude_tg_id: int = None):
    dorm_id = booking_data.get("dormitory_id")
    users = await get_all_users_with_tg(dormitory_id=dorm_id)
    count = 0

    for u in users:
        tg_id = getattr(u, "tg_id", None) or (u[0] if isinstance(u, (tuple, list)) else None)
        if not tg_id:
            continue
        if exclude_tg_id is not None and tg_id == exclude_tg_id:
            continue

        # Выбор локали
        raw_lang = getattr(u, "language", None) or (u[1] if isinstance(u, (tuple, list)) and len(u) > 1 else "RU") or "RU"
        lang = str(raw_lang).strip().upper()
        if lang not in ALL_TEXTS:
            lang = "RU"
        t = ALL_TEXTS[lang]

        # 1. Исправление типа машины (база хранит "Стиральная"/"Сушильная")
        raw_type = booking_data.get("machine_type", "")
        if raw_type == "Стиральная":
            m_type = t.get("machine_type_wash", "Стиральная")
        elif raw_type == "Сушильная":
            m_type = t.get("machine_type_dry", "Сушильная")
        else:
            m_type = raw_type

        # 2. Исправление времени (используем ключи из scheduler.py)
        # Формируем интервал: "14:00 – 15:30"
        time_range = f"{booking_data.get('start_time_str')} – {booking_data.get('end_time_str')}"

        # Формируем текст (включаем parse_mode="HTML" для поддержки <b> из словарей)
        notification_text = t.get(
            "slot_freed_notification",
            "🔔 <b>Slot available!</b>\n\n📅 Date: {date}\n⏰ Time: {time}\n🧺 {m_type} #{m_num}"
        ).format(
            date=booking_data.get("date_str", ""),
            time=time_range,  # Передаем сформированную строку
            m_type=m_type,
            m_num=booking_data.get("machine_num", "")
        )

        machine_id = booking_data.get("machine_id")
        start_iso = booking_data.get("start_iso")
        if machine_id and start_iso:
            reply_markup = get_slot_freed_keyboard(machine_id, start_iso, lang)
        else:
            reply_markup = get_exit_keyboard(lang)

        try:
            await bot.send_message(
                chat_id=tg_id, 
                text=notification_text, 
                parse_mode="HTML",
                reply_markup=reply_markup
            )
            count += 1
            await asyncio.sleep(0.05) 
        except TelegramRetryAfter as e:
            await asyncio.sleep(e.retry_after)
        except TelegramForbiddenError:
            logging.warning(f"User {tg_id} blocked the bot.")
        except Exception as e:
            logging.error(f"Error sending to {tg_id}: {e}")

    logging.info(f"Broadcast finished. Sent to {count} users.")