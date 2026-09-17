import sys
import asyncio
import os
from dotenv import load_dotenv
from sqlalchemy.ext.asyncio import AsyncAttrs, create_async_engine, async_sessionmaker
from sqlalchemy.orm import DeclarativeBase
from sqlalchemy import text
from sqlalchemy.pool import NullPool

load_dotenv()

USER = os.getenv("USER", "postgres")
PASSWORD = os.getenv("PASSWORD", "postgres")
HOST = os.getenv("HOST", "localhost")
PORT = os.getenv("PORT", "5432")
DBNAME = os.getenv("DBNAME", "layndaru_db")

# Формируем URL (если DATABASE_URL задан в .env — используем его, иначе собираем)
DATABASE_URL = os.getenv("DATABASE_URL") or f"postgresql+asyncpg://{USER}:{PASSWORD}@{HOST}:{PORT}/{DBNAME}"

# Создаем движок с NullPool
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
            # await conn.run_sync(Base.metadata.create_all)
            pass
    except Exception as e:
        print(f"Ошибка при инициализации БД: {e}")
        raise

# Функция тестирования соединения
async def test_connection():
    print(f"Попытка подключения к {HOST}:{PORT}...")
    try:
        async with engine.connect() as conn:
            # Простой запрос для проверки
            result = await conn.execute(text("SELECT version()"))
            version = result.scalar()
            print(f"✅ УСПЕХ! Соединение установлено.\nВерсия БД: {version}")
    except Exception as e:
        print(f"❌ ОШИБКА соединения: {e}")

if __name__ == "__main__":
    # --- ФИКС ДЛЯ WINDOWS (обязателен для локальных тестов) ---
    if sys.platform == 'win32':
        asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())
    # ----------------------------------------------------------
    
    asyncio.run(test_connection())