from datetime import datetime
from sqlalchemy import String, Integer, DateTime, ForeignKey, BigInteger, Boolean
from sqlalchemy.orm import Mapped, mapped_column, relationship
from app.db.base import Base
from app.db.models.dormitory import Dormitory
from app.db.models.machine import Machine
from app.db.models.residents import Resident


class Booking(Base):
    __tablename__ = "booking"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    dormitory_id: Mapped[int] = mapped_column(Integer, ForeignKey("dormitories.id"), default=1, nullable=False)
    inidresidents: Mapped[int] = mapped_column(BigInteger, ForeignKey("residents.id"), nullable=True)
    inidmachine: Mapped[int] = mapped_column(Integer, ForeignKey("machines.id"), nullable=True)
    start_time: Mapped[datetime] = mapped_column(DateTime, nullable=True)
    end_time: Mapped[datetime] = mapped_column(DateTime, nullable=True)
    status: Mapped[str] = mapped_column(String, default='Ожидание', nullable=True)

    reminded_tg: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    reminded_vk: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    reminded_max: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    is_autocanceled: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    canceled_notified_tg: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    canceled_notified_vk: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    canceled_notified_max: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    finish_notified_tg: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    finish_notified_vk: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    finish_notified_max: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)

    user = relationship("Resident", lazy="joined")
    machine = relationship("Machine", lazy="joined")
    dormitory = relationship("Dormitory", lazy="joined")
