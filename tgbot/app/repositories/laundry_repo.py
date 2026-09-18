import asyncio
from datetime import datetime, timedelta, time
from typing import List, Optional

from sqlalchemy import join, select, update, delete, and_, func, extract, Integer, or_
from sqlalchemy.orm import joinedload
from sqlalchemy.ext.asyncio import AsyncSession

from app.db.base import async_session
from app.db.models.residents import Resident as User
from app.db.models.machine import Machine as Machine, MACHINE_STATUS_ACTIVE
from app.db.models.booking import Booking as Booking
from app.db.models.notification import Notification
from app.bot.utils.timezone import get_kemerovo_now

# ==========================================
# РАБОТА С ПОЛЬЗОВАТЕЛЯМИ (АУТЕНТИФИКАЦИЯ)
# ==========================================

async def get_user_by_tg_id(tg_id: int):
    async with async_session() as session:
        query = select(User).where(User.tg_id == tg_id)
        result = await session.execute(query)
        return result.scalar_one_or_none()

async def find_resident_by_fio(fio_parts: list[str]):
    if len(fio_parts) < 2:
        return None

    # Гибкое присваивание
    last_name = fio_parts[0]
    first_name = fio_parts[1]
    patronymic = ' '.join(fio_parts[2:]) if len(fio_parts) > 2 else ''  # Объединяем лишние слова или оставляем пустым

    async with async_session() as session:
        # Используем ilike для case-insensitive (полезно для транслитерации)
        conditions = [
            User.last_name.ilike(last_name),
            User.first_name.ilike(first_name)
        ]
        if patronymic:
            conditions.append(User.patronymic.ilike(patronymic))
        else:
            # Если patronymic не указан, ищем где оно пустое или NULL
            conditions.append(or_(User.patronymic == '', User.patronymic.is_(None)))

        query = select(User).where(and_(*conditions))
        result = await session.execute(query)
        found_users = result.scalars().all()
        
        if len(found_users) == 1:
            return found_users[0]
        return None


async def find_resident_by_id_card(id_card: int):
    async with async_session() as session:
        query = select(User).where(User.idcards == id_card)
        result = await session.execute(query)
        return result.scalar_one_or_none()

async def activate_resident_user(resident_id: int, tg_id: int, language: str = 'RU'):
    """
    Привязывает tg_id к жильцу и сохраняет выбранный язык.
    """
    async with async_session() as session:
        # Обновляем и tg_id, и language
        stmt = update(User).where(User.id == resident_id).values(
            tg_id=tg_id, 
            language=language
        )
        await session.execute(stmt)
        await session.commit()
        
        result = await session.execute(select(User).where(User.id == resident_id))
        return result.scalar_one()
    
# 👇 Добавьте эту функцию, она пригодится для кнопки "Сменить язык" в будущем
async def update_user_language(tg_id: int, new_language: str):
    """
    Обновляет язык для уже зарегистрированного пользователя.
    """
    async with async_session() as session:
        stmt = update(User).where(User.tg_id == tg_id).values(language=new_language)
        await session.execute(stmt)
        await session.commit()

# ==========================================
# РАБОТА С МАШИНАМИ И БРОНЯМИ
# ==========================================

async def get_all_machines(dormitory_id: Optional[int] = None) -> List[Machine]:
    async with async_session() as session:
        query = select(Machine)
        if dormitory_id:
            query = query.where(Machine.dormitory_id == dormitory_id)
        result = await session.execute(query.order_by(Machine.number_machine))
        return result.scalars().all()

async def is_slot_free(machine_id: int, date: datetime, duration_minutes: int = 90) -> bool:
    end_time = date + timedelta(minutes=duration_minutes)
    async with async_session() as session:
        result = await session.execute(
            select(Booking.id).where(
                Booking.inidmachine == machine_id,
                Booking.status != 'Отменено',
                or_(
                    and_(Booking.start_time <= date, Booking.end_time > date),
                    and_(Booking.start_time < end_time, Booking.end_time >= end_time),
                    and_(Booking.start_time >= date, Booking.end_time <= end_time)
                )
            ).limit(1)
        )
        return result.scalar_one_or_none() is None

