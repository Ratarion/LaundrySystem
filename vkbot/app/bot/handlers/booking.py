import logging
from datetime import datetime, time, timedelta

from vkbottle import GroupEventType
from vkbottle.bot import BotLabeler, MessageEvent
from vkbottle.dispatch.rules.base import PayloadContainsRule

from app.bot.utils.translate import get_lang_and_texts
from app.bot.utils.calendar_utils import (
    build_date_picker_keyboard,
    parse_picked_date,
    get_range_bounds,
)
from app.bot.states import AddRecord
from app.bot.keyboards import (
    get_section_keyboard,
    get_time_slots_keyboard,
    get_machines_keyboard,
    get_exit_keyboard,
    get_machine_type_keyboard,
)
from app.laundry_repo import (
    get_user_by_vk_id,
    get_available_slots,
    get_available_machines,
    create_booking,
    get_range_workload,
    get_total_daily_capacity_by_type,
    has_weekly_booking,
)
from app.bot.utils.fsm import set_state, update_state_data, clear_state, get_state_data, state_in

booking_labeler = BotLabeler()

DURATION_MINUTES = 90


def _header_text(t: dict, machine_type_db: str) -> str:
    if machine_type_db == "Стиральная":
        return f"📅 {t['record_start']} {t['for_wash']}"
    return f"📅 {t['record_start']} {t['for_dry']}"


async def _render_calendar(event: MessageEvent, peer_id: int, lang: str, t: dict, machine_type_db: str,
                            max_capacity: int, offset: int = 0, dormitory_id: int = 1):
    start, end = get_range_bounds(offset)
    workload = await get_range_workload(start, end, machine_type_db, dormitory_id=dormitory_id)
    header_text = _header_text(t, machine_type_db)
    kb = build_date_picker_keyboard(workload, max_capacity, lang, offset=offset)
    await event.edit_message(header_text, keyboard=kb)


@booking_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "record"}))
async def process_record_start(event: MessageEvent):
    peer_id = event.peer_id
    lang, t = await get_lang_and_texts(peer_id)
    user = await get_user_by_vk_id(event.user_id)
    if not user:
        await event.show_snackbar(t["none_user"])
        return

    dormitory_id = getattr(user, "dormitory_id", 1) or 1
    await update_state_data(peer_id, user_id=user.id, dormitory_id=dormitory_id)

    max_capacity = await get_total_daily_capacity_by_type(dormitory_id=dormitory_id)
    if max_capacity == 0:
        await event.show_snackbar(t["no_active_machines"])
        await event.edit_message(t["section_menu_title"], keyboard=get_section_keyboard(lang))
        await clear_state(peer_id)
        return

    await update_state_data(peer_id, max_capacity=max_capacity)
    await event.edit_message(t["select_machine_type"], keyboard=get_machine_type_keyboard(lang))
    await set_state(peer_id, AddRecord.waiting_for_machine_type)


@booking_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "type"}))
async def process_machine_type(event: MessageEvent):
    peer_id = event.peer_id
    if not await state_in(peer_id, AddRecord.waiting_for_machine_type):
        await event.send_empty_answer()
        return

    lang, t = await get_lang_and_texts(peer_id)
    data = await get_state_data(peer_id)
    dormitory_id = data.get("dormitory_id", 1)
    machine_type_callback = event.payload.get("type")  # "WASH" или "DRY"

    if machine_type_callback == "WASH":
        machine_type_db = "Стиральная"
    else:
        machine_type_db = "Сушильная"

    await update_state_data(peer_id, machine_type=machine_type_db)

    max_capacity = await get_total_daily_capacity_by_type(machine_type_db, dormitory_id=dormitory_id)
    await update_state_data(peer_id, max_capacity=max_capacity)

    await set_state(peer_id, AddRecord.waiting_for_day)
    await _render_calendar(event, peer_id, lang, t, machine_type_db, max_capacity, offset=0, dormitory_id=dormitory_id)


@booking_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "day_page"}))
async def process_calendar_page(event: MessageEvent):
    peer_id = event.peer_id
    if not await state_in(peer_id, AddRecord.waiting_for_day):
        await event.send_empty_answer()
        return

    lang, t = await get_lang_and_texts(peer_id)
    data = await get_state_data(peer_id)
    dormitory_id = data.get("dormitory_id", 1)
    machine_type_db = data.get("machine_type")
    max_capacity = data.get("max_capacity", 0)
    offset = int(event.payload.get("offset", 0))

    await _render_calendar(event, peer_id, lang, t, machine_type_db, max_capacity, offset=offset, dormitory_id=dormitory_id)


