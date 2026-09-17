import json

import aiomax
from aiomax import Router, Callback, fsm

from app.bot.utils.translate import get_lang_and_texts
from app.bot.keyboards import get_back_to_sections_keyboard
from app.bot.states import DisplayRecords
from app.laundry_repo import get_user_by_max_id, get_user_bookings

records_router = Router()


def _is_cmd(cb: Callback, cmd_name: str) -> bool:
    try:
        data = json.loads(cb.payload)
        return data.get("cmd") == cmd_name
    except Exception:
        return cb.payload == cmd_name


@records_router.on_button_callback(lambda cb: _is_cmd(cb, "show_records"))
async def show_records(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    cursor.change_state(DisplayRecords.waiting_for_display)

    user = await get_user_by_max_id(user_id)
    if not user:
        await cb.answer(notification=t["none_user"])
        return

    bookings = await get_user_bookings(user.id)
    back_kb = get_back_to_sections_keyboard(lang)

    if not bookings:
        no_bookings_text = t.get("no_user_bookings", "У вас нет активных записей.")
        await cb.answer(text=no_bookings_text, keyboard=back_kb)
        return

    lines = []
    machine_label = t.get("machine", "Машина")

    for b in bookings[:20]:
        start_str = b.start_time.strftime("%d.%m.%Y %H:%M") if b.start_time else "—"
        end_str = b.end_time.strftime("%H:%M") if b.end_time else "—"
        machine_num = b.machine.number_machine if getattr(b, "machine", None) else "—"

        raw_type = b.machine.type_machine if getattr(b, "machine", None) else "—"
        if raw_type == "Стиральная":
            machine_type = t.get("machine_type_wash", "Стиральная")
        elif raw_type == "Сушильная":
            machine_type = t.get("machine_type_dry", "Сушильная")
        else:
            machine_type = raw_type

        status_icon = "🟢" if b.status == "Подтверждено" else ("⏳" if b.status == "Ожидание подтверждения" else "⚪")
        dorm_text = f" • Общ. №{b.dormitory_id}" if getattr(b, "dormitory_id", None) else ""
        lines.append(f"{status_icon} {start_str} - {end_str}{dorm_text} • {machine_label} №{machine_num} ({machine_type}) [{b.status}]")

    title = t.get("show_records_title", "Ваши активные записи:")
    text = title + "\n\n" + "\n".join(lines)

    await cb.answer(text=text, keyboard=back_kb)
