import os
from dotenv import load_dotenv, find_dotenv
from pathlib import Path

load_dotenv()  # Загрузка переменных окружения из .env (если есть)

root_path = Path(__file__).resolve().parents[1]

BOT_TOKEN = os.getenv("BOT_TOKEN")
DATABASE_URL  = os.getenv("DATABASE_URL")