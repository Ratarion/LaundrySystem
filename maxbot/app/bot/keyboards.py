import json
from datetime import datetime, timedelta
from typing import List

from aiomax.buttons import KeyboardBuilder, CallbackButton, LinkButton

from app.locales import ru, en, cn

ALL_TEXTS = {**ru.RUtexts, **en.ENtexts, **cn.CNtexts}


def get_texts(lang: str) -> dict:
    return ALL_TEXTS.get(lang, ALL_TEXTS["RU"])


_t = get_texts


def _payload(cmd: str, **kwargs) -> str:
    data = {"cmd": cmd, **kwargs}
    return json.dumps(data, ensure_ascii=False)


def get_lang_keyboard() -> KeyboardBuilder:
    kb = KeyboardBuilder()
    kb.row(
        CallbackButton("🇷🇺 Русский", _payload("lang", lang="RU"), intent="positive"),
        CallbackButton("🇬🇧 English", _payload("lang", lang="ENG"), intent="default"),
        CallbackButton("🇨🇳 中文", _payload("lang", lang="CN"), intent="default")
    )
    return kb


def get_section_keyboard(lang: str) -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()
    kb.row(CallbackButton(t["record_laundry"], _payload("record"), intent="positive"))
    kb.row(CallbackButton(t["show_records"], _payload("show_records"), intent="default"))
    kb.row(CallbackButton(t["cancel_record"], _payload("remove_records"), intent="default"))
    kb.row(LinkButton(t.get("web_panel", "🌐 Перейти на сайт"), "http://webpanel.beget.tech"))
    kb.row(CallbackButton(t["change_language"], _payload("change_language"), intent="default"))
    kb.row(CallbackButton(t["report_in_admin"], _payload("report"), intent="negative"))
    return kb


def get_machine_type_keyboard(lang: str) -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()
    kb.row(CallbackButton(t["machine_type_wash"], _payload("type", type="WASH"), intent="positive"))
    kb.row(CallbackButton(t["machine_type_dry"], _payload("type", type="DRY"), intent="positive"))
    kb.row(CallbackButton(t["back"], _payload("back_to_sections"), intent="default"))
    return kb


def get_exit_keyboard(lang: str) -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()
    kb.row(CallbackButton(t.get("exit", "Выход"), _payload("exit"), intent="default"))
    return kb


def get_slot_freed_keyboard(machine_id: int, start_iso: str, lang: str) -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()
    btn_text = t.get("quick_book", "⚡ Быстрая запись")
    kb.row(CallbackButton(btn_text, _payload("quick_book", m_id=machine_id, start=start_iso), intent="positive"))
    return kb


def get_machines_keyboard(available_machines: list, lang: str) -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()
    machines = available_machines[:9]
    for m in machines:
        label = f"№{m.number_machine} ({t['machine_type']}: {m.type_machine})"
        kb.row(CallbackButton(label[:40], _payload("machine", id=m.id), intent="positive"))
    kb.row(CallbackButton(t["back"], _payload("back_to_time"), intent="default"))
    return kb


TIME_SLOTS_PAGE_SIZE = 6


def get_time_slots_keyboard(date: datetime, slots: List[datetime], lang: str, offset: int = 0) -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()
    duration = timedelta(minutes=90)

    page = slots[offset:offset + TIME_SLOTS_PAGE_SIZE]
    for i in range(0, len(page), 2):
        row_buttons = []
        for slot in page[i:i + 2]:
            end_time = slot + duration
            label = f"{slot.strftime('%H:%M')}-{end_time.strftime('%H:%M')}"
            row_buttons.append(
                CallbackButton(label, _payload("time", start=slot.strftime("%Y-%m-%dT%H:%M:%S")), intent="positive")
            )
        kb.row(*row_buttons)

    # Навигация
    nav_buttons = []
    if offset > 0:
        prev_offset = max(0, offset - TIME_SLOTS_PAGE_SIZE)
        nav_buttons.append(CallbackButton("◀", _payload("time_page", offset=prev_offset), intent="default"))
    if offset + TIME_SLOTS_PAGE_SIZE < len(slots):
        next_offset = offset + TIME_SLOTS_PAGE_SIZE
        nav_buttons.append(CallbackButton("▶", _payload("time_page", offset=next_offset), intent="default"))

    if nav_buttons:
        kb.row(*nav_buttons)

    kb.row(CallbackButton(t["back"], _payload("back_to_calendar"), intent="default"))
    return kb


def get_back_to_sections_keyboard(lang: str) -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()
    kb.row(CallbackButton(t.get("back", "Назад"), _payload("back_to_sections"), intent="default"))
    return kb


def get_cancel_booking_keyboard(bookings: list, lang: str) -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()

    for b in bookings[:5]:
        date_str = b.start_time.strftime("%d.%m")
        start_time_str = b.start_time.strftime("%H:%M")
        end_time_str = b.end_time.strftime("%H:%M")

        m_db_type = str(b.machine.type_machine) if getattr(b, "machine", None) else ""
        m_type_key = "machine_type_wash" if m_db_type == "Стиральная" else "machine_type_dry"
        m_type = t.get(m_type_key, "Машина")
        m_num = b.machine.number_machine if getattr(b, "machine", None) else "?"

        btn_text = f"❌ {date_str} {start_time_str}-{end_time_str} | {m_type} №{m_num}"
        kb.row(CallbackButton(btn_text[:40], _payload("cancel", id=b.id), intent="negative"))

    kb.row(CallbackButton(t["back"], _payload("back_to_sections"), intent="default"))
    return kb


def get_confirm_keyboard(booking_id: int, lang: str) -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()
    kb.row(CallbackButton(t.get("confirm_btn", "✅ Я приду"), _payload("confirm", id=booking_id), intent="positive"))
    return kb