@booking_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "day"}))
async def process_day_picked(event: MessageEvent):
    peer_id = event.peer_id
    if not await state_in(peer_id, AddRecord.waiting_for_day):
        await event.send_empty_answer()
        return

    lang, t = await get_lang_and_texts(peer_id)
    data = await get_state_data(peer_id)
    dormitory_id = data.get("dormitory_id", 1)
    max_capacity = data.get("max_capacity", 0)
    machine_type_db = data.get("machine_type")

    date = parse_picked_date(event.payload["date"])
    header_text = _header_text(t, machine_type_db)

    now_dt = datetime.now()
    if date.date() < now_dt.date() or (date.date() == now_dt.date() and now_dt.time() >= time(23, 0)):
        await event.show_snackbar(t["past_date_error"])
        return

    day_start = datetime(date.year, date.month, date.day)
    day_end = day_start + timedelta(days=1)
    workload = await get_range_workload(day_start, day_end, machine_type_db, dormitory_id=dormitory_id)
    used = workload.get(date.date(), 0)
    free = max_capacity - used if max_capacity > 0 else 0
    if free <= 0:
        await event.show_snackbar(t["day_fully_booked"])
        return

    user_id = data.get("user_id")
    if await has_weekly_booking(user_id, date, machine_type_db):
        await event.show_snackbar(t["weekly_limit_reached"])
        return

    await update_state_data(peer_id, chosen_date=date.date().isoformat())
    slots = await get_available_slots(date, machine_type=machine_type_db, dormitory_id=dormitory_id)
    if not slots:
        await event.show_snackbar(t["no_slots_available"])
        return

    await event.edit_message(
        t["select_time_prompt"].replace("{date}", date.strftime("%d.%m")),
        keyboard=get_time_slots_keyboard(date, slots, lang),
    )
    await set_state(peer_id, AddRecord.waiting_for_time)


@booking_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "time"}))
async def process_time_slot(event: MessageEvent):
    peer_id = event.peer_id
    if not await state_in(peer_id, AddRecord.waiting_for_time):
        await event.send_empty_answer()
        return

    lang, t = await get_lang_and_texts(peer_id)
    data = await get_state_data(peer_id)
    dormitory_id = data.get("dormitory_id", 1)

    chosen_dt = datetime.fromisoformat(event.payload["start"])
    end_dt = chosen_dt + timedelta(minutes=DURATION_MINUTES)

    await update_state_data(peer_id, start_time=chosen_dt.isoformat())

    machine_type_db = data.get("machine_type")
    available_machines = await get_available_machines(chosen_dt, machine_type_db, dormitory_id=dormitory_id)

    if not available_machines:
        await event.show_snackbar(t["no_available_slots_alert"])
        await event.edit_message(t["machines_none"])
        return

    prompt_text = t["machine_prompt"].format(
        date=chosen_dt.strftime("%d.%m"),
        start=chosen_dt.strftime("%H:%M"),
        end=end_dt.strftime("%H:%M"),
    )

    await event.edit_message(prompt_text, keyboard=get_machines_keyboard(available_machines, lang))
    await set_state(peer_id, AddRecord.waiting_for_machine)


@booking_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "time_page"}))
async def process_time_page(event: MessageEvent):
    peer_id = event.peer_id
    if not await state_in(peer_id, AddRecord.waiting_for_time):
        await event.send_empty_answer()
        return

    lang, t = await get_lang_and_texts(peer_id)
    data = await get_state_data(peer_id)
    dormitory_id = data.get("dormitory_id", 1)
    chosen_date_str = data.get("chosen_date")
    machine_type_db = data.get("machine_type")
    if not chosen_date_str:
        await event.send_empty_answer()
        return

    chosen_date = parse_picked_date(chosen_date_str)
    slots = await get_available_slots(chosen_date, machine_type=machine_type_db, dormitory_id=dormitory_id)
    offset = int(event.payload.get("offset", 0))

    await event.edit_message(
        t["select_time_prompt"].replace("{date}", chosen_date.strftime("%d.%m")),
        keyboard=get_time_slots_keyboard(chosen_date, slots, lang, offset=offset),
    )