async def create_booking(user_id: int, machine_id: int, start_time: datetime, duration_minutes: int = 90, dormitory_id: Optional[int] = None) -> dict:
    if start_time <= get_kemerovo_now():
        raise ValueError("Cannot book past time")
    end_time = start_time + timedelta(minutes=duration_minutes)
    
    # Проверка слота (как раньше)
    if not await is_slot_free(machine_id, start_time, duration_minutes):
        raise ValueError("Слот уже занят")
    
    async with async_session() as session:
        machine_query = select(Machine).where(Machine.id == machine_id)
        result = await session.execute(machine_query)
        machine = result.scalar_one_or_none()
        if not machine:
            raise ValueError("Machine not found")
        
        machine_type = machine.type_machine
        dorm_id = dormitory_id or getattr(machine, "dormitory_id", 1) or 1
        
        # Проверка недельного лимита с типом
        if await has_weekly_booking(user_id, start_time, machine_type):
            raise ValueError("Weekly limit reached")

        # Если запись создается менее чем за 60 минут до начала (например, на ближайший слот сегодня),
        # житель прямо сейчас бронирует слот — автоматически ставим статус 'Подтверждено'
        initial_status = "Подтверждено" if start_time <= get_kemerovo_now() + timedelta(minutes=60) else "Ожидание"

        booking = Booking(
            dormitory_id=dorm_id,
            inidresidents=user_id,
            inidmachine=machine_id,
            start_time=start_time,
            end_time=end_time,
            status=initial_status
        )
        session.add(booking)
        await session.commit()
        await session.refresh(booking)
        
        return {'booking': booking, 'machine': machine}

async def get_user_bookings(user_id: int) -> List[Booking]:
    async with async_session() as session:
        now = get_kemerovo_now()  # Получаем текущее время (Kemerovo)
        
        query = (
            select(Booking)
            .options(joinedload(Booking.machine))
            .where(
                Booking.inidresidents == user_id,
                Booking.status != 'Отменено',
                Booking.end_time > now  # ФИЛЬТР: только те, что еще не закончились
            )
            .order_by(Booking.start_time.asc()) # Сортируем от ближайших к более поздним
        )
        
        result = await session.execute(query)
        return result.scalars().all()

async def cancel_booking(booking_id: int, user_tg_id: int = None) -> bool:
    """
    Если user_tg_id передан — проверяем, принадлежит ли бронь этому юзеру.
    Если user_tg_id is None — считаем, что это системная отмена (планировщик), и удаляем без проверок владельца.
    """
    async with async_session() as session:
        # 1. Находим бронь
        stmt_get = select(Booking).where(Booking.id == booking_id)
        result = await session.execute(stmt_get)
        booking = result.scalar_one_or_none()
        
        if not booking:
            return False

        # 2. Если это ручная отмена пользователем — проверяем владельца
        if user_tg_id is not None:
            user_query = select(User).where(User.tg_id == user_tg_id)
            user_result = await session.execute(user_query)
            user = user_result.scalar_one_or_none()
            
            if not user or booking.inidresidents != user.id:
                return False # Пытается отменить чужую запись

        # 3. Меняем статус (или удаляем)
        # Если вы хотите оставлять историю со статусом:
        booking.status = 'Отменено' # Убедитесь, что это совпадает с ENUM в базе или логикой
        
        await session.commit()
        return True

async def get_all_users_with_tg(dormitory_id: Optional[int] = None) -> List[tuple[int, str]]:
    """
    Возвращает список кортежей (tg_id, language) всех пользователей.
    """
    async with async_session() as session:
        query = select(User.tg_id, User.language).where(User.tg_id.is_not(None))
        if dormitory_id:
            query = query.where(User.dormitory_id == dormitory_id)
        result = await session.execute(query)
        return result.all()

# ==========================================
# ОПТИМИЗИРОВАННАЯ ЛОГИКА КАЛЕНДАРЯ
# ==========================================

