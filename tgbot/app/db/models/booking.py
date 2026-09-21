from sqlalchemy.orm import Mapped, mapped_column, relationship
from sqlalchemy import BigInteger, Integer, DateTime, String, ForeignKey, Boolean
from datetime import datetime
from app.db.models.dormitory import Dormitory
from app.db.models.machine import Machine
from app.db.models.residents import Resident
from app.db.base import Base

class Booking(Base):
    __tablename__ = "booking"

    id: Mapped[int] = mapped_column(BigInteger, primary_key=True, autoincrement=True)
    dormitory_id: Mapped[int] = mapped_column(Integer, ForeignKey("dormitories.id"), default=1, nullable=False)
    inidresidents: Mapped[int] = mapped_column(BigInteger, ForeignKey("residents.id"))
    inidmachine: Mapped[int] = mapped_column(Integer, ForeignKey("machines.id"), nullable=False)
    
    start_time: Mapped[datetime] = mapped_column(DateTime, nullable=False)
    end_time: Mapped[datetime] = mapped_column(DateTime, nullable=False)
    status: Mapped[str] = mapped_column(String, nullable=True)

    reminded_tg: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    reminded_vk: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    reminded_max: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    is_autocanceled: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    canceled_notified_tg: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    canceled_notified_vk: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)
    canceled_notified_max: Mapped[bool] = mapped_column(Boolean, default=False, nullable=True)

    machine: Mapped["Machine"] = relationship("Machine")
    user: Mapped["Resident"] = relationship("Resident")
    dormitory = relationship("Dormitory", lazy="joined")