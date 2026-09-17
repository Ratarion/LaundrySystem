from sqlalchemy import String, Integer, SmallInteger
from sqlalchemy.orm import Mapped, mapped_column
from app.db.base import Base

# В реальной таблице machines колонка status — smallint (не varchar!).
# 1 = машина активна/работает. Другое значение (или NULL) = недоступна.
MACHINE_STATUS_ACTIVE = 1


class Machine(Base):
    __tablename__ = "machines"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    dormitory_id: Mapped[int] = mapped_column(Integer, default=1, nullable=False)
    type_machine: Mapped[str] = mapped_column(String, nullable=False)
    number_machine: Mapped[int] = mapped_column(Integer, nullable=False)
    status: Mapped[int] = mapped_column(SmallInteger, default=MACHINE_STATUS_ACTIVE, nullable=False)