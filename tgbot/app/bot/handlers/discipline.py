import logging
from aiogram import Router, F
from aiogram.types import CallbackQuery, Message
from aiogram.fsm.context import FSMContext

from app.bot.utils.translate import get_lang_and_texts
from app.bot.keyboards import get_rating_keyboard
from app.repositories.laundry_repo import get_user_by_tg_id
from app.services.discipline_service import get_resident_discipline_card

discipline_router = Router()
logger = logging.getLogger(__name__)


from aiogram.filters import Command

@discipline_router.message(Command("rating"))
@discipline_router.message(F.text.in_({"⭐️ Мой рейтинг", "Мой рейтинг", "Рейтинг", "⭐️ My Rating", "My Rating", "⭐️ 我的积分", "我的积分"}))
@discipline_router.callback_query(F.data == "show_rating")
async def show_discipline_rating(event: CallbackQuery | Message, state: FSMContext = None):
    is_callback = isinstance(event, CallbackQuery)
    user_id = event.from_user.id
    lang, t = await get_lang_and_texts(state, tg_id=user_id)
    user = await get_user_by_tg_id(user_id)

    if not user:
        if is_callback:
            await event.answer(t.get("none_user", "Пользователь не найден"), show_alert=True)
        else:
            await event.answer(t.get("none_user", "Пользователь не найден"))
        return

    try:
        card = await get_resident_discipline_card(user.id)
    except Exception as e:
        logger.error(f"Error fetching discipline card for user {user.id}: {e}")
        card = None

    if not card:
        await callback.answer("Ошибка получения данных рейтинга", show_alert=True)
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
            lines.append(f"• <b>{sign}{h['delta']}</b> ({desc}) <small>{dt_str}</small>")
        history_text = "\n".join(lines)
    else:
        history_text = t.get("discipline_no_history", "История начислений пока пуста.")

    profile_text = t.get(
        "discipline_profile_title",
        "⭐️ <b>Рейтинг дисциплины</b>\n\n👤 <b>Житель:</b> {name}\n🏆 <b>Ранг:</b> {badge} {rank}\n📊 <b>Баллы:</b> {score} / 200\n🔥 <b>Серия подтверждений:</b> {confirm_streak}\n⚠️ <b>Серия пропусков:</b> {miss_streak}\n\n📋 <b>Правила:</b>\n• Подтверждение вовремя: +5 баллов (+бонус за серию до +12)\n• Отмена заранее: +2 балла\n• Отказ в окне подтверждения: +1 балл\n• Пропуск без отмены: -15..-40 баллов\n\n🕒 <b>Последние изменения:</b>\n{history}"
    ).format(
        name=card.get("name") or user.first_name or "",
        badge=badge,
        rank=rank_title,
        score=card.get("score", 100),
        confirm_streak=card.get("confirm_streak", 0),
        miss_streak=card.get("miss_streak", 0),
        history=history_text
    )

    if is_callback:
        await event.message.edit_text(
            profile_text,
            reply_markup=get_rating_keyboard(lang, user.id),
            parse_mode="HTML"
        )
        await event.answer()
    else:
        await event.answer(
            profile_text,
            reply_markup=get_rating_keyboard(lang, user.id),
            parse_mode="HTML"
        )
