from app.db.models.dormitory import Dormitory
from app.db.models.room import rooms
from app.db.models.residents import Resident
from app.db.models.machine import Machine
from app.db.models.booking import Booking
from app.db.models.notification import Notification
from app.db.models.score_log import ResidentScoreLog

__all__ = [
    'Dormitory',
    'rooms',
    'Resident',
    'Machine',
    'Booking',
    'Notification',
    'ResidentScoreLog',
]
