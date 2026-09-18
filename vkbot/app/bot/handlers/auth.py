from vkbottle import GroupEventType, OrRule
from vkbottle.bot import BotLabeler, Message, MessageEvent
from vkbottle.dispatch.rules.base import PayloadContainsRule, VBMLRule, StateRule

from app.bot.keyboards import get_lang_keyboard, get_section_keyboard
from app.bot.loader import api
from app.laundry_repo import (
    get_user_by_vk_id,
    find_resident_by_fio,
    find_resident_by_id_card,
    activate_resident_user,
    update_user_language,
)
from app.bot.states import Auth
from app.bot.utils.translate import get_lang_and_texts, ALL_TEXTS
from app.bot.utils.fsm import set_state, clear_state, update_state_data, get_state_data

auth_labeler = BotLabeler()


async def _send_main_menu(peer_id: int, user, lang: str, t: dict, edit_event: MessageEvent | None = None):
    text = t["hello_user"].format(name=user.first_name)
    kb = get_section_keyboard(lang)
    if edit_event is not None:
        await edit_event.edit_message(text, keyboard=kb)
    else:
        await api.messages.send(peer_id=peer_id, message=text, random_id=0, keyboard=kb)


@auth_labeler.message(
    OrRule(
        PayloadContainsRule({"command": "start"}),
        VBMLRule(["начать", "Начать", "/start", "start", "Start"]),
    )
)
async def cmd_start_initial(message: Message):
    """
    Точка входа. VK сам отправляет payload {"command": "start"}, если в
    настройках сообщества включена кнопка "Начать" — плюс подстраховка
    на случай, если пользователь просто напишет "начать"/"start" вручную.
    """
    peer_id = message.peer_id
    user_id = message.from_id
    user = await get_user_by_vk_id(user_id)
    
    # Если пользователь уже зарегистрирован и у него есть язык в БД:
    if user and user.language:
        lang = user.language.upper()
        if lang not in ALL_TEXTS:
            lang = "RU"
        await update_state_data(peer_id, lang=lang)
        t = ALL_TEXTS[lang]
        await _send_main_menu(peer_id, user, lang, t)
        return

    data = await get_state_data(peer_id)
    if not isinstance(data, dict) or "lang" not in data:
        await message.answer(ALL_TEXTS["RU"]["welcome_lang_choice"], keyboard=get_lang_keyboard())
    else:
        await _cmd_start_auth(peer_id, message.from_id)


async def _cmd_start_auth(peer_id: int, user_id: int):
    existing_user = await get_user_by_vk_id(user_id)
    lang, t = await get_lang_and_texts(peer_id, user_id=user_id, user=existing_user)

    if existing_user:
        if existing_user.language != lang:
            await update_user_language(user_id, lang)
        await _send_main_menu(peer_id, existing_user, lang, t)
    else:
        await api.messages.send(peer_id=peer_id, message=t["auth"], random_id=0)
        await set_state(peer_id, Auth.waiting_for_fio)


@auth_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "lang"}))
async def set_language(event: MessageEvent):
    raw_lang = event.payload.get("lang", "RU")
    lang = str(raw_lang).strip().upper()
    if lang not in ALL_TEXTS:
        lang = "RU"
    await update_state_data(event.peer_id, lang=lang)

    t = ALL_TEXTS[lang]
    user = await get_user_by_vk_id(event.user_id)

    if user:
        await update_user_language(event.user_id, lang)
        await _send_main_menu(event.peer_id, user, lang, t, edit_event=event)
    else:
        await event.edit_message(t["auth"])
        await set_state(event.peer_id, Auth.waiting_for_fio)
        await update_state_data(event.peer_id, lang=lang)


@auth_labeler.message(StateRule(Auth.waiting_for_fio))
async def process_fio_auth(message: Message):
    peer_id = message.peer_id
    lang, t = await get_lang_and_texts(peer_id)
    text = (message.text or "").strip()
    parts = text.split()
    if len(parts) < 2:
        await message.answer(t["write_FIO"])
        return

    resident = await find_resident_by_fio(parts)
    if resident:
        if resident.vk_id and resident.vk_id != message.from_id:
            await message.answer(t["other_tg_id"])
            return

        await activate_resident_user(resident.id, message.from_id, language=lang)
        await message.answer(
            t["hello_user"].format(name=resident.first_name),
            keyboard=get_section_keyboard(lang),
        )
        await clear_state(peer_id)
        await update_state_data(peer_id, lang=lang)
    else:
        await message.answer(t["seek_cards"])
        await set_state(peer_id, Auth.waiting_for_id_card)


@auth_labeler.message(StateRule(Auth.waiting_for_id_card))
async def process_id_card_auth(message: Message):
    peer_id = message.peer_id
    lang, t = await get_lang_and_texts(peer_id)
    text = (message.text or "").strip()
    if not text.isdigit():
        await message.answer(t["reg_id_error"])
        return

    id_card_num = int(text)
    resident = await find_resident_by_id_card(id_card_num)

    if resident:
        if resident.vk_id and resident.vk_id != message.from_id:
            await message.answer(t["other_tg_id"])
            return

        await activate_resident_user(resident.id, message.from_id, language=lang)
        await message.answer(
            t["hello_user"].format(name=resident.first_name),
            keyboard=get_section_keyboard(lang),
        )
        await clear_state(peer_id)
        await update_state_data(peer_id, lang=lang)
    else:
        await message.answer(t["none_user"])


@auth_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "change_language"}))
async def process_change_language_btn(event: MessageEvent):
    """Кнопка 'Сменить язык' из главного меню (не привязана к конкретному состоянию)."""
    await event.edit_message(ALL_TEXTS["RU"]["welcome_lang_choice"], keyboard=get_lang_keyboard())


@auth_labeler.message(
    VBMLRule(["сайт", "Сайт", "/site", "site", "/web", "web", "панель", "Панель", "/panel"])
)
async def cmd_open_site(message: Message):
    lang, t = await get_lang_and_texts(message.peer_id)
    url = "http://webpanel.beget.tech"
    btn_text = t.get("web_panel", "🌐 Перейти на сайт")
    from vkbottle import Keyboard, OpenLink
    kb = Keyboard(inline=True).add(OpenLink(url, btn_text)).get_json()
    await message.answer(f"🌐 {btn_text}:\n{url}", keyboard=kb)
