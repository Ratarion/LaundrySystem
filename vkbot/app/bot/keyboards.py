"""
Клавиатуры VK-бота.

Важное отличие от Telegram: у VK inline-клавиатуры (inline=True) жёсткие
лимиты:
  1) максимум 5 кнопок в строке и максимум 6 строк (форма сетки 5x6);
  2) ОТДЕЛЬНО — максимум 10 кнопок СУММАРНО на всю клавиатуру, даже если
     сетка 5x6 формально позволяет больше (VK API возвращает ошибку 911
     "Keyboard format is invalid: keyboard contains too much buttons").
Оба лимита учтены во всех функциях ниже (особенно в календаре и списке
тайм-слотов, см. calendar_utils.py, где сделана пагинация).

Payload кнопок — обычный JSON-словарь с ключом "cmd", по которому хендлеры
маршрутизируют нажатия (rules.PayloadContainsRule({"cmd": "..."})).
"""
from datetime import datetime, timedelta

from vkbottle import Keyboard, Callback, KeyboardButtonColor, OpenLink

from app.locales import ru, en, cn

ALL_TEXTS = {**ru.RUtexts, **en.ENtexts, **cn.CNtexts}


def get_texts(lang: str) -> dict:
    return ALL_TEXTS.get(lang, ALL_TEXTS["RU"])


# короткий алиас для внутреннего использования в этом файле
_t = get_texts


def get_lang_keyboard(is_settings: bool = False, lang: str = "RU") -> str:
    t = _t(lang)
    kb = (
        Keyboard(inline=True)
        .add(Callback("RU", {"cmd": "lang", "lang": "RU"}), color=KeyboardButtonColor.PRIMARY)
        .add(Callback("ENG", {"cmd": "lang", "lang": "ENG"}), color=KeyboardButtonColor.PRIMARY)
        .add(Callback("CN", {"cmd": "lang", "lang": "CN"}), color=KeyboardButtonColor.PRIMARY)
    )
    if is_settings:
        kb.row().add(Callback(t["back"], {"cmd": "settings_menu"}), color=KeyboardButtonColor.SECONDARY)
    return kb.get_json()


def get_machine_type_keyboard(lang: str) -> str:
    t = _t(lang)
    kb = (
        Keyboard(inline=True)
        .add(Callback(t["machine_type_wash"], {"cmd": "type", "type": "WASH"}), color=KeyboardButtonColor.PRIMARY)
        .row()
        .add(Callback(t["machine_type_dry"], {"cmd": "type", "type": "DRY"}), color=KeyboardButtonColor.PRIMARY)
        .row()
        .add(Callback(t["back"], {"cmd": "back_to_sections"}), color=KeyboardButtonColor.SECONDARY)
    )
    return kb.get_json()


def get_section_keyboard(lang: str) -> str:
    t = _t(lang)
    kb = (
        Keyboard(inline=True)
        .add(Callback(t["record_laundry"], {"cmd": "record"}), color=KeyboardButtonColor.PRIMARY)
        .row()
        .add(Callback(t["show_records"], {"cmd": "show_records"}), color=KeyboardButtonColor.SECONDARY)
        .row()
        .add(Callback(t["cancel_record"], {"cmd": "remove_records"}), color=KeyboardButtonColor.SECONDARY)
        .row()
        .add(OpenLink("http://webpanel.beget.tech", t.get("web_panel", "🌐 Перейти на сайт")))
        .row()
        .add(Callback(t.get("settings", "⚙️ Настройки"), {"cmd": "settings_menu"}), color=KeyboardButtonColor.SECONDARY)
        .row()
        .add(Callback(t["report_in_admin"], {"cmd": "report"}), color=KeyboardButtonColor.NEGATIVE)
    )
    return kb.get_json()


def get_settings_keyboard(lang: str) -> str:
    t = _t(lang)
    kb = (
        Keyboard(inline=True)
        .add(Callback(t.get("notifications_menu", "🔔 Уведомления"), {"cmd": "notifications_menu"}), color=KeyboardButtonColor.PRIMARY)
        .row()
        .add(Callback(t.get("change_language", "🌐 Сменить язык"), {"cmd": "change_language"}), color=KeyboardButtonColor.SECONDARY)
        .row()
        .add(Callback(t["back"], {"cmd": "back_to_sections"}), color=KeyboardButtonColor.SECONDARY)
    )
    return kb.get_json()


def get_notifications_keyboard(is_enabled: bool, lang: str) -> str:
    t = _t(lang)
    toggle_text = t["disable_notifications_btn"] if is_enabled else t["enable_notifications_btn"]
    toggle_color = KeyboardButtonColor.NEGATIVE if is_enabled else KeyboardButtonColor.POSITIVE
    kb = (
        Keyboard(inline=True)
        .add(Callback(toggle_text, {"cmd": "toggle_notifications"}), color=toggle_color)
        .row()
        .add(Callback(t["back"], {"cmd": "settings_menu"}), color=KeyboardButtonColor.SECONDARY)
    )
    return kb.get_json()



def get_exit_keyboard(lang: str) -> str:
    t = _t(lang)
    kb = Keyboard(inline=True).add(Callback(t["exit"], {"cmd": "exit"}), color=KeyboardButtonColor.SECONDARY)
    return kb.get_json()


def get_slot_freed_keyboard(machine_id: int, start_iso: str, lang: str) -> str:
    t = _t(lang)
    btn_text = t.get("quick_book", "⚡ Быстрая запись")
    kb = Keyboard(inline=True).add(
        Callback(btn_text[:40], {"cmd": "quick_book", "m_id": machine_id, "start": start_iso}),
        color=KeyboardButtonColor.POSITIVE
    )
    return kb.get_json()


