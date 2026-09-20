from aiogram import Router, F
from aiogram.filters import CommandStart, Command
from aiogram.types import Message, CallbackQuery, InlineKeyboardMarkup, InlineKeyboardButton
from aiogram.fsm.context import FSMContext

from app.bot.keyboards import (
    kb_welcom,
    get_section_keyboard,
    get_notifications_keyboard,
    get_settings_keyboard,
    get_language_keyboard,
    get_start_reply_keyboard,
    get_main_reply_keyboard,
)
from app.repositories.laundry_repo import (
    get_user_by_tg_id,
    find_resident_by_fio,
    find_resident_by_id_card,
    activate_resident_user,
    update_user_language,
    get_user_notify_status_by_tg,
    toggle_user_notify_by_tg
)
from app.bot.states import Auth
from app.bot.utils.translate import get_lang_and_texts, ALL_TEXTS

auth_router = Router()

async def send_main_menu(message_or_cb, user, lang: str, t: dict):
    name = user.first_name if hasattr(user, 'first_name') and user.first_name else ""
    target = message_or_cb.message if isinstance(message_or_cb, CallbackQuery) else message_or_cb
    await target.answer(
        t.get("quick_access_menu_hint", "Меню быстрого доступа активировано ⬇️"),
        reply_markup=get_main_reply_keyboard(lang)
    )
    score = getattr(user, "score", 100) if getattr(user, "score", None) is not None else 100
    streak = getattr(user, "confirm_streak", 0) if getattr(user, "confirm_streak", None) is not None else 0
    from app.services.discipline_service import get_rank_info
    rank_info = get_rank_info(score, streak)
    rank_name = rank_info.get(lang, rank_info.get("RU", "Дисциплинированный"))
    badge = rank_info.get("badge", "🟢")

    greeting = t['hello_user'].replace('{name}', name)
    rating_line = f"⭐️ <b>{score} баллов</b> ({badge} {rank_name}) | Серия: 🔥 {streak}" if lang == "RU" else (
        f"⭐️ <b>{score} pts</b> ({badge} {rank_name}) | Streak: 🔥 {streak}" if lang == "ENG" else
        f"⭐️ <b>{score} 积分</b> ({badge} {rank_name}) | 连击: 🔥 {streak}"
    )
    full_text = f"{greeting}\n{rating_line}"

    await target.answer(
        full_text,
        reply_markup=get_section_keyboard(lang),
        parse_mode="HTML"
    )

@auth_router.message(CommandStart())
@auth_router.message(F.text.in_({
    "🚀 Начать", "Начать", "начать", "start", "/start", "старт", "Старт", "🚀 Start"
}))
async def cmd_start_initial(message: Message, state: FSMContext):
    tg_id = message.from_user.id
    user = await get_user_by_tg_id(tg_id)
    
    # Если пользователь уже зарегистрирован и у него есть язык в БД:
    if user and user.language:
        lang = user.language.upper()
        if lang not in ALL_TEXTS:
            lang = "RU"
        await state.update_data(lang=lang)
        t = ALL_TEXTS[lang]
        await send_main_menu(message, user, lang, t)
        return

    data = await state.get_data()
    # Если язык еще не выбран, предлагаем выбрать
    if 'lang' not in data:
        await message.answer(
            "Нажмите «🚀 Начать» или выберите язык для продолжения:",
            reply_markup=get_start_reply_keyboard()
        )
        await message.answer(
            ALL_TEXTS["RU"]["welcome_lang_choice"],
            reply_markup=kb_welcom
        )
    else:
        await cmd_start_auth(message, state)

