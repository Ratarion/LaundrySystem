import asyncio
import logging

from app.bot.utils.translate import ALL_TEXTS
from app.bot.utils.text import strip_html
from app.laundry_repo import get_all_users_with_max
from app.bot.keyboards import get_exit_keyboard, get_slot_freed_keyboard


async def broadcast_slot_freed(bot, booking_data: dict, exclude_max_id: int = None):
    dorm_id = booking_data.get("dormitory_id")
    users = await get_all_users_with_max(dormitory_id=dorm_id)
    count = 0

    for u in users:
        max_id = u[0]
        lang = u[1] or "RU"
        if not max_id:
            continue
        if exclude_max_id is not None and max_id == exclude_max_id:
            continue

        t = ALL_TEXTS.get(lang) or ALL_TEXTS.get("RU")

        raw_type = booking_data.get("machine_type", "")
        if raw_type == "Стиральная":
            m_type = t.get("machine_type_wash", "Стиральная")
        elif raw_type == "Сушильная":
            m_type = t.get("machine_type_dry", "Сушильная")
        else:
            m_type = raw_type

        time_range = f"{booking_data.get('start_time_str')} – {booking_data.get('end_time_str')}"

        notification_text = t.get(
            "slot_freed_notification",
            "🔔 Освободилось место!\n\n📅 Дата: {date}\n⏰ Время: {time}\n🧺 {m_type} №{m_num}"
        ).format(
            date=booking_data.get("date_str", ""),
            time=time_range,
            m_type=m_type,
            m_num=booking_data.get("machine_num", "")
        )

        machine_id = booking_data.get("machine_id")
        start_iso = booking_data.get("start_iso")
        if machine_id and start_iso:
            kb = get_slot_freed_keyboard(machine_id, start_iso, lang)
        else:
            kb = get_exit_keyboard(lang)

        try:
            await bot.send_message(
                user_id=max_id,
                text=notification_text,
                keyboard=kb,
            )
            count += 1
            await asyncio.sleep(0.05)
        except Exception as e:
            logging.warning(f"[MaxBot] Не удалось отправить рассылку пользователю {max_id}: {e}")

    logging.info(f"[MaxBot] Рассылка завершена. Отправлено {count} пользователям.")