def get_machines_keyboard(available_machines: list, lang: str) -> str:
    t = _t(lang)
    kb = Keyboard(inline=True)
    # Жёсткий лимит VK: не больше 10 кнопок суммарно на inline-клавиатуре.
    # 9 машин + кнопка "назад" = 10.
    machines = available_machines[:9]
    per_row = 1 if len(machines) <= 4 else 2
    for i, m in enumerate(machines):
        if i > 0 and i % per_row == 0:
            kb.row()
        label = f"№{m.number_machine} ({t['machine_type']}: {m.type_machine})"
        kb.add(Callback(label[:40], {"cmd": "machine", "id": m.id}), color=KeyboardButtonColor.PRIMARY)
    kb.row()
    kb.add(Callback(t["back"], {"cmd": "back_to_time"}), color=KeyboardButtonColor.SECONDARY)
    return kb.get_json()


TIME_SLOTS_PAGE_SIZE = 7  # 7 слотов + 2 кнопки навигации + "назад" = 10 (лимит VK)


def get_time_slots_keyboard(date: datetime, slots: list[datetime], lang: str, offset: int = 0) -> str:
    t = _t(lang)
    kb = Keyboard(inline=True)
    duration = timedelta(minutes=90)

    page = slots[offset:offset + TIME_SLOTS_PAGE_SIZE]
    for i, slot in enumerate(page):
        if i > 0 and i % 5 == 0:
            kb.row()
        end_time = slot + duration
        label = f"{slot.strftime('%H:%M')}-{end_time.strftime('%H:%M')}"
        kb.add(
            Callback(label, {"cmd": "time", "start": slot.strftime("%Y-%m-%dT%H:%M:%S")}),
            color=KeyboardButtonColor.PRIMARY,
        )

    kb.row()
    if offset > 0:
        prev_offset = max(0, offset - TIME_SLOTS_PAGE_SIZE)
        kb.add(Callback("◀", {"cmd": "time_page", "offset": prev_offset}), color=KeyboardButtonColor.SECONDARY)
    if offset + TIME_SLOTS_PAGE_SIZE < len(slots):
        next_offset = offset + TIME_SLOTS_PAGE_SIZE
        kb.add(Callback("▶", {"cmd": "time_page", "offset": next_offset}), color=KeyboardButtonColor.SECONDARY)

    kb.row()
    kb.add(Callback(t["back"], {"cmd": "back_to_calendar"}), color=KeyboardButtonColor.SECONDARY)
    return kb.get_json()


def get_back_to_sections_keyboard(lang: str) -> str:
    t = _t(lang)
    kb = Keyboard(inline=True).add(
        Callback(t.get("back", "Назад"), {"cmd": "back_to_sections"}), color=KeyboardButtonColor.SECONDARY
    )
    return kb.get_json()


def get_cancel_booking_keyboard(bookings: list, lang: str) -> str:
    t = _t(lang)
    kb = Keyboard(inline=True)

    # 5 записей + кнопка "назад" = 6 кнопок — укладывается и в сетку (6 строк),
    # и в общий лимит VK на 10 кнопок для inline-клавиатуры.
    for b in bookings[:5]:
        date_str = b.start_time.strftime("%d.%m")
        start_time_str = b.start_time.strftime("%H:%M")
        end_time_str = b.end_time.strftime("%H:%M")

        m_db_type = str(b.machine.type_machine)
        m_type_key = "machine_type_wash" if m_db_type == "Стиральная" else "machine_type_dry"
        m_type = t.get(m_type_key, "Машина")

        btn_text = f"❌ {date_str} {start_time_str}-{end_time_str} | {m_type} №{b.machine.number_machine}"
        kb.add(Callback(btn_text[:40], {"cmd": "cancel", "id": b.id}), color=KeyboardButtonColor.NEGATIVE)
        kb.row()

    kb.add(Callback(t["back"], {"cmd": "back_to_sections"}), color=KeyboardButtonColor.SECONDARY)
    return kb.get_json()


def get_confirm_keyboard(booking_id: int, lang: str) -> str:
    t = _t(lang)
    kb = (
        Keyboard(inline=True)
        .add(Callback(t.get("confirm_btn", "✅ Я приду"), {"cmd": "confirm", "id": booking_id}), color=KeyboardButtonColor.POSITIVE)
        .add(Callback(t.get("decline_btn", "❌ Я не приду"), {"cmd": "decline", "id": booking_id}), color=KeyboardButtonColor.NEGATIVE)
        .row()
        .add(Callback(t.get("main_menu_btn", "🏠 Главное меню"), {"cmd": "to_main_menu"}), color=KeyboardButtonColor.SECONDARY)
    )
    return kb.get_json()


def get_confirmed_keyboard(booking_id: int, lang: str) -> str:
    t = _t(lang)
    kb = (
        Keyboard(inline=True)
        .add(Callback(t.get("main_menu_btn", "🏠 Главное меню"), {"cmd": "to_main_menu"}), color=KeyboardButtonColor.SECONDARY)
        .add(Callback(t.get("decline_btn", "❌ Я не приду"), {"cmd": "decline", "id": booking_id}), color=KeyboardButtonColor.NEGATIVE)
    )
    return kb.get_json()


def get_declined_keyboard(lang: str) -> str:
    t = _t(lang)
    kb = (
        Keyboard(inline=True)
        .add(Callback(t.get("main_menu_btn", "🏠 Главное меню"), {"cmd": "to_main_menu"}), color=KeyboardButtonColor.SECONDARY)
    )
    return kb.get_json()
