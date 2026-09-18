# --- EN (обновлённый) ---
ENtexts = {
    "ENG": {
        "hello_user": "Hello, {name}! Choose an action:",
        "welcome_lang_choice": "Hello, choose a language",
        "record_laundry": "Book a wash",
        "report_in_admin": "Report a problem",
        "report_sent": "The message has been sent",
        "report_prompt": "Specify the machine number and type, and describe the problem:",
        "report_too_long": "Message is too long. Please shorten it and send again.",
        "show_records": "Show my bookings",
        "cancel_record": "Cancel booking",
        "web_panel": "🌐 Open Website",
        "exit": "Exit",
        "back": "Back",
        "change_language": "🌐 Change language",
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
        "booking_confirmed": "✅ Booking confirmed! We are waiting for you.",
        "booking_autocanceled": "❌ Your booking Date: {date} Time: {time_range} Machine: {machine_type} №{machine_num} was automatically canceled because you did not confirm it in time.",
        "booking_already_confirmed": "This booking has already been confirmed or canceled.",
        "booking_already_canceled": "This booking was automatically canceled (you did not confirm it in time).",
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
        "reg_id_error": "Please enter only numbers.",
        "other_tg_id": "This user is already registered with another account.",
        "seek_cards": "No user with this name was found. Enter your student ID number (numbers only)",
        "auth": "Enter your full name (surname, first name, and patronymic) to log in",
        "write_FIO": "Please enter your full name (at least 2 words)."
    }
}