async def get_month_workload(year: int, month: int, machine_type: Optional[str] = None, dormitory_id: Optional[int] = None) -> dict:
    """Один быстрый запрос для получения загруженности"""
    async with async_session() as session:
        query = (
            select(
                extract('day', Booking.start_time).cast(Integer).label('day'),
                func.count(Booking.id).label('count')
            )
            .join(Machine, Booking.inidmachine == Machine.id)
            .where(
                extract('year', Booking.start_time) == year,
                extract('month', Booking.start_time) == month,
                Booking.status != 'Отменено'
            )
        )
        if machine_type:
            query = query.where(Machine.type_machine == machine_type)
        if dormitory_id:
            query = query.where(Booking.dormitory_id == dormitory_id)
            
        query = query.group_by('day')
        result = await session.execute(query)
        return {row.day: row.count for row in result.all()}

async def get_total_daily_capacity_by_type(machine_type: Optional[str] = None, dormitory_id: Optional[int] = None) -> int:
    """
    Возвращает ОБЩЕЕ КОЛИЧЕСТВО СЛОТОВ в день (Кол-во машин * Кол-во слотов).
    """
    async with async_session() as session:
        conditions = [Machine.status == MACHINE_STATUS_ACTIVE]
        if machine_type:
            conditions.append(Machine.type_machine == machine_type)
        if dormitory_id:
            conditions.append(Machine.dormitory_id == dormitory_id)
        
        query = select(func.count(Machine.id)).where(and_(*conditions))
        active_machines = (await session.execute(query)).scalar() or 0

    slots_per_machine = 10 
    total_slots = active_machines * slots_per_machine
    
    return total_slots

# ==========================================
# ОПТИМИЗИРОВАННЫЙ ПОИСК СЛОТОВ 
# ==========================================

async def get_available_machines(start_time: datetime, machine_type: str, dormitory_id: Optional[int] = None) -> List[Machine]:
    """1 запрос вместо 10. Ищем занятые и исключаем их."""
    duration_minutes = 90
    end_time = start_time + timedelta(minutes=duration_minutes)

    async with async_session() as session:
        busy_subquery = select(Booking.inidmachine).where(
            Booking.status != 'Отменено',
            or_(
                and_(Booking.start_time <= start_time, Booking.end_time > start_time),
                and_(Booking.start_time < end_time, Booking.end_time >= end_time),
                and_(Booking.start_time >= start_time, Booking.end_time <= end_time)
            )
        )

        conditions = [
            Machine.status == MACHINE_STATUS_ACTIVE,
            Machine.type_machine == machine_type,
            Machine.id.not_in(busy_subquery)
        ]
        if dormitory_id:
            conditions.append(Machine.dormitory_id == dormitory_id)

        query = select(Machine).where(and_(*conditions))
        
        result = await session.execute(query)
        return result.scalars().all()

async def get_available_slots(
    date: datetime,
    machine_type: Optional[str] = None,
    dormitory_id: Optional[int] = None,
    work_start: int = 8,
    work_end: int = 23,
    slot_duration: int = 90
) -> List[datetime]:
    start_of_day = date.replace(hour=work_start, minute=0, second=0, microsecond=0)
    end_of_day = date.replace(hour=work_end, minute=0, second=0, microsecond=0)
    now = get_kemerovo_now()

    if date.date() < now.date():
        return []

    async with async_session() as session:
        conditions = [Machine.status == MACHINE_STATUS_ACTIVE]
        if machine_type:
            conditions.append(Machine.type_machine == machine_type)
        if dormitory_id:
            conditions.append(Machine.dormitory_id == dormitory_id)
        
        machines_query = select(Machine.id).where(and_(*conditions))
        active_machine_ids = (await session.execute(machines_query)).scalars().all()
        
        total_machines = len(active_machine_ids)
        if total_machines == 0:
            return []

        bookings_query = select(Booking).where(
            Booking.start_time >= start_of_day,
            Booking.start_time < end_of_day,
            Booking.status != 'Отменено',
            Booking.inidmachine.in_(active_machine_ids)
        )
        bookings_result = await session.execute(bookings_query)
        bookings = bookings_result.scalars().all()

    available_slots = []
    current_slot = start_of_day

    while current_slot + timedelta(minutes=slot_duration) <= end_of_day:
        if current_slot <= now:
            current_slot += timedelta(minutes=slot_duration)
            continue

        slot_end = current_slot + timedelta(minutes=slot_duration)
        
        # Считаем, сколько машин занято в этот конкретный слот
        busy_count = 0
        for b in bookings:
            # Пересечение интервалов
            # (StartA < EndB) and (EndA > StartB)
            if b.start_time < slot_end and b.end_time > current_slot:
                busy_count += 1
        
        # Если занято меньше машин, чем всего есть -> слот свободен
        if busy_count < total_machines:
            available_slots.append(current_slot)
            
        current_slot += timedelta(minutes=slot_duration)

    return available_slots

