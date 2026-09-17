from typing import Optional, Tuple
from app.locales import ru, en, cn
from app.laundry_repo import get_user_by_max_id

ALL_TEXTS = {**ru.RUtexts, **en.ENtexts, **cn.CNtexts}


async def get_lang_and_texts(user_id: int, cursor=None) -> Tuple[str, dict]:
    lang = "RU"
    if cursor is not None:
        data = cursor.get_data()
        if isinstance(data, dict) and "lang" in data:
            lang = data["lang"]
            return lang, ALL_TEXTS.get(lang, ALL_TEXTS["RU"])

    user = await get_user_by_max_id(user_id)
    if user and getattr(user, "language", None):
        lang = user.language

    return lang, ALL_TEXTS.get(lang, ALL_TEXTS["RU"])
