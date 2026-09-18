from aiogram import Router, F
from aiogram.types import CallbackQuery
from aiogram.fsm.context import FSMContext
from datetime import datetime, time, timedelta
from app.bot.utils.timezone import get_kemerovo_now
from aiogram.exceptions import TelegramBadRequest
from aiogram_calendar import SimpleCalendar, SimpleCalendarCallback
try:
    from aiogram_calendar.schemas import SimpleCalendarAction
except ImportError:
    class SimpleCalendarAction:
        DAY = "DAY"

from app.bot.utils.translate import get_lang_and_texts

from app.bot.calendar_utils import CustomLaundryCalendar
from app.bot.states import AddRecord
from app.bot.keyboards import (
    get_section_keyboard,
    get_time_slots_keyboard,
    get_machines_keyboard,
    get_exit_keyboard,
    get_machine_type_keyboard
)
from app.repositories.laundry_repo import (
    get_user_by_tg_id,
    get_available_slots,
    get_available_machines,
    is_slot_free,
    create_booking,
    get_month_workload,
    get_total_daily_capacity_by_type,
    get_user_bookings,
    has_weekly_booking
)

import logging

booking_router = Router()

# helper for colored calendar (можно использовать если нужно создать календарь отдельно)
async def get_colored_calendar(year: int, month: int, locale: str, machine_type=None, dormitory_id: int = 1):
    workload = await get_month_workload(year, month, machine_type, dormitory_id=dormitory_id)
    max_slots = await get_total_daily_capacity_by_type(machine_type, dormitory_id=dormitory_id)
    calendar = CustomLaundryCalendar(workload=workload, max_capacity=max_slots, locale=locale)
    return await calendar.start_calendar(year=year, month=month)


@booking_router.callback_query(F.data == "record")
async def process_record_start(callback: CallbackQuery, state: FSMContext):
    lang, t = await get_lang_and_texts(state)
    user = await get_user_by_tg_id(callback.from_user.id)
    if not user:
        await callback.answer(t["none_user"], show_alert=True)
        return
    
    dormitory_id = getattr(user, "dormitory_id", 1) or 1
    await state.update_data(user_id=user.id, dormitory_id=dormitory_id)

    max_capacity = await get_total_daily_capacity_by_type(dormitory_id=dormitory_id)
    if max_capacity == 0:
        await callback.answer(t["no_active_machines"], show_alert=True)
        await callback.message.edit_text(t["section_menu_title"], reply_markup=get_section_keyboard(lang))
        await state.clear()
        return
    await state.update_data(max_capacity=max_capacity)
    await callback.message.edit_text(
        t["select_machine_type"],
        reply_markup=get_machine_type_keyboard(lang)
    )
    await state.set_state(AddRecord.waiting_for_machine_type)
    await callback.answer()


@booking_router.callback_query(F.data.startswith("type_"), AddRecord.waiting_for_machine_type)
async def process_machine_type(callback: CallbackQuery, state: FSMContext):
    lang, t = await get_lang_and_texts(state)
    data = await state.get_data()
    dormitory_id = data.get("dormitory_id", 1)
    machine_type_callback = callback.data.split("_")[1] # "WASH" или "DRY"
    
    # ПРИВЯЗЫВАЕМСЯ К ЗНАЧЕНИЯМ В БД (они у тебя на русском)
    if machine_type_callback == "WASH":
        machine_type_db = "Стиральная"
        header_text = f"📅 {t['record_start']} {t['for_wash']}"
    else:
        machine_type_db = "Сушильная"
        header_text = f"📅 {t['record_start']} {t['for_dry']}"

    # Теперь в state и в запросы улетит "Стиральная", и БД найдет машины
    await state.update_data(machine_type=machine_type_db)
    
    now = get_kemerovo_now()
    # Теперь эти функции получат правильный тип и вернут реальные цифры, а не 0
    workload = await get_month_workload(now.year, now.month, machine_type_db, dormitory_id=dormitory_id)
    max_capacity = await get_total_daily_capacity_by_type(machine_type_db, dormitory_id=dormitory_id)
    
    await state.update_data(max_capacity=max_capacity)

    calendar = CustomLaundryCalendar(workload=workload, max_capacity=max_capacity, locale=lang.lower())
    
    await callback.message.edit_text(
        header_text,
        reply_markup=await calendar.start_calendar(
            year=now.year, 
            month=now.month, 
            header_text=header_text, 
            back_callback="back_to_machine_type"
        )
    )
    await state.set_state(AddRecord.waiting_for_day)
    await callback.answer()


