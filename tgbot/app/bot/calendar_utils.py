from datetime import datetime, time
from aiogram.types import InlineKeyboardMarkup, InlineKeyboardButton
from aiogram_calendar import SimpleCalendar
from aiogram.filters.callback_data import CallbackData
from app.bot.utils.timezone import get_kemerovo_now

class CustomLaundryCalendarCallback(CallbackData, prefix="custom_laundry_calendar"):
    act: str
    year: int
    month: int
    day: int

class CustomLaundryCalendar(SimpleCalendar):
    calendar_callback = CustomLaundryCalendarCallback

    def __init__(self, workload: dict, max_capacity: int, locale: str = 'ru'):
        # Pass locale=None to prevent aiogram_calendar from calling calendar.different_locale(locale),
        # which crashes with locale.Error: unsupported locale setting in Linux Docker containers.
        super().__init__(locale=None, show_alerts=True)
        self.workload = workload
        self.max_capacity = max_capacity
        
        loc = (locale or 'ru').lower()
        if loc in ('eng', 'en_us', 'en_gb'):
            loc = 'en'
        elif loc in ('zh', 'zh_cn', 'zh_tw'):
            loc = 'cn'
        elif loc not in ('ru', 'en', 'cn'):
            loc = 'ru'
        self.locale = loc
        
        all_months = {
            'ru': {
                1: "Январь", 2: "Февраль", 3: "Март", 4: "Апрель",
                5: "Май", 6: "Июнь", 7: "Июль", 8: "Август",
                9: "Сентябрь", 10: "Октябрь", 11: "Ноябрь", 12: "Декабрь"
            },
            'en': {
                1: "January", 2: "February", 3: "March", 4: "April",
                5: "May", 6: "June", 7: "July", 8: "August",
                9: "September", 10: "October", 11: "November", 12: "December"
            },
            'cn': {
                1: "一月", 2: "二月", 3: "三月", 4: "四月",
                5: "五月", 6: "六月", 7: "七月", 8: "八月",
                9: "九月", 10: "十月", 11: "十一月", 12: "十二月"
            }
        }
        self.months_names = all_months.get(self.locale, all_months['ru'])
        
        # Simple translation map for the Back button inside the class
        self.back_labels = {
            'ru': "Назад",
            'en': "Back",
            'cn': "返回"
        }

    async def start_calendar(
        self, 
        year: int = None, 
        month: int = None, 
        header_text: str = None, 
        back_callback: str = None
    ) -> InlineKeyboardMarkup:
        
        # Determine current date in Kemerovo time if not provided
        now = get_kemerovo_now()
        if year is None: year = now.year
        if month is None: month = now.month
    
        # Generate base structure from SimpleCalendar
        markup = await super().start_calendar(year=year, month=month)
        original_kb = markup.inline_keyboard
        
        new_inline_keyboard = []
    
        # 1. HEADER ROW (Month Name + Year)
        month_name = self.months_names.get(month, "Месяц")
        if self.locale == 'cn':
            title_text = f"{year}年 {month_name}"
        else:
            title_text = f"{month_name} {year}"
        
        title_btn = InlineKeyboardButton(text=title_text, callback_data="ignore_action")
        new_inline_keyboard.append([title_btn])

        # 2. WEEKDAYS ROW (Localized)
        weekdays_map = {
            'ru': ["Пн", "Вт", "Ср", "Чт", "Пт", "Сб", "Вс"],
            'en': ["Mo", "Tu", "We", "Th", "Fr", "Sa", "Su"],
            'cn': ["一", "二", "三", "四", "五", "六", "日"]
        }
        weekdays = weekdays_map.get(self.locale, weekdays_map['ru'])
        new_inline_keyboard.append([InlineKeyboardButton(text=w, callback_data="ignore_action") for w in weekdays])

        # 3. DATE ROWS
        # SimpleCalendar usually puts days from index 3 up to the footer.
        # We iterate to find rows containing days (digits).
        for row in original_kb[3:]:
            new_row = []
            has_days = False
            for btn in row:
                # Check if this button is a day number
                if btn.text.isdigit():
                    has_days = True
                    day = int(btn.text)
                    used = self.workload.get(day, 0)
                    free = self.max_capacity - used if self.max_capacity > 0 else 0

                    now_dt = get_kemerovo_now()
                    today_date = now_dt.date()
                    now_time = now_dt.time()

                    current_day = datetime(year, month, day).date()

                    if current_day < today_date or (current_day == today_date and now_time >= time(23, 0)):
                        btn.text = f"{day} ⚪"
                    elif free <= 0:
                        btn.text = f"{day} 🔴"
                    elif used == 0:
                        btn.text = f"{day} 🟢"
                    else:
                        btn.text = f"{day} 🟡"
                
                # Filter out standard navigation buttons if you don't want them (Cancel, Today)
                # or keep them if they are part of the day rows.
                new_row.append(btn)
            
            # Only append the row if it actually contains calendar days or valid spacers
            if has_days:
                new_inline_keyboard.append(new_row)

        # 4. BACK BUTTON (Footer)
        if back_callback:
            back_label = self.back_labels.get(self.locale, "Back")
            back_btn = InlineKeyboardButton(text=f"⬅️ {back_label}", callback_data=back_callback)
            new_inline_keyboard.append([back_btn])
    
        return InlineKeyboardMarkup(inline_keyboard=new_inline_keyboard)