@auth_router.callback_query(F.data.startswith("lang_"))
async def set_language(callback: CallbackQuery, state: FSMContext):
    # 1. Получаем выбранный язык
    lang = callback.data.split("_")[1].upper()
    if lang not in ALL_TEXTS:
        lang = "RU"
    
    # 2. Обновляем состояние
    await state.update_data(lang=lang)
    
    # 3. Получаем тексты для выбранного языка
    t = ALL_TEXTS[lang]
    
    # 4. Проверяем, кто нажал кнопку (пользователь)
    tg_id = callback.from_user.id
    user = await get_user_by_tg_id(tg_id)

    # Удаляем сообщение с выбором языка, чтобы не засорять чат
    await callback.message.delete()

    if user:
        # --- ЕСЛИ ПОЛЬЗОВАТЕЛЬ УЖЕ ЕСТЬ В БАЗЕ ---
        # Обновляем язык в базе данных
        await update_user_language(tg_id, lang)
        await send_main_menu(callback, user, lang, t)
    else:
        # --- ЕСЛИ ЭТО НОВЫЙ ПОЛЬЗОВАТЕЛЬ (регистрация) ---
        # Запускаем процедуру авторизации
        await callback.message.answer(t["auth"])
        await state.set_state(Auth.waiting_for_fio)
    
    await callback.answer()

async def cmd_start_auth(message: Message, state: FSMContext):
    tg_id = message.from_user.id
    existing_user = await get_user_by_tg_id(tg_id)
    lang, t = await get_lang_and_texts(state)

    if existing_user:
        if existing_user.language != lang:
             await update_user_language(tg_id, lang)
        await send_main_menu(message, existing_user, lang, t)
    else:
        await message.answer(t["auth"])
        await state.set_state(Auth.waiting_for_fio)

@auth_router.message(Auth.waiting_for_fio)
async def process_fio_auth(message: Message, state: FSMContext):
    lang, t = await get_lang_and_texts(state) # Получаем язык из FSM
    text = message.text.strip()
    parts = text.split()
    if len(parts) < 2:
        await message.answer(t["write_FIO"])
        return
    
    resident = await find_resident_by_fio(parts)
    if resident:
        if resident.tg_id and resident.tg_id != message.from_user.id:
            await message.answer(t["other_tg_id"])
            return
            
        # ✅ ПЕРЕДАЕМ ЯЗЫК В БАЗУ
        await activate_resident_user(resident.id, message.from_user.id, language=lang)
        await state.clear()
        await state.update_data(lang=lang)
        await send_main_menu(message, resident, lang, t)
    else:
        await message.answer(t["seek_cards"])
        await state.set_state(Auth.waiting_for_id_card)

@auth_router.message(Auth.waiting_for_id_card)
async def process_id_card_auth(message: Message, state: FSMContext):
    lang, t = await get_lang_and_texts(state)
    id_card_input = (message.text or "").strip()
    if not id_card_input or len(id_card_input) < 2:
        await message.answer(t["reg_id_error"])
        return
        
    resident = await find_resident_by_id_card(id_card_input)
    
    if resident:
        if resident.tg_id and resident.tg_id != message.from_user.id:
            await message.answer(t["other_tg_id"])
            return
            
        # ✅ ПЕРЕДАЕМ ЯЗЫК В БАЗУ
        await activate_resident_user(resident.id, message.from_user.id, language=lang)
        await state.clear()
        await state.update_data(lang=lang)
        await send_main_menu(message, resident, lang, t)
    else:
        await message.answer(t["none_user"])


# --- Обработчики кнопок нижней панели (Reply Keyboard) и команд ---
@auth_router.message(Command("menu"))
@auth_router.message(F.text.in_({
    "🏠 Главное меню", "🏠 Main Menu", "🏠 主菜单",
    "/menu", "главное меню", "Главное меню"
}))
async def reply_show_main_menu(message: Message, state: FSMContext):
    await state.clear()
    lang, t = await get_lang_and_texts(state, tg_id=message.from_user.id)
    await state.update_data(lang=lang)
    user = await get_user_by_tg_id(message.from_user.id)
    if user:
        await send_main_menu(message, user, lang, t)
    else:
        await cmd_start_initial(message, state)


