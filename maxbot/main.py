import os
import time
import asyncio
import logging
import sys

# Установка часового пояса Кемерово (КузГТУ, UTC+7)
os.environ['TZ'] = os.getenv('TZ', 'Asia/Novokuznetsk')
if hasattr(time, 'tzset'):
    time.tzset()


import app.bot.patch_aiomax  # Monkey-patch aiomax Callback.answer to fix errors.required on popups
from app.bot.loader import bot
from app.bot.handlers.auth import auth_router
from app.bot.handlers.booking import booking_router
from app.bot.handlers.records import records_router
from app.bot.handlers.cancel_record import cancel_record_router
from app.bot.handlers.confirmation import confirm_router
from app.bot.handlers.report import report_router
from app.bot.handlers.discipline import discipline_router
from app.bot.utils.scheduler import start_scheduler
from app.db.base import init_db

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s"
)


async def main():
    await init_db()
    logging.info("[MaxBot] База данных подключена успешно")

    if not bot:
        logging.warning("[MaxBot] MAX_TOKEN не указан в .env. MaxBot находится в режиме ожидания (idle).")
        while True:
            await asyncio.sleep(3600)

    bot.add_router(auth_router)
    bot.add_router(booking_router)
    bot.add_router(records_router)
    bot.add_router(cancel_record_router)
    bot.add_router(confirm_router)
    bot.add_router(report_router)
    bot.add_router(discipline_router)

    start_scheduler(bot)

    logging.info("[MaxBot] Запуск polling Max Bot API (max.ru)...")
    await bot.start_polling()


if __name__ == '__main__':
    if sys.platform == 'win32':
        asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())
    try:
        asyncio.run(main())
    except (KeyboardInterrupt, SystemExit):
        logging.info("[MaxBot] Бот остановлен пользователем")