@booking_router.callback_query(SimpleCalendarCallback.filter(F.act == "DAY"), AddRecord.waiting_for_day)
async def process_simple_calendar(callback: CallbackQuery, callback_data: SimpleCalendarCallback, state: FSMContext):
    lang, t = await get_lang_and_texts(state)
    data = await state.get_data()
    dormitory_id = data.get("dormitory_id", 1)
    max_capacity = data.get('max_capacity', 0)
    machine_type_db = data.get('machine_type')
    
    # Generate header_text consistently (reuse from process_machine_type logic)
    if machine_type_db == "Стиральная":
        header_text = f"📅 {t['record_start']} {t['for_wash']}"
    else:
        header_text = f"📅 {t['record_start']} {t['for_dry']}"
    
    workload = await get_month_workload(callback_data.year, callback_data.month, machine_type_db, dormitory_id=dormitory_id)
    calendar = CustomLaundryCalendar(workload=workload, max_capacity=max_capacity, locale=lang.lower())

    selected, date = await calendar.process_selection(callback, callback_data)

    if selected and callback_data.act == SimpleCalendarAction.DAY:
        now_dt = get_kemerovo_now()
        if date.date() < now_dt.date() or (date.date() == now_dt.date() and now_dt.time() >= time(23, 0)):
            await callback.answer(t["past_date_error"], show_alert=True)
            await callback.message.edit_text(
                header_text,  # Use header_text or t["record_start"]
                reply_markup=await calendar.start_calendar(year=callback_data.year, month=callback_data.month, back_callback="back_to_machine_type")
            )
            await state.set_state(AddRecord.waiting_for_day)
            return

        day = date.day
        used = workload.get(day, 0)
        free = max_capacity - used if max_capacity > 0 else 0
        if free <= 0:
            await callback.answer(t["day_fully_booked"], show_alert=True)
            await callback.message.edit_text(
                header_text,  # Use header_text or t["record_start"]
                reply_markup=await calendar.start_calendar(year=callback_data.year, month=callback_data.month, back_callback="back_to_machine_type")
            )
            await state.set_state(AddRecord.waiting_for_day)
            return

        # Weekly limit check
        user_id = data.get('user_id')
        if await has_weekly_booking(user_id, date, machine_type_db):
            await callback.answer(t["weekly_limit_reached"], show_alert=True)
            await callback.message.edit_text(
                header_text,  # FIXED: Use t["record_start"] or header_text (string, not list)
                reply_markup=await calendar.start_calendar(year=callback_data.year, month=callback_data.month, back_callback="back_to_machine_type")
            )
            await state.set_state(AddRecord.waiting_for_day)
            return

        await state.update_data(chosen_date=date)
        slots = await get_available_slots(date, machine_type=machine_type_db, dormitory_id=dormitory_id)
        if not slots:
            await callback.answer(t["no_slots_available"], show_alert=True)
            await callback.message.edit_text(
                header_text,  # Use header_text or t["record_start"]
                reply_markup=await calendar.start_calendar(year=callback_data.year, month=callback_data.month, back_callback="back_to_machine_type")
            )
            await state.set_state(AddRecord.waiting_for_day)
            return

        await callback.message.edit_text(
            t["select_time_prompt"].replace("{date}", date.strftime("%d.%m")),
            reply_markup=get_time_slots_keyboard(date, slots, lang)
        )
        await state.set_state(AddRecord.waiting_for_time)
        await callback.answer()

