from app.db.models.dormitory import Dormitory
from app.db.models.room import Room
from app.db.models.residents import Resident
from app.db.models.machine import Machine
from app.db.models.booking import Booking
from app.db.models.notification import Notification
from app.db.models.score_log import ResidentScoreLog

__all__ = [
    'Dormitory',
    'Room',
    'Resident',
    'Machine',
    'Booking',
    'Notification',
    'ResidentScoreLog',
]
