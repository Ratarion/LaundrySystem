from app.locales import ru, en, cn
from app.bot.utils.fsm import get_state_data, update_state_data
from app.laundry_repo import get_user_by_vk_id

ALL_TEXTS = {**ru.RUtexts, **en.ENtexts, **cn.CNtexts}


async def get_lang_and_texts(peer_id: int, user_id: int = None, user=None) -> tuple[str, dict]:
    lang = None
    data = await get_state_data(peer_id)
    if isinstance(data, dict) and "lang" in data:
        lang = data["lang"]

    if not lang and user is not None and getattr(user, "language", None):
        lang = user.language

    if not lang:
        target_uid = user_id or peer_id
        try:
            db_user = await get_user_by_vk_id(target_uid)
            if db_user and getattr(db_user, "language", None):
                lang = db_user.language
        except Exception:
            pass

    lang = (str(lang).strip() if lang else "RU").upper()
    if lang not in ALL_TEXTS:
        lang = "RU"

    if not (isinstance(data, dict) and data.get("lang") == lang):
        try:
            await update_state_data(peer_id, {"lang": lang})
        except Exception:
            pass

    return lang, ALL_TEXTS[lang]

