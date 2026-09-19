import json
import logging
from datetime import datetime, time, timedelta
from typing import Optional

import aiomax
from aiomax import Router, Callback, fsm

from app.bot.utils.translate import get_lang_and_texts
from app.bot.utils.timezone import get_kemerovo_now
from app.bot.utils.calendar_utils import (
    build_month_calendar_keyboard,
    parse_picked_date,
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
    get_month_workload,
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


def _format_calendar_header(t: dict, machine_type_db: str, warning: Optional[str] = None) -> str:
    base = _header_text(t, machine_type_db)
    if warning:
        return f"{base}\n\n{warning}"
    return base


async def _render_calendar(
    cb: Callback,
    lang: str,
    t: dict,
    machine_type_db: str,
    max_capacity: int,
    year: Optional[int] = None,
    month: Optional[int] = None,
    dormitory_id: int = 1,
    warning: Optional[str] = None,
    notification: Optional[str] = None,
):
    now_kemerovo = get_kemerovo_now()
    cur_year = year or now_kemerovo.year
    cur_month = month or now_kemerovo.month

    workload = await get_month_workload(cur_year, cur_month, machine_type_db, dormitory_id=dormitory_id)
    header_text = _format_calendar_header(t, machine_type_db, warning=warning)
    kb = build_month_calendar_keyboard(
        cur_year,
        cur_month,
        workload,
        max_capacity,
        lang=lang,
        back_cmd="back_to_type"
    )
    await cb.answer(notification=notification, text=header_text, keyboard=kb)


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "record"))
async def process_record_start(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    user = await get_user_by_max_id(user_id)

    if not user:
        await cb.answer(notification=t["none_user"])
        return

    if getattr(user, "is_banned", False):
        await cb.answer(notification=t.get("user_banned_alert", "❌ Ваш аккаунт заблокирован. Запись недоступна."))
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
    await _render_calendar(cb, lang, t, machine_type_db, max_capacity, dormitory_id=dormitory_id)


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "calendar_month"))
async def process_calendar_month(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    payload = _get_payload(cb)
    try:
        year = int(payload.get("year"))
        month = int(payload.get("month"))
    except (ValueError, TypeError):
        now_k = get_kemerovo_now()
        year = now_k.year
        month = now_k.month

    data = cursor.get_data() or {}
    dormitory_id = data.get("dormitory_id", 1)
    machine_type_db = data.get("machine_type", "Стиральная")
    max_capacity = data.get("max_capacity") or await get_total_daily_capacity_by_type(machine_type_db, dormitory_id=dormitory_id)

    await _render_calendar(cb, lang, t, machine_type_db, max_capacity, year=year, month=month, dormitory_id=dormitory_id)


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "day_blocked"))
async def process_day_blocked(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    payload = _get_payload(cb)
    reason = payload.get("reason")
    if reason == "past":
        raw_msg = t.get("past_date_error", "Этот день уже прошёл. Пожалуйста, выберите другую дату.")
        msg = f"❌ {raw_msg}" if not raw_msg.startswith("❌") else raw_msg
    else:
        raw_msg = t.get("day_fully_booked", "На выбранную дату нет свободных мест.")
        msg = f"❌ {raw_msg}" if not raw_msg.startswith("❌") else raw_msg

    data = cursor.get_data() or {}
    dormitory_id = data.get("dormitory_id", 1)
    machine_type_db = data.get("machine_type", "Стиральная")
    max_capacity = data.get("max_capacity") or await get_total_daily_capacity_by_type(machine_type_db, dormitory_id=dormitory_id)

    await _render_calendar(
        cb,
        lang,
        t,
        machine_type_db,
        max_capacity,
        dormitory_id=dormitory_id,
        warning=msg,
        notification=msg,
    )


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "ignore"))
async def process_ignore(cb: Callback):
    await cb.answer()


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

    # 1. Проверка на прошедшую дату (по Кемерово)
    now_kemerovo = get_kemerovo_now()
    if picked_date < now_kemerovo.date() or (picked_date == now_kemerovo.date() and now_kemerovo.time() >= time(23, 0)):
        raw_msg = t.get("past_date_error", "Этот день уже прошёл. Пожалуйста, выберите другую дату.")
        msg = f"❌ {raw_msg}" if not raw_msg.startswith("❌") else raw_msg
        max_capacity = data.get("max_capacity") or await get_total_daily_capacity_by_type(machine_type, dormitory_id=dormitory_id)
        await _render_calendar(
            cb,
            lang,
            t,
            machine_type,
            max_capacity,
            year=picked_date.year,
            month=picked_date.month,
            dormitory_id=dormitory_id,
            warning=msg,
            notification=msg,
        )
        return

    # 2. Получение resident_id (гарантированное, даже если в FSM потерялось)
    resident_id = data.get("resident_id")
    if not resident_id:
        user = await get_user_by_max_id(user_id)
        if user:
            resident_id = user.id
            dormitory_id = getattr(user, "dormitory_id", 1) or 1
            data["resident_id"] = resident_id
            data["dormitory_id"] = dormitory_id
            cursor.change_data(data)

    # 3. Строгая проверка лимита броней: 1 раз в неделю (пн-вс)
    if resident_id:
        target_dt = datetime.combine(picked_date, time(12, 0))
        if await has_weekly_booking(resident_id, target_dt, machine_type):
            raw_msg = t.get(
                "weekly_limit_reached",
                "Вы уже имеете одну запись на эту неделю. Лимит: 1 в неделю."
            )
            msg = f"⚠️ {raw_msg}" if not raw_msg.startswith("⚠️") else raw_msg
            max_capacity = data.get("max_capacity") or await get_total_daily_capacity_by_type(machine_type, dormitory_id=dormitory_id)
            await _render_calendar(
                cb,
                lang,
                t,
                machine_type,
                max_capacity,
                year=picked_date.year,
                month=picked_date.month,
                dormitory_id=dormitory_id,
                warning=msg,
                notification=msg,
            )
            return

    # 4. Проверка доступных слотов
    date_for_slots = datetime.combine(picked_date, time(0, 0))
    slots = await get_available_slots(date_for_slots, machine_type=machine_type, dormitory_id=dormitory_id)

    if not slots:
        raw_msg = t.get("day_fully_booked", "На выбранную дату нет свободных мест.")
        msg = f"❌ {raw_msg}" if not raw_msg.startswith("❌") else raw_msg
        max_capacity = data.get("max_capacity") or await get_total_daily_capacity_by_type(machine_type, dormitory_id=dormitory_id)
        await _render_calendar(
            cb,
            lang,
            t,
            machine_type,
            max_capacity,
            year=picked_date.year,
            month=picked_date.month,
            dormitory_id=dormitory_id,
            warning=msg,
            notification=msg,
        )
        return

    data["picked_date"] = picked_date_str
    cursor.change_data(data)

    date_title = picked_date.strftime("%d.%m.%Y")
    label = t.get("record_time_slots", "Выберите время")
    text = f"⏰ {label} ({date_title}):"
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
    label = t.get("record_time_slots", "Выберите время")
    text = f"⏰ {label} ({date_title}):"
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

    if slot_dt <= get_kemerovo_now():
        await cb.answer(notification="Выбранное время уже прошло или наступило. Пожалуйста, выберите другое время.")
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
    label = t.get("select_machine", "Выберите машину")
    text = f"🧺 {label} ({time_header}):"
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

    label = t.get("record_success", "✅ Запись создана!\n🧺 {type} №{num}\n⏰ {start_time} - {end_time}")
    try:
        success_msg = label.format(
            type=m_type_label,
            num=booking.machine.number_machine,
            start_time=start_time.strftime("%d.%m.%Y %H:%M"),
            end_time=end_time.strftime("%H:%M")
        )
    except Exception:
        success_msg = f"✅ Запись создана!\n🧺 {m_type_label} №{booking.machine.number_machine}\n⏰ {start_time.strftime('%d.%m.%Y %H:%M')} - {end_time.strftime('%H:%M')}"

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


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "back_to_type") or _is_cmd(cb, "back_to_machine_type"))
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

    picked_date_str = data.get("picked_date")
    year = None
    month = None
    if picked_date_str:
        try:
            p_dt = parse_picked_date(picked_date_str)
            year = p_dt.year
            month = p_dt.month
        except Exception:
            pass

    cursor.change_state(AddRecord.waiting_for_day)
    await _render_calendar(cb, lang, t, machine_type_db, max_capacity, year=year, month=month, dormitory_id=dormitory_id)


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
    label = t.get("record_time_slots", "Выберите время")
    text = f"⏰ {label} ({date_title}):"
    kb = get_time_slots_keyboard(date_for_slots, slots, lang, offset=0)

    await cb.answer(text=text, keyboard=kb)
    cursor.change_state(AddRecord.waiting_for_time)


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "exit"))
async def exit_to_main(cb: Callback, cursor: fsm.FSMCursor):
    await back_to_sections(cb, cursor)


