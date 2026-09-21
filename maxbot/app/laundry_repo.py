import asyncio
from datetime import datetime, timedelta, time, date
from typing import List, Optional

from sqlalchemy import select, update, delete, and_, func, extract, Integer, or_, cast, Date, case
from sqlalchemy.orm import joinedload

from app.db.base import async_session
from app.db.models.residents import Resident as User
from app.db.models.machine import Machine as Machine, MACHINE_STATUS_ACTIVE
from app.db.models.booking import Booking as Booking
from app.db.models.notification import Notification
from app.bot.utils.timezone import get_kemerovo_now

# ==========================================
# РАБОТА С ПОЛЬЗОВАТЕЛЯМИ (АУТЕНТИФИКАЦИЯ MAX)
# ==========================================

async def get_user_by_max_id(max_id: int) -> Optional[User]:
    async with async_session() as session:
        query = select(User).where(User.max_id == max_id)
        result = await session.execute(query)
        return result.scalar_one_or_none()

async def find_resident_by_fio(fio_parts: list[str]) -> Optional[User]:
    if len(fio_parts) < 2:
        return None

    last_name = fio_parts[0]
    first_name = fio_parts[1]
    patronymic = ' '.join(fio_parts[2:]) if len(fio_parts) > 2 else ''

    async with async_session() as session:
        conditions = [
            User.last_name.ilike(last_name),
            User.first_name.ilike(first_name)
        ]
        if patronymic:
            conditions.append(User.patronymic.ilike(patronymic))
        else:
            conditions.append(or_(User.patronymic == '', User.patronymic.is_(None)))

        query = select(User).where(and_(*conditions))
        result = await session.execute(query)
        found_users = result.scalars().all()

        if len(found_users) == 1:
            return found_users[0]
        return None

async def find_resident_by_id_card(id_card: str) -> Optional[User]:
    raw = str(id_card).strip().upper()
    raw = "".join(raw.split())
    cyr_to_lat = str.maketrans("АВЕКМНОРСТХ", "ABEKMHOPCTX")
    lat_to_cyr = str.maketrans("ABEKMHOPCTX", "АВЕКМНОРСТХ")
    var_lat = raw.translate(cyr_to_lat)
    var_cyr = raw.translate(lat_to_cyr)
    variants = list(set([raw, var_lat, var_cyr]))

    async with async_session() as session:
        query = select(User).where(or_(*[func.upper(User.idcards) == v for v in variants]))
        result = await session.execute(query)
        return result.scalar_one_or_none()

async def activate_resident_user(resident_id: int, max_id: int, language: str = 'RU') -> User:
    """
    Привязывает max_id к жильцу и сохраняет выбранный язык.
    """
    async with async_session() as session:
        stmt = update(User).where(User.id == resident_id).values(
            max_id=max_id,
            language=language
        )
        await session.execute(stmt)
        await session.commit()

        result = await session.execute(select(User).where(User.id == resident_id))
        return result.scalar_one()

async def update_user_language(max_id: int, new_language: str):
    """
    Обновляет язык для зарегистрированного пользователя MAX.
    """
    async with async_session() as session:
        stmt = update(User).where(User.max_id == max_id).values(language=new_language)
        await session.execute(stmt)
        await session.commit()

async def get_all_users_with_max(dormitory_id: Optional[int] = None):
    """
    Возвращает список (max_id, language) всех пользователей MAX, у которых включены уведомления.
    """
    async with async_session() as session:
        query = select(User.max_id, User.language).where(
            User.max_id.is_not(None),
            User.notify_unconfirmed.is_(True)
        )
        if dormitory_id:
            query = query.where(User.dormitory_id == dormitory_id)
        result = await session.execute(query)
        return result.all()


async def get_user_notify_status_by_max(max_id: int) -> bool:
    """Возвращает статус подписки на уведомления о свободных слотах для пользователя MAX."""
    async with async_session() as session:
        query = select(User.notify_unconfirmed).where(User.max_id == max_id)
        result = await session.execute(query)
        val = result.scalar_one_or_none()
        return True if val is None else bool(val)


