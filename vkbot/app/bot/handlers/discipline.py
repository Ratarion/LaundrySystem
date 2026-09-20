import logging
from vkbottle import GroupEventType
from vkbottle.bot import BotLabeler, MessageEvent, Message
from vkbottle.dispatch.rules.base import PayloadContainsRule

from app.bot.utils.translate import get_lang_and_texts
from app.bot.keyboards import get_rating_keyboard
from app.laundry_repo import get_user_by_vk_id
from app.services.discipline_service import get_resident_discipline_card
from app.bot.utils.text import strip_html

discipline_labeler = BotLabeler()
logger = logging.getLogger(__name__)


@discipline_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "show_rating"}))
async def show_discipline_rating_event(event: MessageEvent):
    peer_id = event.peer_id
    lang, t = await get_lang_and_texts(peer_id)
    user = await get_user_by_vk_id(event.user_id)

    if not user:
        await event.show_snackbar(t.get("none_user", "Пользователь не найден"))
        return

    card = await get_resident_discipline_card(user.id)
    if not card:
        await event.show_snackbar("Ошибка получения данных рейтинга")
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

    await event.edit_message(profile_text, keyboard=get_rating_keyboard(lang))


@discipline_labeler.message(text=["⭐️ Мой рейтинг", "Мой рейтинг", "Рейтинг", "/rating", "⭐️ My Rating", "My Rating", "⭐️ 我的积分", "我的积分"])
async def show_discipline_rating_msg(message: Message):
    peer_id = message.peer_id
    lang, t = await get_lang_and_texts(peer_id)
    user = await get_user_by_vk_id(message.from_id)

    if not user:
        await message.answer(t.get("none_user", "Пользователь не найден"))
        return

    card = await get_resident_discipline_card(user.id)
    if not card:
        await message.answer("Ошибка получения данных рейтинга")
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

    await message.answer(profile_text, keyboard=get_rating_keyboard(lang))
