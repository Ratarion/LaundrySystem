from sqlalchemy import String, BigInteger, Integer, Boolean
from sqlalchemy.orm import Mapped, mapped_column
from typing import Optional
from app.db.base import Base

class Resident(Base):
    __tablename__ = "residents"

    id: Mapped[int] = mapped_column(BigInteger, primary_key=True, autoincrement=True)
    dormitory_id: Mapped[int] = mapped_column(Integer, default=1, nullable=False)
    inidroom: Mapped[Optional[int]] = mapped_column(Integer, nullable=True)
    idcards: Mapped[Optional[str]] = mapped_column(String(50), unique=True, nullable=True)
    tg_id: Mapped[Optional[int]] = mapped_column(BigInteger, unique=False, nullable=True)
    vk_id: Mapped[Optional[int]] = mapped_column(BigInteger, unique=False, nullable=True)
    max_id: Mapped[Optional[int]] = mapped_column(BigInteger, unique=False, nullable=True)
    last_name: Mapped[Optional[str]] = mapped_column(String, nullable=True)
    first_name: Mapped[Optional[str]] = mapped_column(String, nullable=True)
    patronymic: Mapped[Optional[str]] = mapped_column(String, nullable=True)
    language: Mapped[str] = mapped_column(String, default='RU', nullable=True)
    notify_unconfirmed: Mapped[bool] = mapped_column(Boolean, default=True, nullable=False)
    is_banned: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)