import aiomax
from app.config import MAX_TOKEN

if MAX_TOKEN and MAX_TOKEN.strip():
    bot = aiomax.Bot(access_token=MAX_TOKEN, use_certificate=True, default_format='html')
else:
    bot = None
