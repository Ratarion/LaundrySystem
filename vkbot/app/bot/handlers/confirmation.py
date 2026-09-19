import asyncio
import logging
from vkbottle import GroupEventType
from vkbottle.bot import BotLabeler, MessageEvent
from vkbottle.dispatch.rules.base import PayloadContainsRule

from app.laundry_repo import get_booking_by_id, set_booking_status, cancel_booking, get_user_by_vk_id
from app.bot.utils.translate import ALL_TEXTS, get_lang_and_texts
from app.bot.utils.broadcaster import broadcast_slot_freed
from app.bot.loader import bot
from app.bot.keyboards import get_confirmed_keyboard, get_declined_keyboard, get_section_keyboard
from app.bot.utils.fsm import clear_state, update_state_data

confirm_labeler = BotLabeler()


@confirm_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "confirm"}))
async def process_confirm(event: MessageEvent):
    booking_id = int(event.payload["id"])
    lang, t = await get_lang_and_texts(event.peer_id)
    booking = await get_booking_by_id(booking_id)

    if not booking:
        await event.show_snackbar(t.get("booking_already_confirmed", "Запись не найдена"))
        return

    if booking.status == "Подтверждено":
        await event.show_snackbar(t.get("booking_already_confirmed", "Запись уже подтверждена"))
        await event.edit_message(t.get("booking_confirmed", "✅ Запись подтверждена! Ждем вас."), keyboard=get_confirmed_keyboard(booking_id, lang))
        return

    if booking.status == "Отменено":
        cancel_msg = t.get("booking_already_canceled", t.get("cancel_error", "Запись уже отменена"))
        await event.show_snackbar(cancel_msg)
        await event.edit_message(cancel_msg, keyboard=get_declined_keyboard(lang))
        return

    await set_booking_status(booking_id, "Подтверждено")
    await event.edit_message(
        t.get("booking_confirmed", "✅ Запись подтверждена! Ждем вас."),
        keyboard=get_confirmed_keyboard(booking_id, lang)
    )


@confirm_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "decline"}))
async def process_decline(event: MessageEvent):
    booking_id = int(event.payload["id"])
    lang, t = await get_lang_and_texts(event.peer_id)
    booking = await get_booking_by_id(booking_id)

    if not booking:
        await event.show_snackbar(t.get("booking_already_canceled", "Запись не найдена"))
        return

    if booking.status == "Отменено":
        await event.show_snackbar(t.get("booking_already_canceled", "Запись уже отменена"))
        await event.edit_message(t.get("booking_declined", "Запись отменена"), keyboard=get_declined_keyboard(lang))
        return

    dorm_id = getattr(booking, "dormitory_id", None) or (booking.machine.dormitory_id if getattr(booking, "machine", None) else None) or 1
    booking_data = {
        "dormitory_id": dorm_id,
        "machine_id": getattr(booking, "inidmachine", None) or (booking.machine.id if getattr(booking, "machine", None) else None),
        "start_iso": booking.start_time.strftime("%Y%m%d%H%M"),
        "date_str": booking.start_time.strftime("%d.%m"),
        "start_time_str": booking.start_time.strftime("%H:%M"),
        "end_time_str": booking.end_time.strftime("%H:%M"),
        "machine_type": getattr(booking.machine, "type_machine", "") if getattr(booking, "machine", None) else "",
        "machine_num": getattr(booking.machine, "number_machine", "") if getattr(booking, "machine", None) else "",
    }

    success = await cancel_booking(booking_id)
    if success:
        await event.show_snackbar(t.get("booking_declined", "Запись отменена"))
        await event.edit_message(
            t.get("booking_declined", "❌ Вы отменили запись. Слот освобожден для других жильцов."),
            keyboard=get_declined_keyboard(lang)
        )
        asyncio.create_task(
            broadcast_slot_freed(bot, booking_data, exclude_vk_id=event.user_id)
        )
    else:
        await event.show_snackbar(t.get("cancel_error", "Ошибка при отмене"))


@confirm_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "to_main_menu"}))
async def process_to_main_menu(event: MessageEvent):
    peer_id = event.peer_id
    lang, t = await get_lang_and_texts(peer_id)
    user = await get_user_by_vk_id(event.user_id)
    name = user.first_name if user else ""

    await clear_state(peer_id)
    await update_state_data(peer_id, lang=lang)
    await event.edit_message(t["hello_user"].format(name=name), keyboard=get_section_keyboard(lang))
