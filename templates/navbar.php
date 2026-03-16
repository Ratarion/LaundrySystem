<!-- Боковая навигационная модель (Статистика / Техника / Бронирование / Уведомления / Выход) -->
<nav style="width: 250px; background: #1f1f1f; padding: 20px;">
    <h2>Меню</h2>
    <ul style="list-style: none; padding: 0;">
        <li><a href="/stats" style="color: #fff; text-decoration: none;">📊 Статистика</a></li>
        <li><a href="/machines" style="color: #fff; text-decoration: none;">🛠️ Техника</a></li>
        <li><a href="/booking" style="color: #fff; text-decoration: none; font-weight: bold;">📅 Бронирование</a></li>
        <li><a href="/notifications" style="color: #fff; text-decoration: none;">🛎️ Уведомления</a></li>
        <?php if (isset($_SESSION['admin_id'])): ?>
            <li><a href="/logout" style="color: #ff5252;">🚪 Выход</a></li>
        <?php endif; ?>
    </ul>
</nav>