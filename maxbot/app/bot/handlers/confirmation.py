import json

import aiomax
from aiomax import Router, Callback, fsm

from app.laundry_repo import get_booking_by_id, set_booking_status
from app.bot.utils.translate import get_lang_and_texts

confirm_router = Router()


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


@confirm_router.on_button_callback(lambda cb: _is_cmd(cb, "confirm"))
async def process_confirm(cb: Callback, cursor: fsm.FSMCursor):
    payload = _get_payload(cb)
    booking_id = int(payload.get("id", 0))

    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)

    booking = await get_booking_by_id(booking_id)

    if not booking:
        await cb.answer(notification=t["booking_already_confirmed"])
        return

    if booking.status == "Подтверждено":
        await cb.answer(notification=t["booking_already_confirmed"])
        await cb.answer(text=t["booking_confirmed"])
        return

    if booking.status == "Отменено":
        msg = t.get("booking_already_canceled", t["cancel_error"])
        await cb.answer(notification=msg)
        await cb.answer(text=msg)
        return

    await set_booking_status(booking_id, "Подтверждено")
    await cb.answer(text=t["booking_confirmed"])
