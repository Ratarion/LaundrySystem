from vkbottle import GroupEventType
from vkbottle.bot import BotLabeler, Message, MessageEvent
from vkbottle.dispatch.rules.base import PayloadContainsRule, StateRule

from app.bot.utils.translate import get_lang_and_texts
from app.bot.keyboards import get_section_keyboard, get_back_to_sections_keyboard
from app.bot.states import Report
from app.laundry_repo import get_user_by_vk_id, create_notification
from app.bot.utils.fsm import set_state, clear_state

report_labeler = BotLabeler()

MAX_REPORT_LENGTH = 500


@report_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "report"}))
async def report_problem(event: MessageEvent):
    peer_id = event.peer_id
    lang, t = await get_lang_and_texts(peer_id)
    await event.edit_message(
        t.get("report_prompt", "Укажите номер и тип машинки и опишите проблему:"),
        keyboard=get_back_to_sections_keyboard(lang),
    )
    await set_state(peer_id, Report.waiting_for_report)


@report_labeler.message(StateRule(Report.waiting_for_report))
async def process_report(message: Message):
    peer_id = message.peer_id
    lang, t = await get_lang_and_texts(peer_id)

    user = await get_user_by_vk_id(message.from_id)
    if not user:
        await message.answer(t.get("none_user", "Пользователь не найден."), keyboard=get_section_keyboard(lang))
        await clear_state(peer_id)
        return

    report_text = (message.text or "").strip()

    if len(report_text) > MAX_REPORT_LENGTH:
        await message.answer(
            t.get("report_too_long", "Сообщение слишком длинное."),
            keyboard=get_back_to_sections_keyboard(lang),
        )
        return

    await create_notification(resident_id=user.id, description=report_text)

    await message.answer(
        t.get("report_sent", "Сообщение отправлено."),
        keyboard=get_back_to_sections_keyboard(lang),
    )
    # Состояние намеренно не сбрасываем: бот ждёт нажатия "Назад"
    # (или следующего сообщения, если решат отправить ещё один репорт) — как и в tg-версии.
