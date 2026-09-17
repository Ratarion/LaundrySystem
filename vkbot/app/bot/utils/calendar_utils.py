"""
Пикер даты для VK-бота.

В оригинальном tg-боте использовалась библиотека aiogram_calendar с помесячной
сеткой 7 колонок (дни недели) x до 6 строк. У VK inline-клавиатур жёсткие
лимиты: максимум 5 кнопок в строке, максимум 6 строк, и ОТДЕЛЬНО — максимум
10 кнопок СУММАРНО на клавиатуру (даже если сетка 5x6 формально позволяет
больше — VK API вернёт ошибку 911 "keyboard contains too much buttons").
Помесячная сетка под это никак не подходит.

Поэтому здесь другой, более VK-дружелюбный UX: пролистываемый список
ближайших дней (по умолчанию 7 штук = неделя, 2 строки по 5+2), с теми же
цветными индикаторами загруженности, что были в tg-версии:
  🟢 — свободно (нет ни одной брони)
  🟡 — частично занято
  🔴 — полностью занято
  ⚪ — день недоступен для записи (уже прошёл, либо сегодня после 23:00)

7 дней + до 2 кнопок навигации (◀/▶) + кнопка "назад" = максимум 10 кнопок —
ровно предел VK. Так как бронь можно взять максимум 1 раз в неделю (см.
has_weekly_booking), пролистывание по неделям — ещё и осмысленный шаг с
точки зрения UX, а не только техническое ограничение.
"""
from datetime import datetime, timedelta, date as date_cls, time

from vkbottle import Keyboard, Callback, KeyboardButtonColor

from app.bot.keyboards import get_texts

DAY_ROW_SIZE = 3          # кнопок дней в строке
WINDOW_SIZE = 7           # дней показываем за раз (1 неделя) — см. докстринг выше
HORIZON_DAYS = 63         # дальше этого не даём листать вперёд (9 недель)
CUTOFF_HOUR = 23          # после этого часа "сегодня" уже нельзя забронировать

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
    """Финальная проверка (та же, что используется при обработке нажатия)."""
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
) -> str:
    """
    workload: {date(...): количество броней в этот день} — см.
              laundry_repo.get_range_workload
    offset:   сколько дней от сегодняшнего пропустить (для пагинации)
    """
    t = get_texts(lang)
    today = datetime.now().date()
    weekday_abbr = _WEEKDAY_ABBR.get(lang, _WEEKDAY_ABBR["RU"])

    kb = Keyboard(inline=True)
    for i in range(window_size):
        if i > 0 and i % DAY_ROW_SIZE == 0:
            kb.row()
        day = today + timedelta(days=offset + i)
        used = workload.get(day, 0)
        emoji = _status_emoji(day, used, max_capacity)
        wd = weekday_abbr[day.weekday()]
        label = f"{wd} {day.strftime('%d.%m')} {emoji}"
        kb.add(Callback(label, {"cmd": "day", "date": day.isoformat()}), color=KeyboardButtonColor.PRIMARY)

    # Навигация вперёд/назад по окнам дат
    kb.row()
    if offset > 0:
        prev_offset = max(0, offset - window_size)
        kb.add(Callback("◀", {"cmd": "day_page", "offset": prev_offset}), color=KeyboardButtonColor.SECONDARY)
    if offset + window_size < HORIZON_DAYS:
        next_offset = offset + window_size
        kb.add(Callback("▶", {"cmd": "day_page", "offset": next_offset}), color=KeyboardButtonColor.SECONDARY)

    kb.row()
    kb.add(Callback(t["back"], {"cmd": back_cmd}), color=KeyboardButtonColor.SECONDARY)

    return kb.get_json()


def parse_picked_date(date_str: str) -> datetime:
    """
    '2026-07-20' -> datetime(2026, 7, 20, 0, 0)
    Также принимает полную ISO-строку datetime ('2026-07-20T00:00:00') на
    случай, если она попадёт сюда откуда-то ещё — date.fromisoformat() сам
    такое не читает, поэтому обрезаем время до разбора.
    """
    date_str = date_str.split("T")[0]
    d = date_cls.fromisoformat(date_str)
    return datetime(d.year, d.month, d.day)


def get_range_bounds(offset: int, window_size: int = WINDOW_SIZE) -> tuple[datetime, datetime]:
    """Границы диапазона дат, которые сейчас показаны на клавиатуре (для запроса workload)."""
    today = datetime.now().date()
    start = datetime(today.year, today.month, today.day) + timedelta(days=offset)
    end = start + timedelta(days=window_size)
    return start, end
