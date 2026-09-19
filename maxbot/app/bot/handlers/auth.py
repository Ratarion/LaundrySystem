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
    text = t["hello_user"].format(name=user.first_name)
    kb = get_section_keyboard(lang)
    if cb is not None:
        await cb.answer(text=text, keyboard=kb)
    elif message is not None:
        await message.reply(text=text, keyboard=kb)


@auth_router.on_bot_start()
async def on_bot_start_event(payload: BotStartPayload, cursor: fsm.FSMCursor):
    user_id = payload.user.user_id
    data = cursor.get_data() or {}

    if "lang" not in data:
        await payload.send(ALL_TEXTS["RU"]["welcome_lang_choice"], keyboard=get_lang_keyboard())
    else:
        await _cmd_start_auth(user_id, cursor=cursor)


@auth_router.on_command("start")
async def cmd_start(ctx: CommandContext, cursor: fsm.FSMCursor):
    user_id = ctx.sender.user_id
    data = cursor.get_data() or {}

    if "lang" not in data:
        await ctx.reply(ALL_TEXTS["RU"]["welcome_lang_choice"], keyboard=get_lang_keyboard())
    else:
        await _cmd_start_auth(user_id, cursor=cursor)


async def _cmd_start_auth(user_id: int, cursor: fsm.FSMCursor):
    existing_user = await get_user_by_max_id(user_id)
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)

    if existing_user:
        if existing_user.language != lang:
            await update_user_language(user_id, lang)
        text = t["hello_user"].format(name=existing_user.first_name)
        kb = get_section_keyboard(lang)
        await cursor.storage.bot.send_message(user_id=user_id, text=text, keyboard=kb)
    else:
        await cursor.storage.bot.send_message(user_id=user_id, text=t["auth"])
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

    if not text.isdigit():
        await message.reply(t["reg_id_error"])
        return

    id_card_num = int(text)
    resident = await find_resident_by_id_card(id_card_num)

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
