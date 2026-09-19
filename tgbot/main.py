import os
import time
import aiohttp
import asyncio
import logging

# Установка часового пояса Кемерово (КузГТУ, UTC+7)
os.environ['TZ'] = os.getenv('TZ', 'Asia/Novokuznetsk')
if hasattr(time, 'tzset'):
    time.tzset()



from aiogram import Bot, Dispatcher
from aiogram.client.default import DefaultBotProperties
from aiogram.enums import ParseMode
from aiogram.exceptions import TelegramNetworkError

from app.bot.handlers.cancel_record import cancel_record_router
from app.config import config as cfg
from app.bot.handlers.auth import auth_router
from app.bot.handlers.booking import booking_router
from app.bot.handlers.records import records_router
from app.bot.handlers.report import report_router
from app.bot.scheduler import start_scheduler
from app.bot.handlers.confirmation import confirm_router

from app.db.base import init_db

from aiogram.types import BotCommand, BotCommandScopeDefault

TOKEN = cfg.BOT_TOKEN
bot = Bot(token=TOKEN, default=DefaultBotProperties(parse_mode=ParseMode.HTML))
dp = Dispatcher()

async def setup_bot_commands(bot_instance: Bot):
    commands = [
        BotCommand(command="start", description="Запустить бота / главное меню"),
        BotCommand(command="menu", description="Главное меню"),
        BotCommand(command="records", description="Мои записи"),
        BotCommand(command="settings", description="Настройки"),
    ]
    try:
        await bot_instance.set_my_commands(commands, scope=BotCommandScopeDefault())
        logging.info("[Bot] Bot commands menu registered successfully")
    except Exception as e:
        logging.error(f"[Bot] Failed to register bot commands: {e}")

async def main():
    await init_db()
    await setup_bot_commands(bot)
    dp.include_router(auth_router)
    dp.include_router(booking_router)
    dp.include_router(records_router)
    dp.include_router(report_router)
    dp.include_router(cancel_record_router)
    dp.include_router(confirm_router)

    start_scheduler(bot)

    max_retries = 5
    for attempt in range(1, max_retries + 1):
        try:
            logging.info(f"[Bot] Starting polling, attempt    {attempt}")
            await dp.start_polling(bot)
            break
        except (aiohttp.ClientConnectorError, TelegramNetworkError) as e:
            logging.error(f"[Bot] Network error on attempt {attempt}: {e}")
            if attempt < max_retries:
                wait_time = 10 * attempt
                logging.info(f"[Bot] Retrying after {wait_time} seconds...")
                await asyncio.sleep(wait_time)
            else:
                logging.error("[Bot] Max retries reached, exiting.")
                raise
        except Exception as exc:
            logging.error(f"[Bot] Unexpected error: {exc}")
            raise


if __name__ == '__main__':
    logging.basicConfig(level=logging.INFO)
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("[Bot] Бот остановлен")
