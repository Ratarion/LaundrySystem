import logging
from datetime import datetime
from typing import Optional, Dict, Any, List
from sqlalchemy import select, desc
from app.db.base import async_session
from app.db.models.residents import Resident
from app.db.models.score_log import ResidentScoreLog

logger = logging.getLogger(__name__)

EVENT_CONFIRM_ON_TIME = "CONFIRMED_ON_TIME"
EVENT_AUTOCANCEL_MISSED = "AUTOCANCEL_MISSED"
EVENT_EARLY_CANCEL = "EARLY_CANCEL"
EVENT_DECLINE_ON_CONFIRM = "DECLINE_ON_CONFIRM"
EVENT_ADMIN_ADJUSTMENT = "ADMIN_ADJUSTMENT"


def get_rank_info(score: int, confirm_streak: int = 0) -> Dict[str, str]:
    """Возвращает информацию о ранге жильца на 3 языках."""
    if score >= 140 and confirm_streak >= 5:
        return {
            "key": "master",
            "badge": "💎",
            "RU": "Мастер чистоты",
            "ENG": "Master of Cleanliness",
            "CN": "洁净大师"
        }
    elif score >= 90:
        return {
            "key": "disciplined",
            "badge": "🟢",
            "RU": "Дисциплинированный",
            "ENG": "Disciplined",
            "CN": "自律住户"
        }
    elif score >= 50:
        return {
            "key": "warning",
            "badge": "🟡",
            "RU": "Зона внимания",
            "ENG": "Attention Zone",
            "CN": "关注等级"
        }
    elif score >= 20:
        return {
            "key": "critical",
            "badge": "🟠",
            "RU": "Критический уровень",
            "ENG": "Critical",
            "CN": "危险等级"
        }
    elif score > -1000:
        return {
            "key": "penalty",
            "badge": "🔴",
            "RU": "Штрафник",
            "ENG": "Penalty Zone",
            "CN": "受罚等级"
        }
    else:
        return {
            "key": "banned",
            "badge": "🚫",
            "RU": "Заблокирован (-1000 б.)",
            "ENG": "Banned (-1000 pts)",
            "CN": "已封禁"
        }


