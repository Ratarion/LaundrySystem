from aiogram import Router, F
from aiogram.filters import Command
from aiogram.types import CallbackQuery, Message
from aiogram.exceptions import TelegramBadRequest
from aiogram.fsm.context import FSMContext
from app.bot.utils.translate import get_lang_and_texts
from app.bot.keyboards import get_section_keyboard
from app.bot.states import DisplayRecords
from app.repositories.laundry_repo import get_user_by_tg_id, get_user_bookings, cancel_booking
import logging
from app.bot.keyboards import get_back_to_sections_keyboard

records_router = Router()


@records_router.message(Command("records"))
@records_router.message(F.text.in_({
    "📋 Мои записи", "📋 My Bookings", "📋 我的预约",
    "/records", "мои записи", "Мои записи"
}))
async def show_records_msg(message: Message, state: FSMContext):
    await state.clear()
    lang, t = await get_lang_and_texts(state, tg_id=message.from_user.id)
    await state.set_state(DisplayRecords.waiting_for_display)
    user = await get_user_by_tg_id(message.from_user.id)
    
    if not user:
        await message.answer(t["none_user"])
        return

    bookings = await get_user_bookings(user.id)
    back_kb = get_back_to_sections_keyboard(lang)

    if not bookings:
        no_bookings_text = t.get("no_user_bookings", "У вас нет записей.")
        await message.answer(no_bookings_text, reply_markup=back_kb)
        return

    lines = []
    machine_label = t.get('machine', 'Машина')

    for b in bookings[:20]:
        start_str = b.start_time.strftime("%d.%m.%Y %H:%M") if b.start_time else "—"
        end_str = b.end_time.strftime("%H:%M") if b.end_time else "—"
        machine_num = b.machine.number_machine if hasattr(b, 'machine') and b.machine else "—"
        
        raw_type = b.machine.type_machine if hasattr(b, 'machine') and b.machine else "—"
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
    await message.answer(text, reply_markup=back_kb)


@records_router.callback_query(F.data == "show_records")
async def show_records(callback: CallbackQuery, state: FSMContext):
    lang, t = await get_lang_and_texts(state, tg_id=callback.from_user.id)
    await state.set_state(DisplayRecords.waiting_for_display)
    user = await get_user_by_tg_id(callback.from_user.id)
    
    if not user:
        await callback.answer(t["none_user"], show_alert=True)
        return

    bookings = await get_user_bookings(user.id)
    
    back_kb = get_back_to_sections_keyboard(lang)

    if not bookings:
        no_bookings_text = t.get("no_user_bookings", "У вас нет записей.")
        await callback.message.edit_text(no_bookings_text, reply_markup=back_kb)
        return

    lines = []
    machine_label = t.get('machine', 'Машина')

    for b in bookings[:20]:
        start_str = b.start_time.strftime("%d.%m.%Y %H:%M") if b.start_time else "—"
        end_str = b.end_time.strftime("%H:%M") if b.end_time else "—"
        machine_num = b.machine.number_machine if hasattr(b, 'machine') and b.machine else "—"
        
        # --- НОВОЕ: перевод типа машины ---
        raw_type = b.machine.type_machine if hasattr(b, 'machine') and b.machine else "—"
        if raw_type == "Стиральная":
            machine_type = t.get("machine_type_wash", "Стиральная")
        elif raw_type == "Сушильная":
            machine_type = t.get("machine_type_dry", "Сушильная")
        else:
            machine_type = raw_type
        # ------------------------------------

        dorm_text = f" • Общ. №{b.dormitory_id}" if getattr(b, "dormitory_id", None) else ""
        lines.append(f"• {start_str} - {end_str}{dorm_text} • {machine_label} №{machine_num} ({machine_type})")

    title = t.get("show_records_title", "Ваши записи:")
    text = title + "\n\n" + "\n".join(lines)

    try:
        await callback.message.edit_text(text, reply_markup=back_kb)
    except TelegramBadRequest:
        pass

@records_router.callback_query(F.data == "back_to_sections")
async def back_from_records(callback: CallbackQuery, state: FSMContext):
    lang, t = await get_lang_and_texts(state)
    
    # ДОБАВЛЕНО: Получаем user из БД
    user = await get_user_by_tg_id(callback.from_user.id)
    if not user:
        await callback.answer(t["none_user"], show_alert=True)
        return
    
    # Очищаем состояние (выходим из DisplayRecords)
    await state.clear()
    # Восстанавливаем выбранный язык
    await state.update_data(lang=lang)

    await callback.message.edit_text(
        t["hello_user"].format(name=user.first_name),  # ИЗМЕНЕНО: из БД
        reply_markup=get_section_keyboard(lang)
    )
    await callback.answer()