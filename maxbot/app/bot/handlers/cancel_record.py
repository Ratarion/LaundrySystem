import asyncio
import json
import logging

import aiomax
from aiomax import Router, Callback, fsm

from app.bot.utils.translate import get_lang_and_texts
from app.bot.keyboards import get_cancel_booking_keyboard, get_back_to_sections_keyboard
from app.bot.states import CancelRecord
from app.laundry_repo import get_user_by_max_id, get_user_bookings, cancel_booking, get_booking_by_id
from app.bot.utils.broadcaster import broadcast_slot_freed

cancel_record_router = Router()


def _is_cmd(cb: Callback, cmd_name: str) -> bool:
    try:
        data = json.loads(cb.payload)
        return data.get("cmd") == cmd_name
    except Exception:
        return cb.payload == cmd_name


def _get_payload(cb: Callback) -> dict:
    try:
        return json.loads(cb.payload)
    except Exception:
        return {"cmd": cb.payload}


@cancel_record_router.on_button_callback(lambda cb: _is_cmd(cb, "remove_records"))
async def start_cancel_process(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    user = await get_user_by_max_id(user_id)

    if not user:
        await cb.answer(notification=t["none_user"])
        return

    bookings = await get_user_bookings(user.id)
    if not bookings:
        await cb.answer(notification=t["no_user_bookings"])
        return

    await cb.answer(text=t["cancel_prompt"], keyboard=get_cancel_booking_keyboard(bookings, lang))
    cursor.change_state(CancelRecord.waiting_for_cancel)


@cancel_record_router.on_button_callback(lambda cb: _is_cmd(cb, "cancel"))
async def process_cancel_booking(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    payload = _get_payload(cb)
    booking_id = int(payload.get("id", 0))

    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    user = await get_user_by_max_id(user_id)

    if not user:
        await cb.answer(notification=t["none_user"])
        return

    booking = await get_booking_by_id(booking_id)
    if not booking or booking.inidresidents != user.id:
        await cb.answer(notification=t["cancel_error"])
        await start_cancel_process(cb, cursor)
        return

    dorm_id = getattr(booking, "dormitory_id", None) or (booking.machine.dormitory_id if getattr(booking, "machine", None) else None) or getattr(user, "dormitory_id", 1) or 1
    booking_data = {
        "dormitory_id": dorm_id,
        "machine_id": getattr(booking, "inidmachine", None) or (booking.machine.id if getattr(booking, "machine", None) else None),
        "start_iso": booking.start_time.strftime("%Y%m%d%H%M"),
        "date_str": booking.start_time.strftime("%d.%m"),
        "start_time_str": booking.start_time.strftime("%H:%M"),
        "end_time_str": booking.end_time.strftime("%H:%M"),
        "machine_type": booking.machine.type_machine if getattr(booking, "machine", None) else "",
        "machine_num": booking.machine.number_machine if getattr(booking, "machine", None) else "",
    }

    success = await cancel_booking(booking_id, user_id=user.id)

    if success:
        await cb.answer(notification=t["cancel_confirm_success"])
        cancel_success_msg = t["cancel_confirm_success"]
        try:
            from app.services.discipline_service import apply_discipline_event, EVENT_EARLY_CANCEL
            disc_res = await apply_discipline_event(booking.inidresidents, booking_id, EVENT_EARLY_CANCEL)
            if disc_res:
                reward_text = t.get("discipline_early_cancel_reward", "").format(
                    delta=disc_res["delta"],
                    score=disc_res["new_score"]
                )
                if reward_text:
                    cancel_success_msg = f"{cancel_success_msg}\n\n{reward_text}"
        except Exception as e:
            logger.error(f"Error applying discipline on early cancel for booking {booking_id}: {e}")

        await cb.answer(text=cancel_success_msg, keyboard=get_back_to_sections_keyboard(lang))
        cursor.clear_state()

        asyncio.create_task(
            broadcast_slot_freed(cursor.storage.bot, booking_data, exclude_max_id=user_id)
        )
    else:
        await cb.answer(notification=t["cancel_error"])
