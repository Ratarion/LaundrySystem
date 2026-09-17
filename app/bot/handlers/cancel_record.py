import asyncio
import logging

from vkbottle import GroupEventType
from vkbottle.bot import BotLabeler, MessageEvent
from vkbottle.dispatch.rules.base import PayloadContainsRule

from app.bot.utils.translate import get_lang_and_texts
from app.bot.keyboards import get_cancel_booking_keyboard, get_back_to_sections_keyboard
from app.bot.states import CancelRecord
from app.laundry_repo import get_user_by_vk_id, get_user_bookings, cancel_booking, get_booking_by_id
from app.bot.utils.broadcaster import broadcast_slot_freed
from app.bot.utils.fsm import set_state, clear_state, state_in
from app.bot.loader import bot

cancel_record_labeler = BotLabeler()


@cancel_record_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "remove_records"}))
async def start_cancel_process(event: MessageEvent):
    peer_id = event.peer_id
    lang, t = await get_lang_and_texts(peer_id)
    user = await get_user_by_vk_id(event.user_id)

    if not user:
        await event.show_snackbar(t["none_user"])
        return

    bookings = await get_user_bookings(user.id)
    if not bookings:
        await event.show_snackbar(t["no_user_bookings"])
        return

    await event.edit_message(t["cancel_prompt"], keyboard=get_cancel_booking_keyboard(bookings, lang))
    await set_state(peer_id, CancelRecord.waiting_for_cancel)


@cancel_record_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "cancel"}))
async def process_cancel_booking(event: MessageEvent):
    peer_id = event.peer_id
    if not await state_in(peer_id, CancelRecord.waiting_for_cancel):
        await event.send_empty_answer()
        return

    booking_id = int(event.payload["id"])
    lang, t = await get_lang_and_texts(peer_id)

    booking = await get_booking_by_id(booking_id)
    if not booking:
        await event.show_snackbar(t["cancel_error"])
        await start_cancel_process(event)
        return

    dorm_id = getattr(booking, "dormitory_id", None) or (booking.machine.dormitory_id if getattr(booking, "machine", None) else None) or 1
    booking_data = {
        "dormitory_id": dorm_id,
        "date_str": booking.start_time.strftime("%d.%m"),
        "start_time_str": booking.start_time.strftime("%H:%M"),
        "end_time_str": booking.end_time.strftime("%H:%M"),
        "machine_type": booking.machine.type_machine,
        "machine_num": booking.machine.number_machine,
    }

    success = await cancel_booking(booking_id)

    if success:
        await event.show_snackbar(t["cancel_confirm_success"])
        await event.edit_message(t["cancel_confirm_success"], keyboard=get_back_to_sections_keyboard(lang))
        await clear_state(peer_id)

        asyncio.create_task(
            broadcast_slot_freed(bot, booking_data, exclude_vk_id=event.user_id)
        )
    else:
        await event.show_snackbar(t["cancel_error"])