@auth_router.message(Command("settings"))
@auth_router.message(F.text.in_({
    "⚙️ Настройки", "⚙️ Settings", "⚙️ 设置",
    "/settings", "настройки", "Настройки"
}))
async def reply_show_settings(message: Message, state: FSMContext):
    await state.clear()
    lang, t = await get_lang_and_texts(state, tg_id=message.from_user.id)
    await state.update_data(lang=lang)
    text = t.get("settings_title", "⚙️ <b>Настройки</b>\n\nВыберите нужный раздел:")
    await message.answer(
        text,
        parse_mode="HTML",
        reply_markup=get_settings_keyboard(lang)
    )


@auth_router.callback_query(F.data == "settings_menu")
async def process_settings_menu(callback: CallbackQuery, state: FSMContext):
    """
    Меню настроек: уведомления, смена языка и сообщить о проблеме
    """
    lang, t = await get_lang_and_texts(state, tg_id=callback.from_user.id)
    await state.clear()
    await state.update_data(lang=lang)
    text = t.get("settings_title", "⚙️ <b>Настройки</b>\n\nВыберите нужный раздел:")
    try:
        await callback.message.edit_text(
            text,
            parse_mode="HTML",
            reply_markup=get_settings_keyboard(lang)
        )
    except Exception:
        await callback.message.answer(
            text,
            parse_mode="HTML",
            reply_markup=get_settings_keyboard(lang)
        )
    await callback.answer()


@auth_router.callback_query(F.data == "change_language")
async def process_change_language_btn(callback: CallbackQuery, state: FSMContext):
    """
    Обработчик кнопки 'Сменить язык' из меню настроек.
    Показывает клавиатуру выбора языка с кнопкой возврата назад в настройки.
    """
    lang, t = await get_lang_and_texts(state, tg_id=callback.from_user.id)
    try:
        await callback.message.edit_text(
            ALL_TEXTS["RU"]["welcome_lang_choice"], 
            reply_markup=get_language_keyboard(lang, is_settings=True)
        )
    except Exception:
        await callback.message.answer(
            ALL_TEXTS["RU"]["welcome_lang_choice"], 
            reply_markup=get_language_keyboard(lang, is_settings=True)
        )
    await callback.answer()


@auth_router.callback_query(F.data == "notifications_menu")
async def process_notifications_menu(callback: CallbackQuery, state: FSMContext):
    lang, t = await get_lang_and_texts(state, tg_id=callback.from_user.id)
    is_enabled = await get_user_notify_status_by_tg(callback.from_user.id)
    status_str = t["status_enabled"] if is_enabled else t["status_disabled"]
    text = t["notifications_settings_title"].format(status=status_str)
    try:
        await callback.message.edit_text(
            text=text,
            parse_mode="HTML",
            reply_markup=get_notifications_keyboard(is_enabled, lang)
        )
    except Exception:
        await callback.message.answer(
            text=text,
            parse_mode="HTML",
            reply_markup=get_notifications_keyboard(is_enabled, lang)
        )
    await callback.answer()


@auth_router.callback_query(F.data == "toggle_notifications")
async def process_toggle_notifications(callback: CallbackQuery, state: FSMContext):
    lang, t = await get_lang_and_texts(state)
    new_status = await toggle_user_notify_by_tg(callback.from_user.id)
    status_str = t["status_enabled"] if new_status else t["status_disabled"]
    text = t["notifications_settings_title"].format(status=status_str)
    alert = t["notifications_toggled_on"] if new_status else t["notifications_toggled_off"]
    await callback.message.edit_text(
        text=text,
        parse_mode="HTML",
        reply_markup=get_notifications_keyboard(new_status, lang)
    )
    await callback.answer(alert, show_alert=False)



@auth_router.message(F.text.lower().in_({"/site", "сайт", "/web", "панель", "/panel"}))
async def cmd_open_site(message: Message, state: FSMContext):
    lang, t = await get_lang_and_texts(state, tg_id=message.from_user.id)
    url = "http://webpanel.beget.tech"
    btn_text = t.get("web_panel", "🌐 Перейти на сайт")
    kb = InlineKeyboardMarkup(inline_keyboard=[[InlineKeyboardButton(text=btn_text, url=url)]])
    await message.answer(f"🌐 <b>{btn_text}</b>:\n{url}", reply_markup=kb, parse_mode="HTML")