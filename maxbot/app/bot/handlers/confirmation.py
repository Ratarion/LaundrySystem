import asyncio
import json
import logging

import aiomax
from aiomax import Router, Callback, fsm

from app.laundry_repo import get_booking_by_id, set_booking_status, cancel_booking, get_user_by_max_id
from app.bot.utils.translate import get_lang_and_texts
from app.bot.utils.broadcaster import broadcast_slot_freed
from app.bot.keyboards import get_confirmed_keyboard, get_declined_keyboard, get_section_keyboard

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
        await cb.answer(notification=t.get("booking_already_confirmed", "Запись не найдена"))
        return

    if booking.status == "Подтверждено":
        await cb.answer(notification=t.get("booking_already_confirmed", "Запись уже подтверждена"))
        await cb.answer(text=t.get("booking_confirmed", "✅ Запись подтверждена! Ждем вас."), keyboard=get_confirmed_keyboard(booking_id, lang))
        return

    if booking.status == "Отменено":
        msg = t.get("booking_already_canceled", t.get("cancel_error", "Запись уже отменена"))
        await cb.answer(notification=msg)
        await cb.answer(text=msg, keyboard=get_declined_keyboard(lang))
        return

    await set_booking_status(booking_id, "Подтверждено")
    confirm_msg = t.get("booking_confirmed", "✅ Запись подтверждена! Ждем вас.")
    try:
        from app.services.discipline_service import apply_discipline_event, EVENT_CONFIRM_ON_TIME
        disc_res = await apply_discipline_event(booking.inidresidents, booking_id, EVENT_CONFIRM_ON_TIME)
        if disc_res:
            bonus_text = t.get("discipline_confirm_bonus", "").format(
                delta=disc_res["delta"],
                streak=disc_res["confirm_streak"],
                score=disc_res["new_score"]
            )
            if bonus_text:
                confirm_msg = f"{confirm_msg}\n\n{bonus_text}"
    except Exception as e:
        logger.error(f"Error applying discipline for booking {booking_id}: {e}")

    await cb.answer(text=confirm_msg, keyboard=get_confirmed_keyboard(booking_id, lang))


@confirm_router.on_button_callback(lambda cb: _is_cmd(cb, "decline"))
async def process_decline(cb: Callback, cursor: fsm.FSMCursor):
    payload = _get_payload(cb)
    booking_id = int(payload.get("id", 0))

    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)

    booking = await get_booking_by_id(booking_id)

    if not booking:
        await cb.answer(notification=t.get("booking_already_canceled", "Запись не найдена"))
        return

    if booking.status == "Отменено":
        await cb.answer(notification=t.get("booking_already_canceled", "Запись уже отменена"))
        await cb.answer(text=t.get("booking_declined", "Запись отменена"), keyboard=get_declined_keyboard(lang))
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
        await cb.answer(notification=t.get("booking_declined", "Запись отменена"))
        decline_msg = t.get("booking_declined", "❌ Вы отменили запись. Слот освобожден для других жильцов.")
        try:
            from app.services.discipline_service import apply_discipline_event, EVENT_DECLINE_ON_CONFIRM
            disc_res = await apply_discipline_event(booking.inidresidents, booking_id, EVENT_DECLINE_ON_CONFIRM)
            if disc_res:
                reward_text = t.get("discipline_decline_reward", "").format(
                    delta=disc_res["delta"],
                    score=disc_res["new_score"]
                )
                if reward_text:
                    decline_msg = f"{decline_msg}\n\n{reward_text}"
        except Exception as e:
            logger.error(f"Error applying discipline on decline for booking {booking_id}: {e}")

        await cb.answer(
            text=decline_msg,
            keyboard=get_declined_keyboard(lang)
        )
        asyncio.create_task(
            broadcast_slot_freed(cursor.storage.bot, booking_data, exclude_max_id=user_id)
        )
    else:
        await cb.answer(notification=t.get("cancel_error", "Ошибка при отмене"))


@confirm_router.on_button_callback(lambda cb: _is_cmd(cb, "to_main_menu"))
async def process_to_main_menu(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)

    cursor.clear_state()
    await cb.answer(
        text=t.get("section_menu_title", "Главное меню:"),
        keyboard=get_section_keyboard(lang)
    )