@booking_router.on_button_callback(lambda cb: _is_cmd(cb, "quick_book"))
async def process_quick_book(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    payload = _get_payload(cb)
    machine_id = int(payload.get("m_id", 0))
    start_iso = payload.get("start", "")

    try:
        start_time = datetime.strptime(start_iso, "%Y%m%d%H%M")
    except Exception:
        await cb.answer(notification="Ошибка данных бронирования")
        return

    user = await get_user_by_max_id(user_id)
    if not user:
        await cb.answer(notification=t.get("none_user", "Пользователь не найден"))
        return

    if getattr(user, "is_banned", False):
        await cb.answer(notification=t.get("user_banned_alert", "❌ Ваш аккаунт заблокирован. Запись недоступна."))
        return

    if start_time <= get_kemerovo_now():
        await cb.answer(notification=t.get("booking_error", "Время уже прошло"))
        try:
            await cb.answer(text="🔔 Время для этой записи уже прошло", keyboard=get_exit_keyboard(lang))
        except Exception:
            pass
        return

    dormitory_id = getattr(user, "dormitory_id", 1) or 1

    try:
        result = await create_booking(
            user_id=user.id,
            machine_id=machine_id,
            start_time=start_time,
            duration_minutes=DURATION_MINUTES,
            dormitory_id=dormitory_id
        )

        booking = result["booking"]
        end_time = start_time + timedelta(minutes=DURATION_MINUTES)
        m_type_raw = booking.machine.type_machine if getattr(booking, "machine", None) else ""
        m_type_label = t.get("machine_type_wash", "Стиральная") if m_type_raw == "Стиральная" else t.get("machine_type_dry", "Сушильная")

        success_text = t.get(
            "quick_book_success",
            "✅ Вы успешно записались на освободившееся место!\n\n🧺 {m_type} №{m_num}\n📅 Дата: {date}\n⏰ Время: {time}"
        ).format(
            m_type=m_type_label,
            m_num=booking.machine.number_machine if getattr(booking, "machine", None) else "",
            date=start_time.strftime("%d.%m.%Y"),
            time=f"{start_time.strftime('%H:%M')} – {end_time.strftime('%H:%M')}"
        )
        if getattr(booking, "dormitory_id", None):
            success_text += f"\n🏢 Общежитие №{booking.dormitory_id}"

        if getattr(booking, "status", "") == "Подтверждено":
            success_text += f"\n\n{t.get('booking_confirmed', '✅ Запись подтверждена!')}"

        await cb.answer(notification="✅ Запись подтверждена!")
        await cb.answer(text=success_text, keyboard=get_exit_keyboard(lang))
        cursor.clear_state()
        return

    except ValueError as e:
        error_msg = str(e)
        if "Лимит" in error_msg or "Weekly limit" in error_msg:
            await cb.answer(notification=t.get("weekly_limit_reached", "Лимит: 1 запись в неделю!"))
        elif "Слот уже занят" in error_msg or "Slot is already taken" in error_msg:
            taken_msg = t.get("slot_taken_by_other", "❌ Этот слот уже успел занять другой житель!")
            await cb.answer(notification=taken_msg)
            try:
                await cb.answer(text="❌ Этот слот уже занят другим жителем.", keyboard=get_exit_keyboard(lang))
            except Exception:
                pass
        elif "Нельзя забронировать" in error_msg or "Cannot book past time" in error_msg:
            await cb.answer(notification=t.get("booking_error", "Время уже прошло"))
        else:
            await cb.answer(notification=str(e))
        logging.warning(f"[MaxBot] Quick booking error for user {user.id}: {e}")
