from vkbottle import GroupEventType
from vkbottle.bot import BotLabeler, MessageEvent
from vkbottle.dispatch.rules.base import PayloadContainsRule

from app.laundry_repo import get_booking_by_id, set_booking_status
from app.bot.utils.translate import ALL_TEXTS
from app.bot.utils.translate import get_lang_and_texts

confirm_labeler = BotLabeler()


@confirm_labeler.raw_event(GroupEventType.MESSAGE_EVENT, MessageEvent, PayloadContainsRule({"cmd": "confirm"}))
async def process_confirm(event: MessageEvent):
    booking_id = int(event.payload["id"])

    # Пытаемся определить язык пользователя по его текущему состоянию (если есть),
    # иначе — RU по умолчанию (как и в исходной tg-версии).
    lang, t = await get_lang_and_texts(event.peer_id)

    booking = await get_booking_by_id(booking_id)

    if not booking:
        await event.show_snackbar(t["booking_already_confirmed"])
        return

    if booking.status == "Подтверждено":
        await event.show_snackbar(t["booking_already_confirmed"])
        await event.edit_message(t["booking_confirmed"])
        return

    await set_booking_status(booking_id, "Подтверждено")
    await event.edit_message(t["booking_confirmed"])