async def toggle_user_notify_by_max(max_id: int) -> bool:
    """Переключает статус подписки на уведомления о свободных слотах и возвращает новое значение."""
    async with async_session() as session:
        query = select(User).where(User.max_id == max_id)
        result = await session.execute(query)
        user = result.scalar_one_or_none()
        if user:
            current = user.notify_unconfirmed if user.notify_unconfirmed is not None else True
            user.notify_unconfirmed = not current
            new_status = user.notify_unconfirmed
            await session.commit()
            return new_status
        return True


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
        raise ValueError("Нельзя забронировать время, которое уже прошло")

    end_time = start_time + timedelta(minutes=duration_minutes)

    async with async_session() as session:
        # Блокируем строку машины для предотвращения race condition при параллельной быстрой записи
        machine_query = select(Machine).where(Machine.id == machine_id).with_for_update()
        machine_obj = (await session.execute(machine_query)).scalar_one_or_none()
        if not machine_obj:
            raise ValueError("Машина не найдена")

        # Проверяем занятость слота строго внутри транзакции
        conflict_query = select(Booking.id).where(
            Booking.inidmachine == machine_id,
            Booking.status != 'Отменено',
            or_(
                and_(Booking.start_time <= start_time, Booking.end_time > start_time),
                and_(Booking.start_time < end_time, Booking.end_time >= end_time),
                and_(Booking.start_time >= start_time, Booking.end_time <= end_time)
            )
        ).limit(1)
        conflict = (await session.execute(conflict_query)).scalar_one_or_none()
        if conflict:
            raise ValueError("Слот уже занят")

        machine_type = machine_obj.type_machine
        dorm_id = dormitory_id or getattr(machine_obj, "dormitory_id", 1) or 1

        if await has_weekly_booking(user_id, start_time, machine_type):
            raise ValueError(f"Лимит: не более 1 брони типа '{machine_type}' в неделю.")

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

        booking_with_relations = await session.execute(
            select(Booking)
            .options(joinedload(Booking.user), joinedload(Booking.machine))
            .where(Booking.id == booking.id)
        )
        booking = booking_with_relations.scalar_one()

        return {
            "booking": booking,
            "machine_type": machine_type
        }

async def cancel_booking(booking_id: int, user_id: Optional[int] = None) -> bool:
    """Отменяет бронь, устанавливая статус 'Отменено'."""
    async with async_session() as session:
        query = select(Booking).where(Booking.id == booking_id)
        if user_id:
            query = query.where(Booking.inidresidents == user_id)
        result = await session.execute(query)
        booking = result.scalar_one_or_none()

        if booking:
            booking.status = 'Отменено'
            booking.reminded_tg = True
            booking.reminded_vk = True
            booking.reminded_max = True
            booking.canceled_notified_tg = True
            booking.canceled_notified_vk = True
            booking.canceled_notified_max = True
            booking.is_autocanceled = False
            await session.commit()
            return True
        return False

async def get_user_bookings(user_id: int) -> List[Booking]:
    async with async_session() as session:
        query = (
            select(Booking)
            .options(joinedload(Booking.machine))
            .where(
                Booking.inidresidents == user_id,
                Booking.status != 'Отменено',
                Booking.end_time > get_kemerovo_now()
            )
            .order_by(Booking.start_time)
        )
        result = await session.execute(query)
        return result.scalars().all()

# ==========================================
# КАЛЕНДАРЬ И НАГРУЗКА
# ==========================================

async def get_month_workload(year: int, month: int, machine_type: Optional[str] = None, dormitory_id: Optional[int] = None) -> dict:
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

