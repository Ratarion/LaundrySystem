from aiogram.types import (InlineKeyboardMarkup,
                           InlineKeyboardButton)
from datetime import datetime, timedelta
from app.locales import ru, en, cn 

# Объединяем словари локализации
ALL_TEXTS = {**ru.RUtexts, **en.ENtexts, **cn.CNtexts} 

kb_welcom = InlineKeyboardMarkup(inline_keyboard=[
    [
        InlineKeyboardButton(text='RU', callback_data='lang_RU'),
        InlineKeyboardButton(text="ENG", callback_data='lang_ENG'), 
        InlineKeyboardButton(text='CN', callback_data='lang_CN')
    ]
])

def get_language_keyboard(lang: str = "RU", is_settings: bool = True) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    buttons = [
        [
            InlineKeyboardButton(text='RU', callback_data='lang_RU'),
            InlineKeyboardButton(text="ENG", callback_data='lang_ENG'), 
            InlineKeyboardButton(text='CN', callback_data='lang_CN')
        ]
    ]
    if is_settings:
        buttons.append([InlineKeyboardButton(text=t["back"], callback_data="settings_menu")])
    return InlineKeyboardMarkup(inline_keyboard=buttons)

# Клавиатура для выбора типа машины: стирка или сушка.
def get_machine_type_keyboard(lang: str) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    return InlineKeyboardMarkup(
        inline_keyboard=[
            # callback_data: type_WASH (стирка) или type_DRY (сушка)
            [InlineKeyboardButton(text=t["machine_type_wash"], callback_data="type_WASH")],
            [InlineKeyboardButton(text=t["machine_type_dry"], callback_data="type_DRY")],
            [InlineKeyboardButton(text=t["back"], callback_data="back_to_sections")] # Назад в главное меню
        ]
    )

def get_section_keyboard(lang: str) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [InlineKeyboardButton(text=t["record_laundry"], callback_data="record")],
            [InlineKeyboardButton(text=t["show_records"], callback_data="show_records")],
            [InlineKeyboardButton(text=t["cancel_record"], callback_data="remove_records")],
            [InlineKeyboardButton(text=t.get("web_panel", "🌐 Перейти на сайт"), url="http://webpanel.beget.tech")],
            [InlineKeyboardButton(text=t.get("settings", "⚙️ Настройки"), callback_data="settings_menu")],
            [InlineKeyboardButton(text=t["report_in_admin"], callback_data="report")],
        ]
    )

def get_settings_keyboard(lang: str) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [InlineKeyboardButton(text=t.get("notifications_menu", "🔔 Уведомления"), callback_data="notifications_menu")],
            [InlineKeyboardButton(text=t.get("change_language", "🌐 Сменить язык"), callback_data="change_language")],
            [InlineKeyboardButton(text=t["back"], callback_data="back_to_sections")]
        ]
    )

def get_notifications_keyboard(is_enabled: bool, lang: str) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    toggle_text = t["disable_notifications_btn"] if is_enabled else t["enable_notifications_btn"]
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [InlineKeyboardButton(text=toggle_text, callback_data="toggle_notifications")],
            [InlineKeyboardButton(text=t["back"], callback_data="settings_menu")]
        ]
    )

def get_exit_keyboard(lang: str) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    return InlineKeyboardMarkup(inline_keyboard=[[InlineKeyboardButton(text=t["exit"], callback_data="exit")]])



def get_confirm_keyboard(booking_id: int, lang: str) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    return InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text=t.get("confirm_btn", "✅ Я приду"), callback_data=f"confirm_{booking_id}"),
            InlineKeyboardButton(text=t.get("decline_btn", "❌ Я не приду"), callback_data=f"decline_{booking_id}")
        ],
        [
            InlineKeyboardButton(text=t.get("main_menu_btn", "🏠 Главное меню"), callback_data="to_main_menu")
        ]
    ])


def get_confirmed_keyboard(booking_id: int, lang: str) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    return InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text=t.get("main_menu_btn", "🏠 Главное меню"), callback_data="to_main_menu"),
            InlineKeyboardButton(text=t.get("decline_btn", "❌ Я не приду"), callback_data=f"decline_{booking_id}")
        ]
    ])


def get_declined_keyboard(lang: str) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    return InlineKeyboardMarkup(inline_keyboard=[
        [
            InlineKeyboardButton(text=t.get("main_menu_btn", "🏠 Главное меню"), callback_data="to_main_menu")
        ]
    ])


def get_slot_freed_keyboard(machine_id: int, start_iso: str, lang: str) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    btn_text = t.get("quick_book", "⚡ Быстрая запись")
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text=btn_text, callback_data=f"qb_{machine_id}_{start_iso}")]
    ])


def get_machines_keyboard(available_machines: list, lang: str) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    buttons = [
        [InlineKeyboardButton(
            text=f"№{m.number_machine} ({t['machine_type']}: {m.type_machine})",
            callback_data=f"machine_{m.id}"
        )]
        for m in available_machines
    ]
    buttons.append([InlineKeyboardButton(text=t["back"], callback_data="back_to_time")])
    return InlineKeyboardMarkup(inline_keyboard=buttons)

#Вывод промежутка времени 8:00-9:30
def get_time_slots_keyboard(date: datetime, slots: list[datetime], lang: str) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    buttons = []

    DURATION = timedelta(minutes=90) # Длительность одной записи - 90 минут

    for slot in slots:
        start_time = slot
        end_time = start_time + DURATION

        # Формат времени: "08:00 - 09:30"
        text = f"{start_time.strftime('%H:%M')} - {end_time.strftime('%H:%M')}"

        # Используем новый формат callback_data
        callback = f"time_{date.year}_{date.month}_{date.day}_{slot.hour}_{slot.minute}"
        buttons.append([InlineKeyboardButton(text=text, callback_data=callback)])

    buttons.append([InlineKeyboardButton(text=t["back"], callback_data="back_to_calendar")])
    buttons.append([InlineKeyboardButton(text=t["exit"], callback_data="exit")])
    return InlineKeyboardMarkup(inline_keyboard=buttons)

def get_back_to_sections_keyboard(lang: str) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text=t.get("back", "Назад"), callback_data="back_to_sections")]
    ])

def get_cancel_booking_keyboard(bookings: list, lang: str) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    buttons = []
    
    for b in bookings:
        # Формируем текст кнопки: 21.12 14:00-15:30 | Стиральная №1
        date_str = b.start_time.strftime("%d.%m")
        start_time_str = b.start_time.strftime("%H:%M")
        end_time_str = b.end_time.strftime("%H:%M")
        
        # Определяем тип машины локализованно
        m_db_type = str(b.machine.type_machine)
        if m_db_type == "Стиральная":
            m_type_key = "machine_type_wash"
        else:
            m_type_key = "machine_type_dry"
        m_type = t.get(m_type_key, "Машина")
        
        btn_text = f"❌ {date_str} {start_time_str}-{end_time_str} | {m_type} №{b.machine.number_machine}"
        
        # callback_data содержит ID брони
        buttons.append([InlineKeyboardButton(text=btn_text, callback_data=f"cancel_id_{b.id}")])
    
    # Кнопка Назад
    buttons.append([InlineKeyboardButton(text=t["back"], callback_data="back_to_sections")])
    
    return InlineKeyboardMarkup(inline_keyboard=buttons)


def get_notification_keyboard(lang: str) -> InlineKeyboardMarkup:
    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    return InlineKeyboardMarkup(inline_keyboard=[
        [InlineKeyboardButton(text=t["back_to_main"], callback_data="show_main_menu")]
    ])