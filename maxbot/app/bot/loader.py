import aiomax
from app.config import MAX_TOKEN

bot = aiomax.Bot(token=MAX_TOKEN, use_certificate=True, default_format='html')
