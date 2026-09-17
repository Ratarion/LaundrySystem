import logging

from vkbottle import BuiltinStateDispenser
from vkbottle.bot import Bot

from app.config import VK_TOKEN

if not VK_TOKEN:
    raise RuntimeError(
        "VK_TOKEN не найден в переменных окружения (.env). "
        "Укажите токен группы ВКонтакте в файле .env."
    )

# Единый на всё приложение диспенсер состояний. Создаём его отдельно (а не
# полагаемся на дефолтный внутри Bot), чтобы иметь возможность импортировать
# его напрямую в любом модуле (handlers, keyboards, scheduler и т.д.) —
# так же, как раньше все хендлеры получали FSMContext от aiogram.
state_dispenser = BuiltinStateDispenser()

bot = Bot(token=VK_TOKEN, state_dispenser=state_dispenser)

# Короткий алиас, для сообщений "от лица бота" вне хендлеров
# (рассылки, планировщик и т.п.)
api = bot.api

logging.basicConfig(level=logging.INFO)
