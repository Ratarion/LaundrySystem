from vkbottle import GroupEventType
from vkbottle.bot import BotLabeler, MessageEvent
from vkbottle.dispatch.rules.base import PayloadContainsRule

from app.bot.utils.translate import get_lang_and_texts
from app.bot.keyboards import get_back_to_sections_keyboard
from app.bot.states import DisplayRecords
from app.laundry_repo import get_user_by_vk_id, get_user_bookings
from app.bot.utils.fsm import set_state

records_labeler = BotLabeler()


@records_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "show_records"}))
async def show_records(event: MessageEvent):
    peer_id = event.peer_id
    lang, t = await get_lang_and_texts(peer_id)
    await set_state(peer_id, DisplayRecords.waiting_for_display)
    user = await get_user_by_vk_id(event.user_id)

    if not user:
        await event.show_snackbar(t["none_user"])
        return

    bookings = await get_user_bookings(user.id)
    back_kb = get_back_to_sections_keyboard(lang)

    if not bookings:
        no_bookings_text = t.get("no_user_bookings", "У вас нет записей.")
        await event.edit_message(no_bookings_text, keyboard=back_kb)
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

        lines.append(f"• {start_str} - {end_str} • {machine_label} №{machine_num} ({machine_type})")

    title = t.get("show_records_title", "Ваши записи:")
    text = title + "\n\n" + "\n".join(lines)

    await event.edit_message(text, keyboard=back_kb)
