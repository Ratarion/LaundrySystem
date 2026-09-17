from sqlalchemy import String, Integer, SmallInteger
from sqlalchemy.orm import Mapped, mapped_column
from app.db.base import Base

MACHINE_STATUS_ACTIVE = 1
MACHINE_STATUS_INACTIVE = 0


class Machine(Base):
    __tablename__ = "machines"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    dormitory_id: Mapped[int] = mapped_column(Integer, default=1, nullable=False)
    type_machine: Mapped[str] = mapped_column(String, nullable=False)
    number_machine: Mapped[int] = mapped_column(Integer, nullable=True)
    status: Mapped[int] = mapped_column(SmallInteger, default=MACHINE_STATUS_ACTIVE, nullable=True)
