import calendar
import json
from datetime import datetime, date as date_cls, time
from typing import Optional

from aiomax.buttons import KeyboardBuilder, CallbackButton
from app.bot.keyboards import get_texts
from app.bot.utils.timezone import get_kemerovo_now

CUTOFF_HOUR = 23

_MONTH_NAMES = {
    "RU": {
        1: "Январь", 2: "Февраль", 3: "Март", 4: "Апрель",
        5: "Май", 6: "Июнь", 7: "Июль", 8: "Август",
        9: "Сентябрь", 10: "Октябрь", 11: "Ноябрь", 12: "Декабрь"
    },
    "ENG": {
        1: "January", 2: "February", 3: "March", 4: "April",
        5: "May", 6: "June", 7: "July", 8: "August",
        9: "September", 10: "October", 11: "November", 12: "December"
    },
    "CN": {
        1: "一月", 2: "二月", 3: "三月", 4: "四月",
        5: "五月", 6: "六月", 7: "七月", 8: "八月",
        9: "九月", 10: "十月", 11: "十一月", 12: "十二月"
    }
}

_WEEKDAY_ABBR = {
    "RU": ["Пн", "Вт", "Ср", "Чт", "Пт", "Сб", "Вс"],
    "ENG": ["Mo", "Tu", "We", "Th", "Fr", "Sa", "Su"],
    "CN": ["一", "二", "三", "四", "五", "六", "日"]
}

def _normalize_lang(lang: Optional[str]) -> str:
    l = (lang or "RU").upper()
    if l in ("EN", "ENG"):
        return "ENG"
    if l in ("CN", "ZH"):
        return "CN"
    return "RU"

def parse_picked_date(date_str: str) -> date_cls:
    return date_cls.fromisoformat(date_str)

def build_month_calendar_keyboard(
    year: int,
    month: int,
    workload: dict,
    max_capacity: int,
    lang: str = "RU",
    back_cmd: str = "back_to_type",
) -> KeyboardBuilder:
    lang_key = _normalize_lang(lang)
    t = get_texts(lang_key)
    now_dt = get_kemerovo_now()
    today = now_dt.date()
    now_time = now_dt.time()

    kb = KeyboardBuilder()

    # 1. Заголовок месяца (без смены месяца, как в TG)
    month_dict = _MONTH_NAMES.get(lang_key, _MONTH_NAMES["RU"])
    month_name = month_dict.get(month, "Месяц")
    month_title = f"{month_name} {year}"
    kb.row(
        CallbackButton(month_title, json.dumps({"cmd": "ignore"}, ensure_ascii=False))
    )

    # 2. Ряд дней недели (7 кнопок)
    weekdays = _WEEKDAY_ABBR.get(lang_key, _WEEKDAY_ABBR["RU"])
    kb.row(*[CallbackButton(w, json.dumps({"cmd": "ignore"}, ensure_ascii=False)) for w in weekdays])

    # 3. Сетка дней (по 7 кнопок в ряду)
    weeks = calendar.monthcalendar(year, month)
    for week in weeks:
        row_buttons = []
        for day in week:
            if day == 0:
                row_buttons.append(CallbackButton("·", json.dumps({"cmd": "ignore"}, ensure_ascii=False)))
            else:
                current_date = date_cls(year, month, day)
                used = workload.get(day, 0)
                free = max_capacity - used if max_capacity > 0 else 0

                is_past = current_date < today or (current_date == today and now_time >= time(CUTOFF_HOUR, 0))

                if is_past:
                    emoji = "⚪"
                    row_buttons.append(
                        CallbackButton(f"{day}{emoji}", json.dumps({"cmd": "day_blocked", "reason": "past"}, ensure_ascii=False), intent="default")
                    )
                elif max_capacity <= 0 or free <= 0:
                    emoji = "🔴"
                    row_buttons.append(
                        CallbackButton(f"{day}{emoji}", json.dumps({"cmd": "day_blocked", "reason": "full"}, ensure_ascii=False), intent="default")
                    )
                elif used == 0:
                    emoji = "🟢"
                    row_buttons.append(
                        CallbackButton(f"{day}{emoji}", json.dumps({"cmd": "day", "date": current_date.isoformat()}, ensure_ascii=False), intent="positive")
                    )
                else:
                    emoji = "🟡"
                    row_buttons.append(
                        CallbackButton(f"{day}{emoji}", json.dumps({"cmd": "day", "date": current_date.isoformat()}, ensure_ascii=False), intent="default")
                    )
        kb.row(*row_buttons)

    # 4. Кнопка "Назад"
    back_label = f"⬅️ {t['back']}"
    kb.row(
        CallbackButton(back_label, json.dumps({"cmd": back_cmd}, ensure_ascii=False))
    )

    return kb
