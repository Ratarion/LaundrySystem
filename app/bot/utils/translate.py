from app.locales import ru, en, cn
from app.bot.utils.fsm import get_state_data

ALL_TEXTS = {**ru.RUtexts, **en.ENtexts, **cn.CNtexts}


async def get_lang_and_texts(peer_id: int) -> tuple[str, dict]:
    data = await get_state_data(peer_id)
    lang = data.get('lang', 'RU')
    return lang, ALL_TEXTS.get(lang, ALL_TEXTS['RU'])
