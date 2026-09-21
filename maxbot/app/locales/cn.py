# --- CN (обновлённый, упрощённый китайский) ---
CNtexts = {
    "CN": {
        "hello_user": "你好, {name}! 请选择一个操作:",
        "welcome_lang_choice": "Здравствуйте, выберите язык\nHello, choose a language\n你好, 选择语言",
        "record_laundry": "预约",
        "report_sent": "该消息已发送",
        "report_in_admin": "🛠️ 报告问题",
        "report_prompt": "指定机器的数量和类型并描述问题。:",
        "report_too_long": "消息太长，请缩短后重新发送。",
        "show_records": "查看",
        "cancel_record": "取消预订",
        "web_panel": "🌐 访问网页版",
        "exit": "退出",
        "back": "返回",
        "settings": "⚙️ 设置",
        "settings_title": "⚙️ 设置\n\n请选择功能：",
        "user_banned_alert": "❌ 您的账号已被封禁，无法预约洗衣。如有疑问请联系管理员。",
        "change_language": "🌐 更改语言",
        "notifications_menu": "🔔 通知设置",
        "notifications_settings_title": (
            "🔔 空闲时段通知设置\n\n"
            "当前状态: {status}\n\n"
            "当有人取消预约或未及时确认时，机器人会发送通知，方便您快速预约空出的时段。\n\n"
            "您可以开启或关闭此通知："
        ),
        "status_enabled": "已开启 ✅",
        "status_disabled": "已关闭 🔕",
        "disable_notifications_btn": "🔕 关闭通知",
        "enable_notifications_btn": "🔔 开启通知",
        "notifications_toggled_on": "🔔 空位通知已开启！",
        "notifications_toggled_off": "🔕 空位通知已关闭。",
        "machine_type": "洗衣机",
        "select_machine_type": "选择机器类型",
        "for_wash": "用于洗涤",
        "for_dry": "用于干燥",

        # --- 预订 / 日历 ---
        "record_start": "选择日期",
        "weekly_limit_reached": "您本周已有预约。限制：每周1次。",
        "past_date_error": "该日期已过。请选择其他日期。",

        # ⚠️ 已存在（保留）
        "time_prompt": "选择 {date} 的时间:",

        # ✅ 新增/统一 ключи（必须）
        "select_time_prompt": "选择 {date} 的时间:",
        "record_time_slots": "选择时间",
        "select_machine": "选择机器",
        "record_success": "✅ 预订成功！\n🧺 {type} №{num}\n⏰ {start_time} - {end_time}",
        "select_date_prompt": "请选择日期",
        "day_fully_booked": "该日期已被全部预订。",
        "no_slots_available": "所选日期没有可用的时间段。",
        "no_available_slots_alert": "此时间没有可用的机器。",

        "slots_none": "{date} 无可用时段",

        "machine_type_wash": "洗衣",
        "machine_type_dry": "干燥",
        "machine": "机器",
        "show_records_title": "您的预约：",
        "no_user_bookings": "您当前没有预约。",
        "confirm_booking_prompt": (
            "⏳ 预约确认\n\n"
            "您已预约 {machine_type}机 №{machine_num} 于 {date} "
            "(时间: {time_range})。\n"
            "洗衣将在1小时后开始！请在30分钟内点击下方按钮确认，否则预约将被自动取消。"
        ),
        "confirm_btn": "✅ 我会来",
        "decline_btn": "❌ 我不会来",
        "main_menu_btn": "🏠 主菜单",
        "booking_confirmed": "✅ 预约已确认！期待您的到来。",
        "booking_declined": "❌ 您已取消预约。该时段已释放给其他同学。",
        "booking_autocanceled": "❌ 您的预约 日期: {date} 时间: {time_range} 机器: {machine_type} №{machine_num} 已被自动取消，因为您未及时确认。",
        "booking_already_confirmed": "该预约已确认或已取消。",
        "booking_already_canceled": "该预约已被自动取消（您未及时确认）。",
        "wash_finishing_soon": (
            "⏳ <b>您的洗/烘衣即将完成！</b>\n\n"
            "🧺 {machine_type} №{machine_num} 将在 <b>{end_time}</b> 结束。\n"
            "请及时取出衣物，以免耽误下一位同学使用！"
        ),
        "no_active_machines_type": "没有选定类型的可用机器！",
        "no_active_machines": "当前没有可用的机器。",
        "section_menu_title": "主菜单",
        "cancel_prompt": "选择要取消的预订。\n点击按钮释放时段：",
        "cancel_confirm_success": "✅ 预订已成功取消。\n已向其他住户发送空位通知。",
        "cancel_error": "❌ 取消失败或预订已失效。",
        "slot_freed_notification": "🔔 <b>有空位了！</b>\n\n📅 日期: {date}\n⏰ 时间: {time}\n🧺 {m_type} №{m_num}\n\n快去预订吧！",

        "machines_none": "抱歉！此时间所有机器都已被占用。",
        "machine_prompt": "选择 {date} 的机器, {start} – {end}:",
        "booking_success": "预订成功!\n洗衣机 №{machine_num}\n{start} – {end}",
        "booking_error": "该时段已被其他用户预订!",
        "slot_just_taken": "该时间段刚刚被占用，请选择其他时间。",
        "quick_book": "⚡ 快速预约",
        "quick_book_success": "✅ 您已成功预约该空出的位置！\n\n🧺 {m_type} №{m_num}\n📅 日期: {date}\n⏰ 时间: {time}",
        "slot_taken_by_other": "❌ 该时间段已被其他同学预约！",

        # --- 认证 ---
        "none_user": "系统中未找到数据。请联系管理员。",
        "reg_id_error": "请输入有效的学生证/成绩册号码。",
        "other_tg_id": "该用户已使用其他账户注册。",
        "seek_cards": "未找到该姓名的用户。请输入您的学生证/成绩册号码：",
        "auth": "请输入您的全名（姓、名）以进行授权：",
        "write_FIO": "请输入您的全名（至少两个词）。",

        # --- 快捷底部操作面板 ---
        "reply_btn_menu": "🏠 主菜单",
        "reply_btn_book": "🧺 预约洗衣",
        "reply_btn_my_records": "📋 我的预约",
        "reply_btn_settings": "⚙️ 设置",
        "reply_btn_start": "🚀 开始",
        "quick_access_menu_hint": "底部快捷菜单已激活 ⬇️",

        # --- 纪律与信用积分 ---
        "discipline_rating_btn": "⭐️ 我的积分",
        "discipline_confirm_bonus": "🎉 纪律积分 +{delta} 分！（连击：🔥 {streak}，总分：{score}）",
        "discipline_decline_reward": "👍 及时取消获得 +{delta} 分（名额已释放）。当前积分：{score}",
        "discipline_early_cancel_reward": "👍 提前取消获得 +{delta} 积分！当前积分：{score}",
        "discipline_autocancel_penalty": "⚠️ 未取消且逾期未确认，扣除 {delta} 积分！当前积分：{score}。",
        "discipline_banned_alert": "🚫 注意：您的积分已降至 -1000 分，预约功能已被锁定，请联系宿舍管理员。",
        "discipline_profile_title": "⭐️ 纪律与信用积分\n\n👤 住户：{name}\n🏆 等级：{badge} {rank}\n📊 积分：{score} / 200\n🔥 准时连击：{confirm_streak}\n⚠️ 违约连击：{miss_streak}\n\n📋 规则：\n• 准时确认：+5 分（连击奖励最高 +12）\n• 提前取消：+2 分\n• 确认期放弃：+1 分\n• 违约逾期：-15..-40 分\n\n🕒 近期记录：\n{history}",
        "discipline_no_history": "暂无积分变动记录。",

        # --- 信息与关于 ---
        "info_btn": "ℹ️ 信息与关于",
        "reply_btn_info": "ℹ️ 信息",
        "vk_community_btn": "🧺 KuzSTU 洗衣房 (VK)",
        "info_screen_text": (
            "ℹ️ KuzSTU 洗衣房服务信息\n\n"
            "🧺 官方社区:\n"
            "VK: KuzSTU 洗衣房 (https://vk.ru/kuzstu_stirka)\n\n"
            "👨‍💻 开发者联系方式:\n"
            "• Stas Yakushev:\n"
            "  Telegram: @JokMiler (https://t.me/JokMiler)\n"
            "  VK: Stas Yakushev (https://vk.ru/ratarion)\n"
            "• Vladimir Sakharov:\n"
            "  Telegram: @nrg0412 (https://t.me/nrg0412)\n"
            "  VK: Vladimir Sakharov (https://vk.ru/nrg412)\n\n"
            "⭐️ 纪律积分与荣誉殿堂:\n"
            "查看您的纪律积分与连击，或访问宿舍区荣誉殿堂。"
        )
    }
}
