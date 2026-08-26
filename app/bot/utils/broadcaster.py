import asyncio
import logging

from vkbottle import VKAPIError
from vkbottle.bot import Bot

from app.bot.utils.translate import ALL_TEXTS
from app.bot.utils.text import strip_html
from app.laundry_repo import get_all_users_with_vk
from app.bot.keyboards import get_exit_keyboard


async def broadcast_slot_freed(bot: Bot, booking_data: dict, exclude_vk_id: int = None):
    users = await get_all_users_with_vk()
    count = 0

    for u in users:
        vk_id = getattr(u, "vk_id", None)
        if not vk_id:
            continue
        if exclude_vk_id is not None and vk_id == exclude_vk_id:
            continue

        lang = getattr(u, "language", "RU") or "RU"
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
            "🔔 Slot available!\n\n📅 Date: {date}\n⏰ Time: {time}\n🧺 {m_type} #{m_num}"
        ).format(
            date=booking_data.get("date_str", ""),
            time=time_range,
            m_type=m_type,
            m_num=booking_data.get("machine_num", "")
        )
        notification_text = strip_html(notification_text)

        try:
            await bot.api.messages.send(
                peer_id=vk_id,
                message=notification_text,
                random_id=0,
                keyboard=get_exit_keyboard(lang),
            )
            count += 1
            await asyncio.sleep(0.05)
        except VKAPIError as e:
            # Код 901/902/15 — сообщения от сообщества заблокированы пользователем и т.п.
            logging.warning(f"Не удалось отправить уведомление {vk_id}: {e}")
        except Exception as e:
            logging.error(f"Error sending to {vk_id}: {e}")

    logging.info(f"Broadcast finished. Sent to {count} users.")
