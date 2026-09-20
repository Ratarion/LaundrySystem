import json
import logging
from typing import Optional

import aiomax
from aiomax import Router, Message, Callback, CommandContext, BotStartPayload, fsm

from app.bot.keyboards import (
    get_lang_keyboard,
    get_section_keyboard,
    get_notifications_keyboard,
    get_settings_keyboard,
    get_start_keyboard,
    get_main_reply_keyboard,
)
from app.laundry_repo import (
    get_user_by_max_id,
    find_resident_by_fio,
    find_resident_by_id_card,
    activate_resident_user,
    update_user_language,
    get_user_notify_status_by_max,
    toggle_user_notify_by_max,
)
from app.bot.states import Auth
from app.bot.utils.translate import get_lang_and_texts, ALL_TEXTS

auth_router = Router()


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


async def _send_main_menu(user_id: int, user, lang: str, t: dict, cb: Optional[Callback] = None, message: Optional[Message] = None):
    text = t['hello_user'].format(name=user.first_name)
    kb = get_section_keyboard(lang)
    if cb is not None:
        await cb.answer(text=text, keyboard=kb)
    elif message is not None:
        await message.reply(text=text, keyboard=kb)


@auth_router.on_bot_start()
async def on_bot_start_event(payload: BotStartPayload, cursor: fsm.FSMCursor):
    user_id = payload.user.user_id
    existing_user = await get_user_by_max_id(user_id)

    if existing_user:
        lang = (existing_user.language or "RU").strip().upper()
        if lang not in ALL_TEXTS:
            lang = "RU"
        data = cursor.get_data() or {}
        data["lang"] = lang
        cursor.change_data(data)
        cursor.clear_state()

        t = ALL_TEXTS[lang]
        text = t["hello_user"].format(name=existing_user.first_name)
        kb = get_section_keyboard(lang)
        await payload.send(text, keyboard=kb)
        return

    data = cursor.get_data() or {}
    if "lang" not in data:
        await payload.send("Нажмите «🚀 Начать» или выберите язык для продолжения:", keyboard=get_start_keyboard())
        await payload.send(ALL_TEXTS["RU"]["welcome_lang_choice"], keyboard=get_lang_keyboard())
    else:
        lang, t = await get_lang_and_texts(user_id, cursor=cursor)
        await payload.send(t["auth"])
        cursor.change_state(Auth.waiting_for_fio)


@auth_router.on_command("start")
async def cmd_start(ctx: CommandContext, cursor: fsm.FSMCursor):
    user_id = ctx.sender.user_id
    existing_user = await get_user_by_max_id(user_id)

    if existing_user:
        lang = (existing_user.language or "RU").strip().upper()
        if lang not in ALL_TEXTS:
            lang = "RU"
        data = cursor.get_data() or {}
        data["lang"] = lang
        cursor.change_data(data)
        cursor.clear_state()

        t = ALL_TEXTS[lang]
        text = t["hello_user"].format(name=existing_user.first_name)
        kb = get_section_keyboard(lang)
        await ctx.reply(text, keyboard=kb)
        return

    data = cursor.get_data() or {}
    if "lang" not in data:
        await ctx.reply("Нажмите «🚀 Начать» или выберите язык для продолжения:", keyboard=get_start_keyboard())
        await ctx.reply(ALL_TEXTS["RU"]["welcome_lang_choice"], keyboard=get_lang_keyboard())
    else:
        lang, t = await get_lang_and_texts(user_id, cursor=cursor)
        await ctx.reply(t["auth"])
        cursor.change_state(Auth.waiting_for_fio)


@auth_router.on_message(lambda msg: (getattr(getattr(msg, "body", None), "text", None) or "").strip().lower() in ["/start", "старт", "start", "начать", "🚀 начать", "🚀 start"])
async def cmd_start_text(message: Message, cursor: fsm.FSMCursor):
    user_id = message.sender.user_id
    existing_user = await get_user_by_max_id(user_id)

    if existing_user:
        lang = (existing_user.language or "RU").strip().upper()
        if lang not in ALL_TEXTS:
            lang = "RU"
        data = cursor.get_data() or {}
        data["lang"] = lang
        cursor.change_data(data)
        cursor.clear_state()

        t = ALL_TEXTS[lang]
        text = t["hello_user"].format(name=existing_user.first_name)
        kb = get_section_keyboard(lang)
        await message.reply(text=text, keyboard=kb)
        return

    data = cursor.get_data() or {}
    if "lang" not in data:
        await message.reply("Нажмите «🚀 Начать» или выберите язык для продолжения:", keyboard=get_start_keyboard())
        await message.reply(ALL_TEXTS["RU"]["welcome_lang_choice"], keyboard=get_lang_keyboard())
    else:
        lang, t = await get_lang_and_texts(user_id, cursor=cursor)
        await message.reply(t["auth"])
        cursor.change_state(Auth.waiting_for_fio)


