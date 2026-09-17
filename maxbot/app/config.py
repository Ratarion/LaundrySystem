import os
from dotenv import load_dotenv
from pathlib import Path

load_dotenv()

root_path = Path(__file__).resolve().parents[1]

MAX_TOKEN = os.getenv("MAX_TOKEN", "")

# База данных
DATABASE_URL = os.getenv("DATABASE_URL")
if not DATABASE_URL:
    user = os.getenv("DB_USER", os.getenv("USER", "postgres"))
    password = os.getenv("DB_PASS", os.getenv("PASSWORD", "postgres"))
    host = os.getenv("DB_HOST", os.getenv("HOST", "localhost"))
    port = os.getenv("DB_PORT", os.getenv("PORT", "5432"))
    dbname = os.getenv("DB_NAME", os.getenv("DBNAME", "layndaru_db"))
    DATABASE_URL = f"postgresql+asyncpg://{user}:{password}@{host}:{port}/{dbname}"