@booking_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "machine"}))
async def process_machine(event: MessageEvent):
    peer_id = event.peer_id
    if not await state_in(peer_id, AddRecord.waiting_for_machine):
        await event.send_empty_answer()
        return

    lang, t = await get_lang_and_texts(peer_id)
    machine_id = int(event.payload["id"])
    data = await get_state_data(peer_id)
    dormitory_id = data.get("dormitory_id", 1)

    start_time = datetime.fromisoformat(data["start_time"])
    end_time = start_time + timedelta(minutes=DURATION_MINUTES)

    user_id = data.get("user_id")
    if not user_id:
        await event.show_snackbar(t.get("none_user", "User not found"))
        return

    try:
        result = await create_booking(user_id=user_id, machine_id=machine_id, start_time=start_time, dormitory_id=dormitory_id)

        msg = t["booking_success"].format(
            machine_num=result["machine"].number_machine,
            start=start_time.strftime("%d.%m.%Y %H:%M"),
            end=end_time.strftime("%H:%M"),
        )
        if getattr(result.get("booking"), "dormitory_id", None):
            msg += f"\n🏢 Общежитие №{result['booking'].dormitory_id}"

        await event.edit_message(
            msg,
            keyboard=get_exit_keyboard(lang),
        )
        await clear_state(peer_id)
        await update_state_data(peer_id, lang=lang)
        return

    except ValueError as e:
        error_msg = str(e)
        if error_msg == "Weekly limit reached":
            await event.show_snackbar(t["weekly_limit_reached"])
            await _back_to_time(event, peer_id)
            return
        elif error_msg == "Слот уже занят":
            await event.show_snackbar(t["slot_just_taken"])
        else:
            await event.show_snackbar(t["booking_error"])
        logging.exception(e)

    await clear_state(peer_id)
    await update_state_data(peer_id, lang=lang)


@booking_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "back_to_sections"}))
async def process_back_to_sections(event: MessageEvent):
    peer_id = event.peer_id
    lang, t = await get_lang_and_texts(peer_id)
    user = await get_user_by_vk_id(event.user_id)
    if not user:
        await event.show_snackbar(t["none_user"])
        return

    await event.edit_message(t["hello_user"].format(name=user.first_name), keyboard=get_section_keyboard(lang))
    await clear_state(peer_id)
    await update_state_data(peer_id, lang=lang)


async def _back_to_time(event: MessageEvent, peer_id: int):
    lang, t = await get_lang_and_texts(peer_id)
    data = await get_state_data(peer_id)
    dormitory_id = data.get("dormitory_id", 1)
    chosen_date_str = data.get("chosen_date")
    if not chosen_date_str:
        await event.show_snackbar("Дата не найдена")
        return
    chosen_date = parse_picked_date(chosen_date_str)
    machine_type_db = data.get("machine_type")
    slots = await get_available_slots(chosen_date, machine_type=machine_type_db, dormitory_id=dormitory_id)
    await event.edit_message(
        t["select_time_prompt"].replace("{date}", chosen_date.strftime("%d.%m")),
        keyboard=get_time_slots_keyboard(chosen_date, slots, lang),
    )
    await set_state(peer_id, AddRecord.waiting_for_time)


@booking_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "back_to_calendar"}))
async def process_back_to_calendar(event: MessageEvent):
    peer_id = event.peer_id
    if not await state_in(peer_id, AddRecord.waiting_for_time):
        await event.send_empty_answer()
        return

    lang, t = await get_lang_and_texts(peer_id)
    data = await get_state_data(peer_id)
    machine_type_db = data.get("machine_type")
    max_capacity = data.get("max_capacity", 0)

    await set_state(peer_id, AddRecord.waiting_for_day)
    await _render_calendar(event, peer_id, lang, t, machine_type_db, max_capacity, offset=0)


@booking_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "back_to_time"}))
async def process_back_to_time(event: MessageEvent):
    peer_id = event.peer_id
    if not await state_in(peer_id, AddRecord.waiting_for_machine):
        await event.send_empty_answer()
        return
    await _back_to_time(event, peer_id)


@booking_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "back_to_type"}))
async def back_to_machine_type(event: MessageEvent):
    peer_id = event.peer_id
    lang, t = await get_lang_and_texts(peer_id)
    await event.edit_message(t["select_machine_type"], keyboard=get_machine_type_keyboard(lang))
    await set_state(peer_id, AddRecord.waiting_for_machine_type)


@booking_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "exit"}))
async def process_exit(event: MessageEvent):
    peer_id = event.peer_id
    lang, t = await get_lang_and_texts(peer_id)
    user = await get_user_by_vk_id(event.user_id)
    if not user:
        await event.show_snackbar(t["none_user"])
        return

    await event.edit_message(t["hello_user"].format(name=user.first_name), keyboard=get_section_keyboard(lang))