# Код выбора времени — заменил user_router на booking_router
@booking_router.callback_query(F.data.startswith("time_"), AddRecord.waiting_for_time)
async def process_time_slot(callback: CallbackQuery, state: FSMContext):
    lang, t = await get_lang_and_texts(state)
    data = await state.get_data()
    dormitory_id = data.get("dormitory_id", 1)

    parts = callback.data.split("_")
    # ожидаем формат time_YEAR_MONTH_DAY_HOUR_MINUTE
    year, month, day, hour, minute = map(int, parts[1:6])
    chosen_dt = datetime(year, month, day, hour, minute)
    
    if chosen_dt <= get_kemerovo_now():
        await callback.answer(t.get("past_date_error", "Время уже прошло"), show_alert=True)
        return

    # Рассчитываем время окончания (90 минут, как в логике бронирования)
    duration_minutes = 90
    end_dt = chosen_dt + timedelta(minutes=duration_minutes)

    await state.update_data(start_time=chosen_dt)

    machine_type_db = data.get('machine_type')
    available_machines = await get_available_machines(chosen_dt, machine_type_db, dormitory_id=dormitory_id)

    if not available_machines:
        await callback.answer(t["no_available_slots_alert"], show_alert=True)
        await callback.message.edit_text(t["machines_none"])
        return

    # Формируем новый текст с интервалом времени
    prompt_text = t["machine_prompt"].format(
        date=chosen_dt.strftime('%d.%m'),
        start=chosen_dt.strftime('%H:%M'),
        end=end_dt.strftime('%H:%M')
    )

    await callback.message.edit_text(
        prompt_text,
        reply_markup=get_machines_keyboard(available_machines, lang)
    )
    await state.set_state(AddRecord.waiting_for_machine)
    await callback.answer()

@booking_router.callback_query(F.data.startswith("machine_"), AddRecord.waiting_for_machine)
async def process_machine(callback: CallbackQuery, state: FSMContext):
    lang, t = await get_lang_and_texts(state)
    machine_id = int(callback.data.split("_")[1])
    data = await state.get_data()
    dormitory_id = data.get("dormitory_id", 1)
    duration_minutes = 90
    start_time = data["start_time"]
    end_time = start_time + timedelta(minutes=duration_minutes)

    # Получаем user_id из state (сохранён в process_record_start)
    user_id = data.get('user_id')
    if not user_id:
        await callback.answer(t.get("none_user", "User not found"), show_alert=True)
        return

    try:
        # Теперь create_booking сама проверит слот и лимит, и бросит ValueError если нужно
        result = await create_booking(
            user_id=user_id,
            machine_id=machine_id,
            start_time=start_time,
            dormitory_id=dormitory_id
        )

        msg = t["booking_success"].format(
            machine_num=result['machine'].number_machine,
            start=start_time.strftime('%d.%m.%Y %H:%M'),
            end=end_time.strftime('%H:%M')
        )
        if getattr(result.get("booking"), "dormitory_id", None):
            msg += f"\n🏢 Общежитие №{result['booking'].dormitory_id}"

        await callback.message.edit_text(
            msg,
            reply_markup=get_exit_keyboard(lang)
        )
        await state.clear()
        await state.update_data(lang=lang)
        return

    except ValueError as e:
        error_msg = str(e)
        if error_msg == "Weekly limit reached":
            await callback.answer(t["weekly_limit_reached"], show_alert=True)
            # Вернуться назад, например, на выбор времени (опционально)
            await process_back_to_time(callback, state)
            return
        elif error_msg == "Слот уже занят":  # Или "Slot is already taken", если изменили в repo
            await callback.answer(t["slot_just_taken"], show_alert=True)
        else:
            await callback.answer(t["booking_error"], show_alert=True)
        
        logging.exception(e)  # Логируем для отладки

    # Освобождаем state на всякий случай
    await state.clear()
    await state.update_data(lang=lang)


