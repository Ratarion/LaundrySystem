import sys
import asyncio
from sqlalchemy.ext.asyncio import AsyncAttrs, create_async_engine, async_sessionmaker
from sqlalchemy.orm import DeclarativeBase
from sqlalchemy.pool import NullPool

from app.config import DATABASE_URL

engine = create_async_engine(
    DATABASE_URL,
    poolclass=NullPool,
    pool_pre_ping=True,
    echo=False
)

async_session = async_sessionmaker(engine, expire_on_commit=False)


class Base(AsyncAttrs, DeclarativeBase):
    pass


async def init_db():
    try:
        async with engine.begin() as conn:
            pass
    except Exception as e:
        print(f"Ошибка при подключении к БД: {e}")
        raise
