import json
from datetime import datetime, timedelta, date as date_cls, time
from typing import Tuple

from aiomax.buttons import KeyboardBuilder, CallbackButton
from app.bot.keyboards import get_texts

DAY_ROW_SIZE = 3
WINDOW_SIZE = 7
HORIZON_DAYS = 63
CUTOFF_HOUR = 23

_WEEKDAY_ABBR = {
    "RU": ["Пн", "Вт", "Ср", "Чт", "Пт", "Сб", "Вс"],
    "ENG": ["Mo", "Tu", "We", "Th", "Fr", "Sa", "Su"],
    "CN": ["一", "二", "三", "四", "五", "六", "日"],
}


def _status_emoji(day: date_cls, used: int, max_capacity: int) -> str:
    now = datetime.now()
    is_past_or_closed = day < now.date() or (day == now.date() and now.time() >= time(CUTOFF_HOUR, 0))
    if is_past_or_closed:
        return "⚪"
    if max_capacity <= 0:
        return "⚪"
    free = max_capacity - used
    if free <= 0:
        return "🔴"
    if used == 0:
        return "🟢"
    return "🟡"


def is_day_selectable(day: date_cls, used: int, max_capacity: int) -> bool:
    now = datetime.now()
    if day < now.date() or (day == now.date() and now.time() >= time(CUTOFF_HOUR, 0)):
        return False
    if max_capacity <= 0:
        return False
    return (max_capacity - used) > 0


def build_date_picker_keyboard(
    workload: dict,
    max_capacity: int,
    lang: str,
    offset: int = 0,
    back_cmd: str = "back_to_type",
    window_size: int = WINDOW_SIZE,
) -> KeyboardBuilder:
    t = get_texts(lang)
    today = datetime.now().date()
    weekday_abbr = _WEEKDAY_ABBR.get(lang, _WEEKDAY_ABBR["RU"])

    kb = KeyboardBuilder()
    row_buttons = []

    for i in range(window_size):
        day = today + timedelta(days=offset + i)
        used = workload.get(day, 0)
        emoji = _status_emoji(day, used, max_capacity)
        wd = weekday_abbr[day.weekday()]
        label = f"{wd} {day.strftime('%d.%m')} {emoji}"

        intent = "positive" if emoji == "🟢" else ("default" if emoji == "🟡" else "default")
        payload = json.dumps({"cmd": "day", "date": day.isoformat()}, ensure_ascii=False)
        row_buttons.append(CallbackButton(label, payload, intent=intent))

        if len(row_buttons) == DAY_ROW_SIZE or i == window_size - 1:
            kb.row(*row_buttons)
            row_buttons = []

    # Навигация вперёд/назад
    nav_buttons = []
    if offset > 0:
        prev_offset = max(0, offset - window_size)
        nav_buttons.append(
            CallbackButton("◀", json.dumps({"cmd": "day_page", "offset": prev_offset}, ensure_ascii=False), intent="default")
        )
    if offset + window_size < HORIZON_DAYS:
        next_offset = offset + window_size
        nav_buttons.append(
            CallbackButton("▶", json.dumps({"cmd": "day_page", "offset": next_offset}, ensure_ascii=False), intent="default")
        )

    if nav_buttons:
        kb.row(*nav_buttons)

    # Кнопка "Назад"
    kb.row(
        CallbackButton(t["back"], json.dumps({"cmd": back_cmd}, ensure_ascii=False), intent="default")
    )

    return kb


def parse_picked_date(date_str: str) -> date_cls:
    return date_cls.fromisoformat(date_str)


def get_range_bounds(offset: int = 0, window_size: int = WINDOW_SIZE) -> Tuple[datetime, datetime]:
    today = datetime.now().date()
    start_day = today + timedelta(days=offset)
    end_day = start_day + timedelta(days=window_size)
    start_dt = datetime.combine(start_day, time(0, 0, 0))
    end_dt = datetime.combine(end_day, time(0, 0, 0))
    return start_dt, end_dt
