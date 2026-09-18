from datetime import datetime
from sqlalchemy import Text, Integer, DateTime, ForeignKey, BigInteger
from sqlalchemy.orm import Mapped, mapped_column, relationship
from app.db.base import Base


from app.bot.utils.timezone import get_kemerovo_now


class Notification(Base):
    __tablename__ = "notifications"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    id_residents: Mapped[int] = mapped_column(BigInteger, ForeignKey("residents.id"), nullable=True)
    id_machines: Mapped[int] = mapped_column(Integer, ForeignKey("machines.id"), nullable=True)
    create_date: Mapped[datetime] = mapped_column(DateTime, default=get_kemerovo_now)
    description: Mapped[str] = mapped_column(Text, nullable=True)

    resident = relationship("Resident", lazy="joined")
    machine = relationship("Machine", lazy="joined")
