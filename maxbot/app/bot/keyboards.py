import json
from datetime import datetime, timedelta
from typing import List

from aiomax.buttons import KeyboardBuilder, CallbackButton, LinkButton, MessageButton

from app.locales import ru, en, cn

ALL_TEXTS = {**ru.RUtexts, **en.ENtexts, **cn.CNtexts}


def get_texts(lang: str) -> dict:
    return ALL_TEXTS.get(lang, ALL_TEXTS["RU"])


_t = get_texts


def _payload(cmd: str, **kwargs) -> str:
    data = {"cmd": cmd, **kwargs}
    return json.dumps(data, ensure_ascii=False)


def get_start_keyboard() -> KeyboardBuilder:
    """Начальные кнопки при старте MAX-бота"""
    kb = KeyboardBuilder()
    kb.row(
        MessageButton("🚀 Начать"),
        MessageButton("/start")
    )
    return kb


def get_main_reply_keyboard(lang: str = "RU") -> KeyboardBuilder:
    """Быстрые кнопки в MAX-боте"""
    t = _t(lang)
    kb = KeyboardBuilder()
    kb.row(
        MessageButton(t.get("reply_btn_menu", "🏠 Главное меню"))
    )
    return kb


def get_lang_keyboard(is_settings: bool = False, lang: str = "RU") -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()
    kb.row(
        CallbackButton("🇷🇺 Русский", _payload("lang", lang="RU"), intent="positive"),
        CallbackButton("🇬🇧 English", _payload("lang", lang="ENG"), intent="default"),
        CallbackButton("🇨🇳 中文", _payload("lang", lang="CN"), intent="default")
    )
    if is_settings:
        kb.row(CallbackButton(t["back"], _payload("settings_menu"), intent="default"))
    return kb


def get_section_keyboard(lang: str) -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()
    kb.row(
        CallbackButton(t["record_laundry"], _payload("record"), intent="positive"),
        CallbackButton(t["show_records"], _payload("show_records"), intent="default")
    )
    kb.row(CallbackButton(t["cancel_record"], _payload("remove_records"), intent="default"))
    kb.row(
        CallbackButton(t.get("settings", "⚙️ Настройки"), _payload("settings_menu"), intent="default"),
        CallbackButton(t.get("info_btn", "ℹ️ Информация"), _payload("info_menu"), intent="positive")
    )
    return kb


def get_info_keyboard(lang: str, resident_id: int | None = None) -> KeyboardBuilder:
    t = _t(lang)
    url = f"http://webpanel.beget.tech/hall-of-fame?me={resident_id}" if resident_id else "http://webpanel.beget.tech/hall-of-fame"
    kb = KeyboardBuilder()
    kb.row(CallbackButton(t.get("discipline_rating_btn", "⭐️ Мой рейтинг"), _payload("show_rating"), intent="positive"))
    kb.row(LinkButton(t.get("hall_of_fame_btn", "🏆 Зал славы"), url))
    kb.row(LinkButton(t.get("web_panel", "🌐 Перейти на сайт"), "http://webpanel.beget.tech"))
    kb.row(LinkButton(t.get("vk_community_btn", "🧺 Стирка КузГТУ (ВК)"), "https://vk.ru/kuzstu_stirka"))
    kb.row(CallbackButton(t["back"], _payload("back_to_sections"), intent="default"))
    return kb


def get_rating_keyboard(lang: str, resident_id: int | None = None) -> KeyboardBuilder:
    t = _t(lang)
    url = f"http://webpanel.beget.tech/hall-of-fame?me={resident_id}" if resident_id else "http://webpanel.beget.tech/hall-of-fame"
    kb = KeyboardBuilder()
    kb.row(LinkButton(t.get("hall_of_fame_btn", "🏆 Зал славы"), url))
    kb.row(CallbackButton(t["back"], _payload("info_menu"), intent="default"))
    return kb


def get_settings_keyboard(lang: str) -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()
    kb.row(CallbackButton(t.get("notifications_menu", "🔔 Уведомления"), _payload("notifications_menu"), intent="default"))
    kb.row(CallbackButton(t.get("change_language", "🌐 Сменить язык"), _payload("change_language"), intent="default"))
    kb.row(CallbackButton(t.get("report_in_admin", "🛠️ Сообщить о проблеме"), _payload("report"), intent="negative"))
    kb.row(CallbackButton(t["back"], _payload("back_to_sections"), intent="default"))
    return kb


def get_notifications_keyboard(is_enabled: bool, lang: str) -> KeyboardBuilder:
    t = _t(lang)
    toggle_text = t["disable_notifications_btn"] if is_enabled else t["enable_notifications_btn"]
    toggle_intent = "negative" if is_enabled else "positive"
    kb = KeyboardBuilder()
    kb.row(CallbackButton(toggle_text, _payload("toggle_notifications"), intent=toggle_intent))
    kb.row(CallbackButton(t["back"], _payload("settings_menu"), intent="default"))
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


def get_back_to_settings_keyboard(lang: str) -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()
    kb.row(CallbackButton(t.get("back", "Назад"), _payload("settings_menu"), intent="default"))
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
    kb.row(CallbackButton(t.get("decline_btn", "❌ Я не приду"), _payload("decline", id=booking_id), intent="negative"))
    kb.row(CallbackButton(t.get("main_menu_btn", "🏠 Главное меню"), _payload("to_main_menu"), intent="default"))
    return kb


def get_confirmed_keyboard(booking_id: int, lang: str) -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()
    kb.row(CallbackButton(t.get("main_menu_btn", "🏠 Главное меню"), _payload("to_main_menu"), intent="default"))
    kb.row(CallbackButton(t.get("decline_btn", "❌ Я не приду"), _payload("decline", id=booking_id), intent="negative"))
    return kb


def get_declined_keyboard(lang: str) -> KeyboardBuilder:
    t = _t(lang)
    kb = KeyboardBuilder()
    kb.row(CallbackButton(t.get("main_menu_btn", "🏠 Главное меню"), _payload("to_main_menu"), intent="default"))
    return kb

