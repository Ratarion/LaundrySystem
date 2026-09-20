from sqlalchemy import String, BigInteger, Integer, Boolean
from sqlalchemy.orm import Mapped, mapped_column
from app.db.base import Base


class Resident(Base):
    __tablename__ = "residents"

    id: Mapped[int] = mapped_column(BigInteger, primary_key=True, autoincrement=True)
    dormitory_id: Mapped[int] = mapped_column(Integer, default=1, nullable=False)
    inidroom: Mapped[int] = mapped_column(Integer, nullable=True)
    idcards: Mapped[str] = mapped_column(String(50), nullable=True, unique=True)
    tg_id: Mapped[int] = mapped_column(BigInteger, unique=False, nullable=True)
    vk_id: Mapped[int] = mapped_column(BigInteger, unique=False, nullable=True)
    max_id: Mapped[int] = mapped_column(BigInteger, unique=False, nullable=True)
    last_name: Mapped[str] = mapped_column(String, nullable=True)
    first_name: Mapped[str] = mapped_column(String, nullable=True)
    patronymic: Mapped[str] = mapped_column(String, nullable=True)
    language: Mapped[str] = mapped_column(String, default='RU', nullable=True)
    notify_unconfirmed: Mapped[bool] = mapped_column(Boolean, default=True, nullable=False)
    is_banned: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    score: Mapped[int] = mapped_column(Integer, default=100, nullable=False)
    confirm_streak: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    miss_streak: Mapped[int] = mapped_column(Integer, default=0, nullable=False)

