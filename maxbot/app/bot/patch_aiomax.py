import logging
from typing import Optional, Literal
from aiomax import utils, buttons
from aiomax.types import Callback, Attachment

logger = logging.getLogger("maxbot.patch")

async def patched_answer(
    self: Callback,
    notification: "Optional[str]" = None,
    text: "Optional[str]" = None,
    format: "Optional[Literal['html', 'markdown', 'default']]" = "default",
    notify: bool = True,
    keyboard: "Optional[list[list[buttons.Button]] | buttons.KeyboardBuilder]" = None,
    attachments: "Optional[list[Attachment] | Attachment]" = None,
):
    """
    Patched Callback.answer that fixes aiomax bug:
    In standard aiomax, if notification is passed without text, aiomax extracts the
    keyboard from self.message and constructs a message body with text=None.
    The MAX Bot API rejects messages with text=None with ('proto.payload', 'errors.required').

    This patch ensures:
    1. If text or attachments are provided, message is constructed properly.
    2. If only notification is provided, NO message object is sent (avoids errors.required).
    3. If only keyboard is provided without text, the existing message text is preserved.
    4. If nothing is provided, an empty body is sent to acknowledge the callback.
    """
    body = {}
    if notification is not None:
        body["notification"] = notification

    # Only include message if text, attachments, or explicit keyboard was requested
    if text is not None or attachments is not None:
        if keyboard is None and self.message is not None and self.message.body:
            kb_list = [
                i
                for i in (self.message.body.attachments or [])
                if getattr(i, "type", None) == "inline_keyboard"
            ]
            keyboard = None if len(kb_list) == 0 else kb_list[0].payload

        fmt = self.bot.default_format if format == "default" else format
        body["message"] = utils.get_message_body(
            text,
            fmt,
            notify=notify,
            keyboard=keyboard,
            attachments=attachments,
        )
    elif keyboard is not None:
        # Only keyboard changed: preserve current text so API won't fail with text=None
        cur_text = " "
        if self.message and self.message.body and self.message.body.text:
            cur_text = self.message.body.text
        fmt = self.bot.default_format if format == "default" else format
        body["message"] = utils.get_message_body(
            cur_text,
            fmt,
            notify=notify,
            keyboard=keyboard,
            attachments=attachments,
        )

    out = await self.bot.post(
        "https://botapi.max.ru/answers",
        params={"callback_id": self.callback_id},
        json=body,
    )
    return await out.json()


Callback.answer = patched_answer
logger.info("[MaxBot] aiomax.types.Callback.answer successfully patched")
