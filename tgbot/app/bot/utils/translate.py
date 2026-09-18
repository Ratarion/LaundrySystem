from aiogram.fsm.context import FSMContext
from app.locales import ru, en, cn
from app.repositories.laundry_repo import get_user_by_tg_id

ALL_TEXTS = {**ru.RUtexts, **en.ENtexts, **cn.CNtexts}


async def get_lang_and_texts(state: FSMContext = None, tg_id: int = None, user=None) -> tuple[str, dict]:
    lang = None
    if state is not None:
        try:
            data = await state.get_data()
            if isinstance(data, dict):
                lang = data.get('lang')
        except Exception:
            data = {}

    if not lang and user is not None and getattr(user, "language", None):
        lang = user.language

    if not lang and tg_id is not None:
        try:
            db_user = await get_user_by_tg_id(tg_id)
            if db_user and getattr(db_user, "language", None):
                lang = db_user.language
        except Exception:
            pass

    lang = (str(lang).strip() if lang else "RU").upper()
    if lang not in ALL_TEXTS:
        lang = "RU"

    if state is not None:
        try:
            current_data = await state.get_data()
            if not isinstance(current_data, dict) or current_data.get('lang') != lang:
                await state.update_data(lang=lang)
        except Exception:
            pass

    return lang, ALL_TEXTS[lang]