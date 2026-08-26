from sqlalchemy import String, BigInteger, Integer
from sqlalchemy.orm import Mapped, mapped_column
from app.db.base import Base


class Resident(Base):
    __tablename__ = "residents"

    id: Mapped[int] = mapped_column(BigInteger, primary_key=True, autoincrement=True)
    inidroom: Mapped[int] = mapped_column(Integer, nullable=False)
    idcards: Mapped[int] = mapped_column(Integer, nullable=False)
    # Telegram ID жильца. Колонка общая с tg-ботом (та же таблица в той же БД).
    # Не используется VK-ботом, но обязательно должна остаться в модели,
    # иначе SQLAlchemy не будет знать про существующую колонку таблицы.
    tg_id: Mapped[int] = mapped_column(BigInteger, unique=False, nullable=True)
    # ID пользователя ВКонтакте. Отдельная колонка, чтобы один и тот же
    # жилец мог быть привязан и к Telegram, и к VK одновременно, не мешая
    # друг другу (id в TG и VK — разные пространства чисел).
    #
    # ВАЖНО: колонки vk_id в таблице residents изначально нет — её нужно
    # один раз добавить в БД вручную (см. migrations/001_add_vk_id.sql).
    vk_id: Mapped[int] = mapped_column(BigInteger, unique=False, nullable=True)
    last_name: Mapped[str] = mapped_column(String, nullable=False)
    first_name: Mapped[str] = mapped_column(String, nullable=False)
    patronymic: Mapped[str] = mapped_column(String, nullable=False)
    language: Mapped[str] = mapped_column(String, default='RU', nullable=False)