@booking_router.callback_query(F.data == "back_to_sections", AddRecord.waiting_for_machine_type)
async def process_back_to_sections(callback: CallbackQuery, state: FSMContext):
    lang, t = await get_lang_and_texts(state)
    user = await get_user_by_tg_id(callback.from_user.id)
    
    db_name = user.first_name 

    await callback.message.edit_text(
        t["hello_user"].format(name=db_name),
        reply_markup=get_section_keyboard(lang)
    )
    await state.clear()
    await callback.answer()


@booking_router.callback_query(F.data == "back_to_calendar", AddRecord.waiting_for_time)
async def process_back_to_calendar(callback: CallbackQuery, state: FSMContext):
    lang, t = await get_lang_and_texts(state)
    data = await state.get_data()
    dormitory_id = data.get("dormitory_id", 1)
    machine_type_db = data.get('machine_type')
    max_capacity = data.get('max_capacity', 0)
    now = get_kemerovo_now()
    workload = await get_month_workload(now.year, now.month, machine_type_db, dormitory_id=dormitory_id)

    calendar = CustomLaundryCalendar(
        workload=workload,
        max_capacity=max_capacity,
        locale=lang.lower()
    )

    # Generate header text to be consistent
    if machine_type_db == t.get("machine_type_wash"):
         header_text = f"📅 {t['record_start']} {t['for_wash']}"
    else:
         header_text = f"📅 {t['record_start']} {t['for_dry']}"

    await callback.message.edit_text(
        header_text,
        reply_markup=await calendar.start_calendar(
            year=now.year,
            month=now.month,
            header_text=header_text,
            back_callback="back_to_machine_type"
        )
    )
    await state.set_state(AddRecord.waiting_for_day)
    await callback.answer()


@booking_router.callback_query(F.data == "back_to_time", AddRecord.waiting_for_machine)
async def process_back_to_time(callback: CallbackQuery, state: FSMContext):
    lang, t = await get_lang_and_texts(state)
    data = await state.get_data()
    dormitory_id = data.get("dormitory_id", 1)
    chosen_date = data.get('chosen_date')
    if not chosen_date:
        await callback.answer("Дата не найдена", show_alert=True)
        return
    machine_type_db = data.get('machine_type')
    slots = await get_available_slots(chosen_date, machine_type=machine_type_db, dormitory_id=dormitory_id)
    await callback.message.edit_text(
        t["select_time_prompt"].replace("{date}", chosen_date.strftime("%d.%m")),
        reply_markup=get_time_slots_keyboard(chosen_date, slots, lang)
    )
    await state.set_state(AddRecord.waiting_for_time)
    await callback.answer()

@booking_router.callback_query(F.data == "back_to_machine_type")
async def back_to_machine_type(callback: CallbackQuery, state: FSMContext):
    lang, t = await get_lang_and_texts(state)
    await callback.message.edit_text(
        t["select_machine_type"],
        reply_markup=get_machine_type_keyboard(lang)
    )
    await state.set_state(AddRecord.waiting_for_machine_type)
    await callback.answer()


@booking_router.callback_query(F.data == "exit")  # <--- Проверьте, совпадает ли это с callback_data кнопки
async def process_exit(callback: CallbackQuery, state: FSMContext):
    lang, t = await get_lang_and_texts(state)
    
    # 1. Получаем пользователя из БД по Telegram ID
    user = await get_user_by_tg_id(callback.from_user.id)


    # 4. ВАЖНО: Берем имя ИМЕННО из объекта user (из БД)
    # user.first_name — это имя из базы (Инцзе)
    # callback.from_user.first_name — это имя из ТГ (Стас)
    
    db_name = user.first_name if user else callback.from_user.first_name
    
    await state.clear()
    await state.update_data(lang=lang)

    await callback.message.edit_text(
        t["hello_user"].format(name=db_name),
        reply_markup=get_section_keyboard(lang)
    )
    await callback.answer()


# # Отладочный / универсальный логгер колбэков (оставил, но на booking_router)
# @booking_router.callback_query()
# async def debug_callback(cb: CallbackQuery):
#     import logging
#     logging.info("Callback received: %s", cb.data)
#     await cb.answer()
