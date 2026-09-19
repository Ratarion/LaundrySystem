import asyncio
import logging
from aiogram import Router, F, Bot
from aiogram.types import CallbackQuery
from aiogram.fsm.context import FSMContext

from app.repositories.laundry_repo import get_booking_by_id, set_booking_status, cancel_booking, get_user_by_tg_id
from app.bot.utils.translate import ALL_TEXTS, get_lang_and_texts
from app.bot.utils.broadcaster import broadcast_slot_freed
from app.bot.keyboards import get_confirmed_keyboard, get_declined_keyboard, get_section_keyboard

confirm_router = Router()


@confirm_router.callback_query(F.data.startswith("confirm_"))
async def process_confirm(callback: CallbackQuery, state: FSMContext = None):
    try:
        booking_id = int(callback.data.split("_")[1])
    except (IndexError, ValueError):
        await callback.answer("Ошибка формата данных", show_alert=True)
        return

    lang, t = await get_lang_and_texts(state, tg_id=callback.from_user.id)
    booking = await get_booking_by_id(booking_id)

    if not booking:
        await callback.answer(t.get("booking_already_confirmed", "Запись не найдена"), show_alert=True)
        try:
            await callback.message.delete()
        except Exception:
            pass
        return

    if hasattr(booking, "user") and booking.user and getattr(booking.user, "language", None):
        user_lang = booking.user.language
        t = ALL_TEXTS.get(user_lang, t)

    if booking.status == "Подтверждено":
        await callback.answer(t.get("booking_already_confirmed", "Запись уже подтверждена"), show_alert=True)
        try:
            await callback.message.edit_reply_markup(reply_markup=get_confirmed_keyboard(booking_id, lang))
        except Exception:
            pass
        return

    if booking.status == "Отменено":
        cancel_msg = t.get("booking_already_canceled", t.get("cancel_error", "Запись уже отменена"))
        await callback.answer(cancel_msg, show_alert=True)
        try:
            await callback.message.edit_text(cancel_msg, reply_markup=get_declined_keyboard(lang))
        except Exception:
            pass
        return

    # Успешное подтверждение
    await set_booking_status(booking_id, "Подтверждено")
    await callback.message.edit_text(
        t.get("booking_confirmed", "✅ Запись подтверждена! Ждем вас."),
        reply_markup=get_confirmed_keyboard(booking_id, lang)
    )
    await callback.answer()


@confirm_router.callback_query(F.data.startswith("decline_"))
async def process_decline(callback: CallbackQuery, bot: Bot, state: FSMContext = None):
    try:
        booking_id = int(callback.data.split("_")[1])
    except (IndexError, ValueError):
        await callback.answer("Ошибка формата данных", show_alert=True)
        return

    lang, t = await get_lang_and_texts(state, tg_id=callback.from_user.id)
    booking = await get_booking_by_id(booking_id)

    if not booking:
        await callback.answer(t.get("booking_already_canceled", "Запись не найдена"), show_alert=True)
        try:
            await callback.message.delete()
        except Exception:
            pass
        return

    if hasattr(booking, "user") and booking.user and getattr(booking.user, "language", None):
        user_lang = booking.user.language
        t = ALL_TEXTS.get(user_lang, t)

    if booking.status == "Отменено":
        await callback.answer(t.get("booking_already_canceled", "Запись уже отменена"), show_alert=True)
        try:
            await callback.message.edit_reply_markup(reply_markup=get_declined_keyboard(lang))
        except Exception:
            pass
        return

    # Подготавливаем данные для рассылки освободившегося слота
    dorm_id = getattr(booking, "dormitory_id", None) or (booking.machine.dormitory_id if getattr(booking, "machine", None) else None) or 1
    booking_data = {
        "dormitory_id": dorm_id,
        "machine_id": getattr(booking, "inidmachine", None) or (booking.machine.id if getattr(booking, "machine", None) else None),
        "start_iso": booking.start_time.strftime("%Y%m%d%H%M"),
        "date_str": booking.start_time.strftime("%d.%m"),
        "start_time_str": booking.start_time.strftime("%H:%M"),
        "end_time_str": booking.end_time.strftime("%H:%M"),
        "machine_type": getattr(booking.machine, "type_machine", "") if getattr(booking, "machine", None) else "",
        "machine_num": getattr(booking.machine, "number_machine", "") if getattr(booking, "machine", None) else "",
    }

    success = await cancel_booking(booking_id, user_tg_id=callback.from_user.id)
    if not success:
        success = await cancel_booking(booking_id)

    if success:
        await callback.answer(t.get("booking_declined", "Запись отменена"), show_alert=True)
        await callback.message.edit_text(
            t.get("booking_declined", "❌ Вы отменили запись. Слот освобожден для других жильцов."),
            reply_markup=get_declined_keyboard(lang)
        )
        asyncio.create_task(
            broadcast_slot_freed(bot, booking_data, exclude_tg_id=callback.from_user.id)
        )
    else:
        await callback.answer(t.get("cancel_error", "Ошибка при отмене"), show_alert=True)


@confirm_router.callback_query(F.data == "to_main_menu")
async def process_to_main_menu(callback: CallbackQuery, state: FSMContext = None):
    lang, t = await get_lang_and_texts(state, tg_id=callback.from_user.id)
    user = await get_user_by_tg_id(callback.from_user.id)
    db_name = user.first_name if user else callback.from_user.first_name

    if state:
        await state.clear()
        await state.update_data(lang=lang)

    await callback.message.edit_text(
        t["hello_user"].format(name=db_name),
        reply_markup=get_section_keyboard(lang)
    )
    await callback.answer()