async def apply_discipline_event(
    resident_id: int,
    booking_id: Optional[int],
    event_type: str,
    custom_delta: Optional[int] = None,
    details: Optional[str] = None
) -> Optional[Dict[str, Any]]:
    """
    Применяет событие дисциплины (начисление / штраф / сброс) к жителю.
    Гарантирует идемпотентность через resident_score_logs (booking_id + reason).
    """
    async with async_session() as session:
        # 1. Проверяем идемпотентность (только если есть booking_id)
        if booking_id:
            check_q = select(ResidentScoreLog).where(
                ResidentScoreLog.booking_id == booking_id,
                ResidentScoreLog.reason == event_type
            )
            existing = (await session.execute(check_q)).scalar_one_or_none()
            if existing:
                logger.info(f"Discipline event {event_type} for booking {booking_id} already processed. Skipping.")
                return None

        # 2. Получаем жителя
        res_q = select(Resident).where(Resident.id == resident_id)
        resident = (await session.execute(res_q)).scalar_one_or_none()
        if not resident:
            logger.warning(f"Resident {resident_id} not found for discipline event {event_type}")
            return None

        old_score = resident.score if resident.score is not None else 100
        confirm_streak = resident.confirm_streak if resident.confirm_streak is not None else 0
        miss_streak = resident.miss_streak if resident.miss_streak is not None else 0

        delta = 0
        bonus = 0

        # 3. Расчет начисления / списания
        if event_type == EVENT_CONFIRM_ON_TIME:
            confirm_streak += 1
            miss_streak = 0
            base_points = 5

            if confirm_streak >= 10:
                bonus = 7
            elif confirm_streak >= 5:
                bonus = 5
            elif confirm_streak >= 3:
                bonus = 3
            else:
                bonus = 0

            delta = base_points + bonus
            if not details:
                details = f"Подтверждение вовремя (Серия: 🔥 {confirm_streak})"

        elif event_type == EVENT_AUTOCANCEL_MISSED:
            miss_streak += 1
            confirm_streak = 0

            if miss_streak >= 3:
                delta = -40
            elif miss_streak == 2:
                delta = -25
            else:
                delta = -15

            if not details:
                details = f"Пропуск записи без отмены (Серия пропусков: ⚠️ {miss_streak})"

        elif event_type == EVENT_EARLY_CANCEL:
            delta = 2
            miss_streak = 0
            # confirm_streak не сбрасывается (замораживается)
            if not details:
                details = "Заблаговременная отмена (освобождение слота)"

        elif event_type == EVENT_DECLINE_ON_CONFIRM:
            delta = 1
            miss_streak = 0
            # confirm_streak не сбрасывается
            if not details:
                details = "Отказ в окне подтверждения (слот передан другим)"

        elif event_type == EVENT_ADMIN_ADJUSTMENT:
            delta = custom_delta if custom_delta is not None else 0
            if not details:
                details = "Корректировка администратором"

        # 4. Обновляем показатели
        new_score = max(-1000, min(200, old_score + delta))
        resident.score = new_score
        resident.confirm_streak = confirm_streak
        resident.miss_streak = miss_streak

        # Автобан при падении до -1000 баллов
        if new_score <= -1000:
            resident.is_banned = True

        # 5. Записываем в лог
        score_log = ResidentScoreLog(
            resident_id=resident_id,
            booking_id=booking_id,
            delta=delta,
            score_after=new_score,
            reason=event_type,
            details=details,
            created_at=datetime.utcnow()
        )
        session.add(score_log)

        await session.commit()

        if resident.is_banned:
            rank = {
                "key": "banned",
                "badge": "🚫",
                "RU": "Заблокирован",
                "ENG": "Banned",
                "CN": "已封禁"
            }
        else:
            rank = get_rank_info(new_score, confirm_streak)

        return {
            "resident_id": resident_id,
            "old_score": old_score,
            "new_score": new_score,
            "delta": delta,
            "bonus": bonus,
            "confirm_streak": confirm_streak,
            "miss_streak": miss_streak,
            "event_type": event_type,
            "details": details,
            "rank": rank,
            "is_banned": resident.is_banned
        }


async def get_resident_discipline_card(resident_id: int, limit_history: int = 5) -> Optional[Dict[str, Any]]:
    """Возвращает данные о рейтинге жителя и последние операции."""
    async with async_session() as session:
        res_q = select(Resident).where(Resident.id == resident_id)
        resident = (await session.execute(res_q)).scalar_one_or_none()
        if not resident:
            return None

        score = resident.score if resident.score is not None else 100
        confirm_streak = resident.confirm_streak if resident.confirm_streak is not None else 0
        miss_streak = resident.miss_streak if resident.miss_streak is not None else 0

        logs_q = (
            select(ResidentScoreLog)
            .where(ResidentScoreLog.resident_id == resident_id)
            .order_by(desc(ResidentScoreLog.created_at))
            .limit(limit_history)
        )
        logs_res = (await session.execute(logs_q)).scalars().all()

        history = []
        for l in logs_res:
            history.append({
                "id": l.id,
                "delta": l.delta,
                "score_after": l.score_after,
                "reason": l.reason,
                "details": l.details,
                "created_at": l.created_at
            })

        if resident.is_banned:
            rank = {
                "key": "banned",
                "badge": "🚫",
                "RU": "Заблокирован",
                "ENG": "Banned",
                "CN": "已封禁"
            }
        else:
            rank = get_rank_info(score, confirm_streak)

        return {
            "resident_id": resident.id,
            "name": f"{resident.last_name or ''} {resident.first_name or ''}".strip(),
            "score": score,
            "confirm_streak": confirm_streak,
            "miss_streak": miss_streak,
            "rank": rank,
            "is_banned": resident.is_banned,
            "history": history
        }
