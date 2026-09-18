from datetime import datetime, time, date, timedelta
from zoneinfo import ZoneInfo

KEMEROVO_TZ = ZoneInfo("Asia/Novokuznetsk")

def get_kemerovo_now() -> datetime:
    """
    Возвращает наивный datetime в кемеровском времени (UTC+7, Asia/Novokuznetsk).
    Идеально совместим с полями базы данных TIMESTAMP WITHOUT TIME ZONE.
    """
    return datetime.now(KEMEROVO_TZ).replace(tzinfo=None)

def get_kemerovo_today() -> date:
    """Возвращает текущую дату в кемеровском времени."""
    return get_kemerovo_now().date()
