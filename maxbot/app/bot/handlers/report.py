import json

import aiomax
from aiomax import Router, Message, Callback, fsm

from app.bot.utils.translate import get_lang_and_texts
from app.bot.keyboards import get_section_keyboard, get_back_to_settings_keyboard
from app.bot.states import Report
from app.laundry_repo import get_user_by_max_id, create_notification

report_router = Router()

MAX_REPORT_LENGTH = 500


def _is_cmd(cb: Callback, cmd_name: str) -> bool:
    try:
        data = json.loads(cb.payload)
        return data.get("cmd") == cmd_name
    except Exception:
        return cb.payload == cmd_name


@report_router.on_button_callback(lambda cb: _is_cmd(cb, "report"))
async def report_problem(cb: Callback, cursor: fsm.FSMCursor):
    user_id = cb.user.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)

    await cb.answer(
        text=t.get("report_prompt", "Укажите номер и тип машинки и опишите проблему:"),
        keyboard=get_back_to_settings_keyboard(lang),
    )
    cursor.change_state(Report.waiting_for_report)


@report_router.on_message(lambda msg: getattr(msg.bot.storage, 'get_state', lambda u: None)(msg.sender.user_id) == Report.waiting_for_report)
async def process_report(message: Message, cursor: fsm.FSMCursor):
    user_id = message.sender.user_id
    lang, t = await get_lang_and_texts(user_id, cursor=cursor)

    user = await get_user_by_max_id(user_id)
    if not user:
        await message.reply(t.get("none_user", "Пользователь не найден."), keyboard=get_section_keyboard(lang))
        cursor.clear_state()
        return

    report_text = (message.body.text or "").strip()

    if len(report_text) > MAX_REPORT_LENGTH:
        await message.reply(
            t.get("report_too_long", "Сообщение слишком длинное."),
            keyboard=get_back_to_settings_keyboard(lang),
        )
        return

    await create_notification(resident_id=user.id, description=report_text)

    await message.reply(
        t.get("report_sent", "Сообщение отправлено администрации."),
        keyboard=get_back_to_settings_keyboard(lang),
    )
