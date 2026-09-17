from aiogram import Router, F
from aiogram.types import CallbackQuery
from aiogram.fsm.context import FSMContext

from app.repositories.laundry_repo import get_booking_by_id, set_booking_status
from app.bot.utils.translate import ALL_TEXTS, get_lang_and_texts

confirm_router = Router()


@confirm_router.callback_query(F.data.startswith("confirm_"))
async def process_confirm(callback: CallbackQuery, state: FSMContext = None):
    try:
        booking_id = int(callback.data.split("_")[1])
    except (IndexError, ValueError):
        await callback.answer("Ошибка формата данных", show_alert=True)
        return

    # Получаем язык из FSM (если есть) или RU по умолчанию
    if state:
        lang, t = await get_lang_and_texts(state)
    else:
        lang = "RU"
        t = ALL_TEXTS["RU"]

    booking = await get_booking_by_id(booking_id)

    if not booking:
        await callback.answer(t.get("booking_already_confirmed", "Запись не найдена"), show_alert=True)
        try:
            await callback.message.delete()
        except Exception:
            pass
        return

    # Если язык сохранен у пользователя в БД, можно привязаться к нему
    if hasattr(booking, "user") and booking.user and getattr(booking.user, "language", None):
        user_lang = booking.user.language
        t = ALL_TEXTS.get(user_lang, t)

    if booking.status == "Подтверждено":
        await callback.answer(t.get("booking_already_confirmed", "Запись уже подтверждена"), show_alert=True)
        try:
            await callback.message.edit_reply_markup(reply_markup=None)
        except Exception:
            pass
        return

    if booking.status == "Отменено":
        cancel_msg = t.get("booking_already_canceled", t.get("cancel_error", "Запись уже отменена"))
        await callback.answer(cancel_msg, show_alert=True)
        try:
            await callback.message.edit_text(cancel_msg)
        except Exception:
            pass
        return

    # Успешное подтверждение
    await set_booking_status(booking_id, "Подтверждено")
    await callback.message.edit_text(t.get("booking_confirmed", "✅ Запись подтверждена! Ждем вас."))
    await callback.answer()