async def get_range_workload(start_date: datetime, end_date: datetime, machine_type: Optional[str] = None, dormitory_id: Optional[int] = None) -> dict:
    async with async_session() as session:
        query = (
            select(
                cast(Booking.start_time, Date).label('day'),
                func.count(Booking.id).label('count')
            )
            .join(Machine, Booking.inidmachine == Machine.id)
            .where(
                Booking.start_time >= start_date,
                Booking.start_time < end_date,
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
    async with async_session() as session:
        conditions = [Machine.status == MACHINE_STATUS_ACTIVE]
        if machine_type:
            conditions.append(Machine.type_machine == machine_type)
        if dormitory_id:
            conditions.append(Machine.dormitory_id == dormitory_id)

        query = select(func.count(Machine.id)).where(and_(*conditions))
        active_machines = (await session.execute(query)).scalar() or 0

    slots_per_machine = 10  # 8:00 - 23:00 / 90 min = 10 слотов
    return active_machines * slots_per_machine

async def get_available_machines(start_time: datetime, machine_type: str, dormitory_id: Optional[int] = None) -> List[Machine]:
    if start_time <= get_kemerovo_now():
        return []

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
    now = get_kemerovo_now()

    while current_slot + timedelta(minutes=slot_duration) <= end_of_day:
        if current_slot <= now:
            current_slot += timedelta(minutes=slot_duration)
            continue

        slot_end = current_slot + timedelta(minutes=slot_duration)

        busy_count = 0
        for b in bookings:
            if b.start_time < slot_end and b.end_time > current_slot:
                busy_count += 1

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
    async with async_session() as session:
        query = (
            select(Booking)
            .options(joinedload(Booking.machine), joinedload(Booking.user))
            .where(Booking.id == booking_id)
        )
        result = await session.execute(query)
        return result.scalar_one_or_none()

async def get_bookings_to_remind_max(minutes_before: int = 60, minutes_deadline: int = 30):
    """Ищет записи, до начала которых от minutes_deadline до minutes_before минут, и которым еще не отправлялось напоминание в MAX."""
    now = get_kemerovo_now()
    max_time = now + timedelta(minutes=minutes_before)
    min_time = now + timedelta(minutes=minutes_deadline)

    async with async_session() as session:
        query = select(Booking).options(joinedload(Booking.user), joinedload(Booking.machine)).where(
            and_(
                Booking.start_time <= max_time,
                Booking.start_time > min_time,
                Booking.status.in_(['Ожидание', 'Ожидание подтверждения', None]),
                or_(Booking.reminded_max == False, Booking.reminded_max == None)
            )
        )
        result = await session.execute(query)
        return result.scalars().all()

async def mark_booking_reminded_max(booking_id: int):
    """Отмечает, что напоминание в MAX отправлено, и переводит статус в 'Ожидание подтверждения'."""
    async with async_session() as session:
        query = update(Booking).where(Booking.id == booking_id).values(
            reminded_max=True,
            status=case((Booking.status == 'Ожидание', 'Ожидание подтверждения'), else_=Booking.status)
        )
        await session.execute(query)
        await session.commit()

async def get_expired_unconfirmed_bookings_to_cancel(minutes_before_deadline: int = 30):
    """Ищет записи, до начала которых осталось <= minutes_before_deadline (30 мин), и которые еще не подтверждены."""
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

async def autocancel_booking(booking_id: int, platform: str = "max"):
    """Отменяет бронь по автоотмене с фиксацией платформы."""
    values = {
        "status": "Отменено",
        "is_autocanceled": True,
    }
    if platform == "tg":
        values["canceled_notified_tg"] = True
    elif platform == "vk":
        values["canceled_notified_vk"] = True
    elif platform == "max":
        values["canceled_notified_max"] = True

    async with async_session() as session:
        query = update(Booking).where(Booking.id == booking_id).values(**values)
        await session.execute(query)
        await session.commit()

async def get_autocanceled_to_notify_max():
    """Ищет автоотмененные записи, по которым еще не отправлено уведомление в MAX."""
    now = get_kemerovo_now()
    async with async_session() as session:
        query = select(Booking).options(joinedload(Booking.machine), joinedload(Booking.user)).where(
            and_(
                Booking.is_autocanceled == True,
                or_(Booking.canceled_notified_max == False, Booking.canceled_notified_max == None),
                Booking.start_time >= now - timedelta(minutes=60),
                Booking.start_time <= now + timedelta(minutes=35)
            )
        )
        result = await session.execute(query)
        return result.scalars().all()

async def mark_autocanceled_notified_max(booking_id: int):
    """Отмечает, что уведомление об автоотмене отправлено в MAX."""
    async with async_session() as session:
        query = update(Booking).where(Booking.id == booking_id).values(canceled_notified_max=True)
        await session.execute(query)
        await session.commit()

async def get_bookings_to_notify_finish_max():
    """Ищет активные записи, которые завершаются в ближайшие 15 минут, и жителю еще не отправлено уведомление об окончании в MAX."""
    now = get_kemerovo_now()
    async with async_session() as session:
        query = select(Booking).options(joinedload(Booking.machine), joinedload(Booking.user)).where(
            and_(
                Booking.end_time <= now + timedelta(minutes=15),
                Booking.end_time > now - timedelta(minutes=2),
                Booking.status != 'Отменено',
                or_(Booking.finish_notified_max == False, Booking.finish_notified_max == None)
            )
        )
        result = await session.execute(query)
        return result.scalars().all()

async def mark_booking_finish_notified_max(booking_id: int):
    """Отмечает, что уведомление об окончании стирки отправлено в MAX."""
    async with async_session() as session:
        query = update(Booking).where(Booking.id == booking_id).values(finish_notified_max=True)
        await session.execute(query)
        await session.commit()


async def get_autocancel_penalty_info(booking_id: int):
    """Возвращает информацию о примененном штрафе за автоотмену для формирования текста уведомления."""
    from app.db.models.score_log import ResidentScoreLog
    async with async_session() as session:
        query = select(ResidentScoreLog).where(
            ResidentScoreLog.booking_id == booking_id,
            ResidentScoreLog.reason == "AUTOCANCEL_MISSED"
        ).order_by(ResidentScoreLog.id.desc()).limit(1)
        res = (await session.execute(query)).scalar_one_or_none()
        if res:
            user = (await session.execute(select(User).where(User.id == res.resident_id))).scalar_one_or_none()
            return {
                "delta": res.delta,
                "new_score": res.score_after,
                "is_banned": getattr(user, "is_banned", False) if user else False
            }
        return None

# Совместимость со старым API
async def get_bookings_to_remind(minutes_before: int = 60, minutes_deadline: int = 30):
    return await get_bookings_to_remind_max(minutes_before, minutes_deadline)

async def set_booking_status(booking_id: int, status: str):
    async with async_session() as session:
        query = update(Booking).where(Booking.id == booking_id).values(status=status)
        await session.execute(query)
        await session.commit()

async def get_expired_unconfirmed_bookings(minutes_before_deadline: int = 30):
    return await get_expired_unconfirmed_bookings_to_cancel(minutes_before_deadline)

async def has_weekly_booking(user_id: int, target_date: datetime, machine_type: Optional[str] = None) -> bool:
    """
    Проверяет, есть ли у пользователя запись на той же календарной неделе (пн-вс), что и target_date.
    Не отмененные записи блокируют повторную бронь (лимит: 1 раз в неделю).
    """
    week_day = target_date.weekday()
    start_of_week = target_date - timedelta(days=week_day)
    start_of_week = start_of_week.replace(hour=0, minute=0, second=0, microsecond=0)

    end_of_week = start_of_week + timedelta(days=6)
    end_of_week = end_of_week.replace(hour=23, minute=59, second=59, microsecond=999999)

    async with async_session() as session:
        query = select(func.count(Booking.id)).where(
            Booking.inidresidents == user_id,
            Booking.status != 'Отменено',
            Booking.start_time >= start_of_week,
            Booking.start_time <= end_of_week
        )

        if machine_type:
            query = query.join(Machine, Booking.inidmachine == Machine.id).where(
                Machine.type_machine == machine_type
            )

        result = await session.execute(query)
        count = result.scalar() or 0
        return count > 0
