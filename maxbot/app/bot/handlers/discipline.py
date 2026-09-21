import json
import logging
from typing import Optional

from aiomax import Router, Callback, Message, fsm

from app.bot.utils.translate import get_lang_and_texts
from app.bot.keyboards import get_rating_keyboard, get_info_keyboard
from app.laundry_repo import get_user_by_max_id
from app.services.discipline_service import get_resident_discipline_card
from app.bot.utils.text import strip_html

discipline_router = Router()
logger = logging.getLogger(__name__)


def _is_cmd(cb: Callback, cmd_name: str) -> bool:
    try:
        data = json.loads(cb.payload)
        return data.get("cmd") == cmd_name
    except Exception:
        return cb.payload == cmd_name


@discipline_router.on_button_callback(lambda cb: _is_cmd(cb, "info_menu"))
async def show_info_menu_cb(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    user = await get_user_by_max_id(user_id)
    info_text = strip_html(t.get("info_screen_text", "ℹ️ Информация о сервисе «Стирка КузГТУ»"))
    await cb.answer(text=info_text, keyboard=get_info_keyboard(lang, user.id if user else None))


@discipline_router.on_message(lambda msg: (getattr(msg, "text", "") or "").strip() in {
    "ℹ️ Информация", "Информация", "/info", "Info", "ℹ️ Information", "Information", "ℹ️ 信息", "信息"
})
async def show_info_menu_msg(message: Message, cursor: fsm.FSMCursor):
    user_id = message.sender.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    user = await get_user_by_max_id(user_id)
    info_text = strip_html(t.get("info_screen_text", "ℹ️ Информация о сервисе «Стирка КузГТУ»"))
    await message.reply(text=info_text, keyboard=get_info_keyboard(lang, user.id if user else None))


@discipline_router.on_button_callback(lambda cb: _is_cmd(cb, "show_rating"))
async def show_discipline_rating_cb(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    user = await get_user_by_max_id(user_id)

    if not user:
        await cb.answer(notification=t.get("none_user", "Пользователь не найден"))
        return

    card = await get_resident_discipline_card(user.id)
    if not card:
        await cb.answer(notification="Ошибка получения данных рейтинга")
        return

    rank_dict = card.get("rank", {})
    rank_title = rank_dict.get(lang, rank_dict.get("RU", "Дисциплинированный"))
    badge = rank_dict.get("badge", "🟢")

    history_items = card.get("history", [])
    if history_items:
        lines = []
        for h in history_items:
            sign = "+" if h["delta"] > 0 else ""
            dt_str = h["created_at"].strftime("%d.%m %H:%M") if h.get("created_at") else ""
            desc = h.get("details") or h.get("reason", "")
            lines.append(f"• {sign}{h['delta']} ({desc}) {dt_str}")
        history_text = "\n".join(lines)
    else:
        history_text = t.get("discipline_no_history", "История начислений пока пуста.")

    profile_text = strip_html(t.get(
        "discipline_profile_title",
        "⭐️ Рейтинг дисциплины\n\n👤 Житель: {name}\n🏆 Ранг: {badge} {rank}\n📊 Баллы: {score} / 200\n🔥 Серия подтверждений: {confirm_streak}\n⚠️ Серия пропусков: {miss_streak}\n\n📋 Правила:\n• Подтверждение вовремя: +5 баллов (+бонус за серию до +12)\n• Отмена заранее: +2 балла\n• Отказ в окне подтверждения: +1 балл\n• Пропуск без отмены: -15..-40 баллов\n\n🕒 Последние изменения:\n{history}"
    ).format(
        name=card.get("name") or user.first_name or "",
        badge=badge,
        rank=rank_title,
        score=card.get("score", 100),
        confirm_streak=card.get("confirm_streak", 0),
        miss_streak=card.get("miss_streak", 0),
        history=history_text
    ))

    await cb.answer(text=profile_text, keyboard=get_rating_keyboard(lang, user.id))


@discipline_router.on_message(lambda msg: (getattr(msg, "text", "") or "").strip() in {
    "⭐️ Мой рейтинг", "Мой рейтинг", "Рейтинг", "/rating", "⭐️ My Rating", "My Rating", "⭐️ 我的积分", "我的积分"
})
async def show_discipline_rating_msg(message: Message, cursor: fsm.FSMCursor):
    user_id = message.sender.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)
    user = await get_user_by_max_id(user_id)

    if not user:
        await message.reply(text=t.get("none_user", "Пользователь не найден"))
        return

    card = await get_resident_discipline_card(user.id)
    if not card:
        await message.reply(text="Ошибка получения данных рейтинга")
        return

    rank_dict = card.get("rank", {})
    rank_title = rank_dict.get(lang, rank_dict.get("RU", "Дисциплинированный"))
    badge = rank_dict.get("badge", "🟢")

    history_items = card.get("history", [])
    if history_items:
        lines = []
        for h in history_items:
            sign = "+" if h["delta"] > 0 else ""
            dt_str = h["created_at"].strftime("%d.%m %H:%M") if h.get("created_at") else ""
            desc = h.get("details") or h.get("reason", "")
            lines.append(f"• {sign}{h['delta']} ({desc}) {dt_str}")
        history_text = "\n".join(lines)
    else:
        history_text = t.get("discipline_no_history", "История начислений пока пуста.")

    profile_text = strip_html(t.get(
        "discipline_profile_title",
        "⭐️ Рейтинг дисциплины\n\n👤 Житель: {name}\n🏆 Ранг: {badge} {rank}\n📊 Баллы: {score} / 200\n🔥 Серия подтверждений: {confirm_streak}\n⚠️ Серия пропусков: {miss_streak}\n\n📋 Правила:\n• Подтверждение вовремя: +5 баллов (+бонус за серию до +12)\n• Отмена заранее: +2 балла\n• Отказ в окне подтверждения: +1 балл\n• Пропуск без отмены: -15..-40 баллов\n\n🕒 Последние изменения:\n{history}"
    ).format(
        name=card.get("name") or user.first_name or "",
        badge=badge,
        rank=rank_title,
        score=card.get("score", 100),
        confirm_streak=card.get("confirm_streak", 0),
        miss_streak=card.get("miss_streak", 0),
        history=history_text
    ))

    await message.reply(text=profile_text, keyboard=get_rating_keyboard(lang, user.id))