@auth_router.on_button_callback(lambda cb: _is_cmd(cb, "lang"))
async def set_language(cb: Callback, cursor: fsm.FSMCursor):
    payload = _get_payload(cb)
    lang = payload.get("lang", "RU")
    user_id = cb.user.user_id

    data = cursor.get_data() or {}
    data["lang"] = lang
    cursor.change_data(data)

    t = ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
    user = await get_user_by_max_id(user_id)

    if user:
        await update_user_language(user_id, lang)
        await _send_main_menu(user_id, user, lang, t, cb=cb)
    else:
        await cb.answer(text=t["auth"])
        cursor.change_state(Auth.waiting_for_fio)


@auth_router.on_message(lambda msg: getattr(msg.bot.storage, 'get_state', lambda u: None)(msg.sender.user_id) == Auth.waiting_for_fio)
async def process_fio_auth(message: Message, cursor: fsm.FSMCursor):
    user_id = message.sender.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    text = (message.body.text or "").strip()
    parts = text.split()

    if len(parts) < 2:
        await message.reply(t["write_FIO"])
        return

    resident = await find_resident_by_fio(parts)
    if resident:
        if resident.max_id and resident.max_id != user_id:
            await message.reply(t["other_tg_id"])
            return

        await activate_resident_user(resident.id, user_id, language=lang)
        await message.reply(
            t["hello_user"].format(name=resident.first_name),
            keyboard=get_section_keyboard(lang),
        )
        cursor.clear_state()
    else:
        await message.reply(t["seek_cards"])
        cursor.change_state(Auth.waiting_for_id_card)


@auth_router.on_message(lambda msg: getattr(msg.bot.storage, 'get_state', lambda u: None)(msg.sender.user_id) == Auth.waiting_for_id_card)
async def process_id_card_auth(message: Message, cursor: fsm.FSMCursor):
    user_id = message.sender.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    text = (message.body.text or "").strip()

    if not text or len(text) < 2:
        await message.reply(t["reg_id_error"])
        return

    resident = await find_resident_by_id_card(text)

    if resident:
        if resident.max_id and resident.max_id != user_id:
            await message.reply(t["other_tg_id"])
            return

        await activate_resident_user(resident.id, user_id, language=lang)
        await message.reply(
            t["hello_user"].format(name=resident.first_name),
            keyboard=get_section_keyboard(lang),
        )
        cursor.clear_state()
    else:
        await message.reply(t["none_user"])


@auth_router.on_button_callback(lambda cb: _is_cmd(cb, "settings_menu"))
async def process_settings_menu(cb: Callback, cursor: fsm.FSMCursor):
    cursor.clear_state()
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    text = t.get("settings_title", "⚙️ Настройки:\n\nВыберите нужный раздел:")
    await cb.answer(text=text, keyboard=get_settings_keyboard(lang))


