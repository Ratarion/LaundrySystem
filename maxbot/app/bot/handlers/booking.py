import json
import logging
from datetime import datetime, time, timedelta
from typing import Optional

import aiomax
from aiomax import Router, Callback, fsm

from app.bot.utils.translate import get_lang_and_texts
from app.bot.utils.calendar_utils import (
    build_date_picker_keyboard,
    parse_picked_date,
    get_range_bounds,
    is_day_selectable,
)
from app.bot.states import AddRecord
from app.bot.keyboards import (
    get_section_keyboard,
    get_time_slots_keyboard,
    get_machines_keyboard,
    get_exit_keyboard,
    get_machine_type_keyboard,
    get_back_to_sections_keyboard,
)
from app.laundry_repo import (
    get_user_by_max_id,
    get_available_slots,
    get_available_machines,
    create_booking,
    get_range_workload,
    get_total_daily_capacity_by_type,
    has_weekly_booking,
)

booking_router = Router()

DURATION_MINUTES = 90


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


def _header_text(t: dict, machine_type_db: str) -> str:
    if machine_type_db == "Стиральная":
        return f"📅 {t['record_start']} {t['for_wash']}"
    return f"📅 {t['record_start']} {t['for_dry']}"


async def _render_calendar(cb: Callback, lang: str, t: dict, machine_type_db: str, max_capacity: int, offset: int = 0, dormitory_id: int = 1):
    start, end = get_range_bounds(offset)
    workload = await get_range_workload(start, end, machine_type_db, dormitory_id=dormitory_id)
    header_text = _header_text(t, machine_type_db)
    kb = build_date_picker_keyboard(workload, max_capacity, lang, offset=offset)
    await cb.answer(text=header_text, keyboard=kb)


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "record"))
async def process_record_start(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    user = await get_user_by_max_id(user_id)

    if not user:
        await cb.answer(notification=t["none_user"])
        return

    dormitory_id = getattr(user, "dormitory_id", 1) or 1
    data = cursor.get_data() or {}
    data["resident_id"] = user.id
    data["dormitory_id"] = dormitory_id
    cursor.change_data(data)

    max_capacity = await get_total_daily_capacity_by_type(dormitory_id=dormitory_id)
    if max_capacity == 0:
        await cb.answer(notification=t["no_active_machines"])
        await cb.answer(text=t["section_menu_title"], keyboard=get_section_keyboard(lang))
        cursor.clear_state()
        return

    data["max_capacity"] = max_capacity
    cursor.change_data(data)

    await cb.answer(text=t["select_machine_type"], keyboard=get_machine_type_keyboard(lang))
    cursor.change_state(AddRecord.waiting_for_machine_type)


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "type"))
async def process_machine_type(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    payload = _get_payload(cb)
    machine_type_callback = payload.get("type")

    machine_type_db = "Стиральная" if machine_type_callback == "WASH" else "Сушильная"

    data = cursor.get_data() or {}
    dormitory_id = data.get("dormitory_id", 1)
    data["machine_type"] = machine_type_db

    max_capacity = await get_total_daily_capacity_by_type(machine_type_db, dormitory_id=dormitory_id)
    data["max_capacity"] = max_capacity
    cursor.change_data(data)

    cursor.change_state(AddRecord.waiting_for_day)
    await _render_calendar(cb, lang, t, machine_type_db, max_capacity, offset=0, dormitory_id=dormitory_id)


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "day_page"))
async def process_day_page(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    payload = _get_payload(cb)
    offset = int(payload.get("offset", 0))

    data = cursor.get_data() or {}
    dormitory_id = data.get("dormitory_id", 1)
    machine_type_db = data.get("machine_type", "Стиральная")
    max_capacity = data.get("max_capacity") or await get_total_daily_capacity_by_type(machine_type_db, dormitory_id=dormitory_id)

    await _render_calendar(cb, lang, t, machine_type_db, max_capacity, offset=offset, dormitory_id=dormitory_id)


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "day"))
async def process_day_pick(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    payload = _get_payload(cb)
    picked_date_str = payload.get("date")

    data = cursor.get_data() or {}
    dormitory_id = data.get("dormitory_id", 1)
    machine_type = data.get("machine_type", "Стиральная")

    try:
        picked_date = parse_picked_date(picked_date_str)
    except Exception:
        await cb.answer(notification="Ошибка даты")
        return

    # Проверка лимита броней в неделю
    resident_id = data.get("resident_id")
    if resident_id:
        target_dt = datetime.combine(picked_date, time(12, 0))
        if await has_weekly_booking(resident_id, target_dt, machine_type):
            msg = t.get(
                "weekly_limit_reached",
                "⚠️ У вас уже есть активная запись на этой неделе."
            )
            await cb.answer(notification=msg)
            return

    data["picked_date"] = picked_date_str
    cursor.change_data(data)

    date_for_slots = datetime.combine(picked_date, time(0, 0))
    slots = await get_available_slots(date_for_slots, machine_type=machine_type, dormitory_id=dormitory_id)

    if not slots:
        await cb.answer(notification=t.get("no_slots", "Нет свободных слотов на этот день"))
        return

    date_title = picked_date.strftime("%d.%m.%Y")
    text = f"⏰ {t['record_time_slots']} ({date_title}):"
    kb = get_time_slots_keyboard(date_for_slots, slots, lang, offset=0)

    await cb.answer(text=text, keyboard=kb)
    cursor.change_state(AddRecord.waiting_for_time)


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "time_page"))
async def process_time_page(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    payload = _get_payload(cb)
    offset = int(payload.get("offset", 0))

    data = cursor.get_data() or {}
    dormitory_id = data.get("dormitory_id", 1)
    picked_date_str = data.get("picked_date")
    machine_type = data.get("machine_type", "Стиральная")

    if not picked_date_str:
        await cb.answer(notification="Ошибка: выберите дату заново")
        return

    picked_date = parse_picked_date(picked_date_str)
    date_for_slots = datetime.combine(picked_date, time(0, 0))
    slots = await get_available_slots(date_for_slots, machine_type=machine_type, dormitory_id=dormitory_id)

    date_title = picked_date.strftime("%d.%m.%Y")
    text = f"⏰ {t['record_time_slots']} ({date_title}):"
    kb = get_time_slots_keyboard(date_for_slots, slots, lang, offset=offset)

    await cb.answer(text=text, keyboard=kb)


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "time"))
async def process_time_pick(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    payload = _get_payload(cb)
    slot_iso = payload.get("start")

    try:
        slot_dt = datetime.fromisoformat(slot_iso)
    except Exception:
        await cb.answer(notification="Ошибка выбора времени")
        return

    data = cursor.get_data() or {}
    dormitory_id = data.get("dormitory_id", 1)
    data["picked_slot"] = slot_iso
    cursor.change_data(data)

    machine_type = data.get("machine_type", "Стиральная")
    available_machines = await get_available_machines(slot_dt, machine_type, dormitory_id=dormitory_id)

    if not available_machines:
        await cb.answer(notification=t.get("no_available_machines", "Нет доступных машин на это время"))
        return

    slot_end = slot_dt + timedelta(minutes=DURATION_MINUTES)
    time_header = f"{slot_dt.strftime('%d.%m %H:%M')}-{slot_end.strftime('%H:%M')}"
    text = f"🧺 {t['select_machine']} ({time_header}):"
    kb = get_machines_keyboard(available_machines, lang)

    await cb.answer(text=text, keyboard=kb)
    cursor.change_state(AddRecord.waiting_for_machine)


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "machine"))
async def process_machine_pick(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    payload = _get_payload(cb)
    machine_id = int(payload.get("id", 0))

    data = cursor.get_data() or {}
    resident_id = data.get("resident_id")
    dormitory_id = data.get("dormitory_id", 1)
    slot_iso = data.get("picked_slot")

    if not resident_id or not slot_iso:
        user = await get_user_by_max_id(user_id)
        if not user:
            await cb.answer(notification=t["none_user"])
            return
        resident_id = user.id
        dormitory_id = getattr(user, "dormitory_id", 1) or 1

    try:
        start_time = datetime.fromisoformat(slot_iso)
    except Exception:
        await cb.answer(notification="Ошибка данных брони")
        return

    try:
        result = await create_booking(
            user_id=resident_id,
            machine_id=machine_id,
            start_time=start_time,
            duration_minutes=DURATION_MINUTES,
            dormitory_id=dormitory_id,
        )
    except ValueError as e:
        await cb.answer(notification=str(e))
        return
    except Exception as e:
        logging.error(f"[MaxBot] Ошибка при create_booking: {e}")
        await cb.answer(notification="Не удалось создать бронь")
        return

    booking = result["booking"]
    end_time = start_time + timedelta(minutes=DURATION_MINUTES)

    m_type_raw = booking.machine.type_machine
    m_type_label = t.get("machine_type_wash", "Стиральная") if m_type_raw == "Стиральная" else t.get("machine_type_dry", "Сушильная")

    success_msg = t["record_success"].format(
        type=m_type_label,
        num=booking.machine.number_machine,
        start_time=start_time.strftime("%d.%m.%Y %H:%M"),
        end_time=end_time.strftime("%H:%M")
    )
    if getattr(booking, "dormitory_id", None):
        success_msg += f"\n🏢 Общежитие №{booking.dormitory_id}"

    await cb.answer(text=success_msg, keyboard=get_back_to_sections_keyboard(lang))
    cursor.clear_state()


# --- НАВИГАЦИОННЫЕ КНОПКИ НАЗАД ---

@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "back_to_sections"))
async def back_to_sections(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    cursor.clear_state()
    await cb.answer(text=t.get("section_menu_title", "Главное меню:"), keyboard=get_section_keyboard(lang))


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "back_to_type"))
async def back_to_type(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    cursor.change_state(AddRecord.waiting_for_machine_type)
    await cb.answer(text=t["select_machine_type"], keyboard=get_machine_type_keyboard(lang))


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "back_to_calendar"))
async def back_to_calendar(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    data = cursor.get_data() or {}
    dormitory_id = data.get("dormitory_id", 1)
    machine_type_db = data.get("machine_type", "Стиральная")
    max_capacity = data.get("max_capacity") or await get_total_daily_capacity_by_type(machine_type_db, dormitory_id=dormitory_id)
    cursor.change_state(AddRecord.waiting_for_day)
    await _render_calendar(cb, lang, t, machine_type_db, max_capacity, offset=0, dormitory_id=dormitory_id)


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "back_to_time"))
async def back_to_time(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    data = cursor.get_data() or {}
    dormitory_id = data.get("dormitory_id", 1)
    picked_date_str = data.get("picked_date")
    machine_type = data.get("machine_type", "Стиральная")

    if not picked_date_str:
        await back_to_calendar(cb, cursor)
        return

    picked_date = parse_picked_date(picked_date_str)
    date_for_slots = datetime.combine(picked_date, time(0, 0))
    slots = await get_available_slots(date_for_slots, machine_type=machine_type, dormitory_id=dormitory_id)

    date_title = picked_date.strftime("%d.%m.%Y")
    text = f"⏰ {t['record_time_slots']} ({date_title}):"
    kb = get_time_slots_keyboard(date_for_slots, slots, lang, offset=0)

    await cb.answer(text=text, keyboard=kb)
    cursor.change_state(AddRecord.waiting_for_time)


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "exit"))
async def exit_to_main(cb: Callback, cursor: fsm.FSMCursor):
    await back_to_sections(cb, cursor)
