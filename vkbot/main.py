import os
import time
import asyncio
import logging

# Установка часового пояса Кемерово (КузГТУ, UTC+7)
os.environ['TZ'] = os.getenv('TZ', 'Asia/Novokuznetsk')
if hasattr(time, 'tzset'):
    time.tzset()


from vkbottle import VKAPIError

from app.bot.loader import bot
from app.bot.handlers.auth import auth_labeler
from app.bot.handlers.booking import booking_labeler
from app.bot.handlers.records import records_labeler
from app.bot.handlers.report import report_labeler
from app.bot.handlers.cancel_record import cancel_record_labeler
from app.bot.handlers.confirmation import confirm_labeler
from app.bot.utils.scheduler import start_scheduler
from app.db.base import init_db

logging.basicConfig(level=logging.INFO)


async def main():
    await init_db()

    # Порядок важен для "back_to_sections": единственный хендлер на этот
    # payload лежит в booking_labeler, поэтому он должен быть загружен.
    bot.on.load(auth_labeler)
    bot.on.load(booking_labeler)
    bot.on.load(records_labeler)
    bot.on.load(report_labeler)
    bot.on.load(cancel_record_labeler)
    bot.on.load(confirm_labeler)

    start_scheduler(bot)

    max_retries = 5
    for attempt in range(1, max_retries + 1):
        try:
            logging.info(f"[Bot] Starting polling, attempt {attempt}")
            await bot.run_polling()
            break
        except VKAPIError as e:
            logging.error(f"[Bot] VK API error on attempt {attempt}: {e}")
            if attempt < max_retries:
                wait_time = 10 * attempt
                logging.info(f"[Bot] Retrying after {wait_time} seconds...")
                await asyncio.sleep(wait_time)
            else:
                logging.error("[Bot] Max retries reached, exiting.")
                raise
        except Exception as exc:
            logging.error(f"[Bot] Unexpected error: {exc}")
            if attempt < max_retries:
                wait_time = 10 * attempt
                await asyncio.sleep(wait_time)
            else:
                raise


if __name__ == '__main__':
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("[Bot] Бот остановлен")