@auth_router.on_button_callback(lambda cb: _is_cmd(cb, "change_language"))
async def process_change_language_btn(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    await cb.answer(text=ALL_TEXTS["RU"]["welcome_lang_choice"], keyboard=get_lang_keyboard(is_settings=True, lang=lang))


@auth_router.on_button_callback(lambda cb: _is_cmd(cb, "notifications_menu"))
async def process_notifications_menu(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    is_enabled = await get_user_notify_status_by_max(user_id)
    status_str = t["status_enabled"] if is_enabled else t["status_disabled"]
    text = t["notifications_settings_title"].format(status=status_str)
    await cb.answer(text=text, keyboard=get_notifications_keyboard(is_enabled, lang))


@auth_router.on_button_callback(lambda cb: _is_cmd(cb, "toggle_notifications"))
async def process_toggle_notifications(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    new_status = await toggle_user_notify_by_max(user_id)
    status_str = t["status_enabled"] if new_status else t["status_disabled"]
    text = t["notifications_settings_title"].format(status=status_str)
    alert = t["notifications_toggled_on"] if new_status else t["notifications_toggled_off"]
    await cb.answer(notification=alert, text=text, keyboard=get_notifications_keyboard(new_status, lang))



@auth_router.on_message(lambda msg: (getattr(getattr(msg, "body", None), "text", None) or "").strip().lower() in ["/site", "сайт", "/web", "панель", "/panel"])
async def cmd_open_site_max(message: Message, cursor: fsm.FSMCursor):
    user_id = message.sender.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    url = "http://webpanel.beget.tech"
    btn_text = t.get("web_panel", "🌐 Перейти на сайт")
    from aiomax.buttons import KeyboardBuilder, LinkButton
    kb = KeyboardBuilder()
    kb.row(LinkButton(btn_text, url))
    await message.reply(text=f"🌐 {btn_text}:\n{url}", keyboard=kb)


# --- Обработчики кнопок быстрого доступа в MAX-боте ---
@auth_router.on_message(lambda msg: (getattr(getattr(msg, "body", None), "text", None) or "").strip().lower() in [
    "🏠 главное меню", "🏠 main menu", "🏠 主菜单", "главное меню", "/menu", "menu"
])
async def max_reply_show_main_menu(message: Message, cursor: fsm.FSMCursor):
    user_id = message.sender.user_id
    user = await get_user_by_max_id(user_id)
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    if user:
        await _send_main_menu(user_id, user, lang, t, message=message)
    else:
        await cmd_start_text(message, cursor)


@auth_router.on_message(lambda msg: (getattr(getattr(msg, "body", None), "text", None) or "").strip().lower() in [
    "🧺 записаться", "🧺 book laundry", "🧺 预约洗衣", "записаться", "/book", "book"
])
async def max_reply_book(message: Message, cursor: fsm.FSMCursor):
    user_id = message.sender.user_id
    user = await get_user_by_max_id(user_id)
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    if not user:
        await message.reply(t["none_user"])
        return
    if getattr(user, "is_banned", False):
        await message.reply(t.get("user_banned_alert", "❌ Ваш аккаунт заблокирован. Запись недоступна."))
        return
    from app.bot.keyboards import get_machine_type_keyboard
    await message.reply(text=t["select_machine_type"], keyboard=get_machine_type_keyboard(lang))


@auth_router.on_message(lambda msg: (getattr(getattr(msg, "body", None), "text", None) or "").strip().lower() in [
    "📋 мои записи", "📋 my bookings", "📋 我的预约", "мои записи", "/records", "records"
])
async def max_reply_records(message: Message, cursor: fsm.FSMCursor):
    user_id = message.sender.user_id
    user = await get_user_by_max_id(user_id)
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    if not user:
        await message.reply(t["none_user"])
        return
    from app.laundry_repo import get_user_bookings
    bookings = await get_user_bookings(user.id)
    from app.bot.keyboards import get_back_to_sections_keyboard
    back_kb = get_back_to_sections_keyboard(lang)
    if not bookings:
        no_bookings_text = t.get("no_user_bookings", "У вас нет записей.")
        await message.reply(text=no_bookings_text, keyboard=back_kb)
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
        dorm_text = f" • Общ. №{b.dormitory_id}" if getattr(b, "dormitory_id", None) else ""
        lines.append(f"• {start_str} - {end_str}{dorm_text} • {machine_label} №{machine_num} ({machine_type})")
    title = t.get("show_records_title", "Ваши записи:")
    text = title + "\n\n" + "\n".join(lines)
    await message.reply(text=text, keyboard=back_kb)


@auth_router.on_message(lambda msg: (getattr(getattr(msg, "body", None), "text", None) or "").strip().lower() in [
    "⚙️ настройки", "⚙️ settings", "⚙️ 设置", "настройки", "/settings", "settings"
])
async def max_reply_settings(message: Message, cursor: fsm.FSMCursor):
    user_id = message.sender.user_id
    user = await get_user_by_max_id(user_id)
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    text = t.get("settings_title", "⚙️ <b>Настройки</b>\n\nВыберите нужный раздел:")
    await message.reply(text=text, keyboard=get_settings_keyboard(lang))
