# --- EN (обновлённый) ---
ENtexts = {
    "ENG": {
        "hello_user": "Hello, {name}! Choose an action:",
        "welcome_lang_choice": "Hello, choose a language",
        "record_laundry": "Booking",
        "report_in_admin": "🛠️ Report a problem",
        "report_sent": "The message has been sent",
        "report_prompt": "Specify the machine number and type, and describe the problem:",
        "report_too_long": "Message is too long. Please shorten it and send again.",
        "show_records": "View",
        "cancel_record": "Cancel booking",
        "web_panel": "🌐 Open Website",
        "exit": "Exit",
        "back": "Back",
        "settings": "⚙️ Settings",
        "settings_title": "⚙️ Settings\n\nSelect a section:",
        "user_banned_alert": "❌ Your account is blocked. Laundry booking is unavailable. Please contact the dormitory elder or administrator.",
        "change_language": "🌐 Change language",
        "notifications_menu": "🔔 Notifications",
        "notifications_settings_title": (
            "🔔 Available slot notifications\n\n"
            "Current status: {status}\n\n"
            "When someone cancels a booking or does not confirm it in time, the bot sends a notification with quick booking for the freed slot.\n\n"
            "You can turn these notifications on or off:"
        ),
        "status_enabled": "Enabled ✅",
        "status_disabled": "Disabled 🔕",
        "disable_notifications_btn": "🔕 Turn off notifications",
        "enable_notifications_btn": "🔔 Turn on notifications",
        "notifications_toggled_on": "🔔 Available slot notifications enabled!",
        "notifications_toggled_off": "🔕 Available slot notifications disabled.",
        "machine_type": "Machine",
        "select_machine_type": "Select machine type",
        "confirm_booking_prompt": (
            "⏳ Booking confirmation\n\n"
            "You have scheduled {machine_type} machine №{machine_num} on {date} "
            "(time: {time_range}).\n"
            "Laundry starts in 1 hour! Please confirm your booking using the button below within 30 minutes, "
            "otherwise it will be canceled automatically."
        ),
        "confirm_btn": "✅ I will come",
        "decline_btn": "❌ I won't come",
        "main_menu_btn": "🏠 Main menu",
        "booking_confirmed": "✅ Booking confirmed! We are waiting for you.",
        "booking_declined": "❌ You cancelled the booking. The slot is freed for other residents.",
        "booking_autocanceled": "❌ Your booking Date: {date} Time: {time_range} Machine: {machine_type} №{machine_num} was automatically canceled because you did not confirm it in time.",
        "booking_already_confirmed": "This booking has already been confirmed or canceled.",
        "booking_already_canceled": "This booking was automatically canceled (you did not confirm it in time).",
        "wash_finishing_soon": (
            "⏳ <b>Your laundry is finishing soon!</b>\n\n"
            "🧺 {machine_type} #{machine_num} finishes at <b>{end_time}</b>.\n"
            "Please remember to collect your laundry promptly so the next resident can use the machine!"
        ),
        "for_wash": "for washing",
        "for_dry": "for drying",

        # Booking / calendar
        "record_start": "Select a date",
        "weekly_limit_reached": "You already have a booking this week. Limit: 1 per week.",
        "machine": "Machine",
        "show_records_title": "Your bookings:",
        "no_user_bookings": "You have no active bookings.",
        "past_date_error": "This day has already passed. Please choose another date.",
        # legacy key (kept)
        "time_prompt": "Select a time for {date}:",
        # new key used in code
        "select_time_prompt": "Select a time for {date}:",
        "record_time_slots": "Select a time",
        "select_machine": "Select a machine",
        "record_success": "✅ Booking created!\n🧺 {type} #{num}\n⏰ {start_time} - {end_time}",
        "select_date_prompt": "Please select a date",
        "day_fully_booked": "This date is fully booked.",
        "no_slots_available": "No slots available on the selected date.",
        "no_available_slots_alert": "No available machines at this time.",
        "slots_none": "No free slots available on {date}",
        "cancel_prompt": "Select a booking to cancel.\nClick the button to free up the slot:",
        "cancel_confirm_success": "✅ Booking cancelled successfully.\nA notification about the free slot has been sent to other residents.",
        "cancel_error": "❌ Failed to cancel booking or it is already inactive.",
        "slot_freed_notification": "🔔 <b>Slot available!</b>\n\n📅 Date: {date}\n⏰ Time: {time}\n🧺 {m_type} #{m_num}\n\nBook it now!",

        "machine_type_wash": "Washing",
        "machine_type_dry": "Drying",
        "no_active_machines_type": "No active machines of the selected type!",
        # key used when overall capacity == 0 in your handler
        "no_active_machines": "No active machines available right now.",
        # Title for section menu when returning from errors
        "section_menu_title": "Main menu",

        "machines_none": "Oops! All machines are busy at this time.",
        "machine_prompt": "Select a machine for {date}, {start} – {end}:",
        "booking_success": "Booking created!\nMachine №{machine_num}\n{start} – {end}",
        "booking_error": "The slot is already taken by another user!",
        "slot_just_taken": "Sorry — someone just took this slot. Try another one.",
        "quick_book": "⚡ Quick booking",
        "quick_book_success": "✅ You have successfully booked the available slot!\n\n🧺 {m_type} #{m_num}\n📅 Date: {date}\n⏰ Time: {time}",
        "slot_taken_by_other": "❌ Someone else has already booked this slot!",

        # Authentication
        "none_user": "No data was found in the system. Please contact the administrator.",
        "reg_id_error": "Please enter a valid student ID number.",
        "other_tg_id": "This user is already registered with another account.",
        "seek_cards": "No user with this name was found. Enter your student ID number:",
        "auth": "Enter your full name (surname, first name, and patronymic) to log in",
        "write_FIO": "Please enter your full name (at least 2 words).",

        # --- Quick access reply panel ---
        "reply_btn_menu": "🏠 Main Menu",
        "reply_btn_book": "🧺 Book Laundry",
        "reply_btn_my_records": "📋 My Bookings",
        "reply_btn_settings": "⚙️ Settings",
        "reply_btn_start": "🚀 Start",
        "quick_access_menu_hint": "Quick access menu activated ⬇️",

        # --- Gamification and discipline rating ---
        "discipline_rating_btn": "⭐️ My Rating",
        "discipline_confirm_bonus": "🎉 +{delta} points for discipline! (Streak: 🔥 {streak}, score: {score})",
        "discipline_decline_reward": "👍 +{delta} point for timely cancellation (slot freed). Score: {score}",
        "discipline_early_cancel_reward": "👍 +{delta} points for early cancellation! Score: {score}",
        "discipline_autocancel_penalty": "⚠️ Penalty: {delta} points for missed booking without cancellation! Current score: {score}.",
        "discipline_banned_alert": "🚫 Warning: your score reached -1000. Booking is locked. Please contact the dorm manager.",
        "discipline_profile_title": "⭐️ Discipline Rating\n\n👤 Resident: {name}\n🏆 Rank: {badge} {rank}\n📊 Score: {score} / 200\n🔥 Confirmation streak: {confirm_streak}\n⚠️ Miss streak: {miss_streak}\n\n📋 Rules:\n• On-time confirmation: +5 pts (+streak bonus up to +12)\n• Early cancellation: +2 pts\n• Decline during prompt: +1 pt\n• Missed without cancel: -15..-40 pts\n\n🕒 Recent activity:\n{history}",
        "discipline_no_history": "No score transactions yet.",

        # --- Information section ---
        "info_btn": "ℹ️ Information",
        "reply_btn_info": "ℹ️ Information",
        "vk_community_btn": "🧺 KuzSTU Laundry (VK)",
        "info_screen_text": (
            "ℹ️ KuzSTU Laundry Service Information\n\n"
            "🧺 Official Community:\n"
            "VK: KuzSTU Laundry (https://vk.ru/kuzstu_stirka)\n\n"
            "👨‍💻 Developer Contacts:\n"
            "• Stas Yakushev:\n"
            "  Telegram: @JokMiler (https://t.me/JokMiler)\n"
            "  VK: Stas Yakushev (https://vk.ru/ratarion)\n"
            "• Vladimir Sakharov:\n"
            "  Telegram: @nrg0412 (https://t.me/nrg0412)\n"
            "  VK: Vladimir Sakharov (https://vk.ru/nrg412)\n\n"
            "⭐️ Rating & Hall of Fame:\n"
            "Check your discipline score, active streak, or view the campus Hall of Fame."
        )
    }
}