async def create_notification(resident_id: int, description: str, booking_id: Optional[int] = None):
    async with async_session() as session:
        notification = Notification(
            id_residents=resident_id,
            create_date=get_kemerovo_now(),
            description=description
        )
        session.add(notification)
        await session.commit()
        await session.refresh(notification)
        return notification
    

async def get_booking_by_id(booking_id: int) -> Optional[Booking]:
    """Получает бронь по ID с подгрузкой машины (для текста уведомления)."""
    async with async_session() as session:
        query = (
            select(Booking)
            .options(joinedload(Booking.machine))
            .where(Booking.id == booking_id)
        )
        result = await session.execute(query)
        return result.scalar_one_or_none()
    




async def get_bookings_to_remind(minutes_before: int = 60, minutes_deadline: int = 30):
    """Ищет записи, которые начнутся через minutes_before (60 мин), и статус еще 'Ожидание' (напоминание еще не отправлялось)"""
    now = get_kemerovo_now()
    max_time = now + timedelta(minutes=minutes_before)
    min_time = now + timedelta(minutes=minutes_deadline)
    
    async with async_session() as session:
        query = select(Booking).options(joinedload(Booking.user), joinedload(Booking.machine)).where(
            and_(
                Booking.start_time <= max_time,
                Booking.start_time > min_time,
                or_(Booking.status == 'Ожидание', Booking.status == None) 
            )
        )
        result = await session.execute(query)
        return result.scalars().all()
    
async def set_booking_status(booking_id: int, status: str):
    async with async_session() as session:
        query = update(Booking).where(Booking.id == booking_id).values(status=status)
        await session.execute(query)
        await session.commit()

async def get_expired_unconfirmed_bookings(minutes_before_deadline: int = 30):
    """Ищет записи, до начала которых осталось <= minutes_before_deadline (30 мин), но статус все еще не подтвержден"""
    now = get_kemerovo_now()
    deadline_time = now + timedelta(minutes=minutes_before_deadline)

    async with async_session() as session:
        query = select(Booking).options(joinedload(Booking.machine), joinedload(Booking.user)).where(
            and_(
                Booking.start_time <= deadline_time,
                Booking.start_time >= now - timedelta(minutes=15), 
                Booking.status.in_(['Ожидание', 'Ожидание подтверждения'])
            )
        )
        result = await session.execute(query)
        return result.scalars().all()
    

# Модифицированная has_weekly_booking
async def has_weekly_booking(user_id: int, target_date: datetime, machine_type: Optional[str] = None) -> bool:
    """
    Проверяет, есть ли у пользователя активная бронь в неделе target_date.
    Неделя: с понедельника по воскресенье.
    Если machine_type указан, проверяет только для этого типа машины.
    """
    now = get_kemerovo_now()
    
    # Определяем начало и конец недели для target_date
    week_day = target_date.weekday()  # 0 = понедельник, 6 = воскресенье
    start_of_week = target_date - timedelta(days=week_day)
    start_of_week = start_of_week.replace(hour=0, minute=0, second=0, microsecond=0)
    
    end_of_week = start_of_week + timedelta(days=6)
    end_of_week = end_of_week.replace(hour=23, minute=59, second=59, microsecond=999999)
    
    async with async_session() as session:
        query = select(func.count(Booking.id)).where(
            Booking.inidresidents == user_id,
            Booking.status != 'Отменено',
            Booking.end_time > now,  # Только будущие/активные
            Booking.start_time >= start_of_week,
            Booking.start_time <= end_of_week
        )
        
        # НОВАЯ ЧАСТЬ: Фильтр по типу машины, если указан
        if machine_type:
            query = query.join(Machine, Booking.inidmachine == Machine.id).where(
                Machine.type_machine == machine_type
            )
        
        result = await session.execute(query)
        count = result.scalar() or 0
        return count > 0