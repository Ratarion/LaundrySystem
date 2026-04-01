<?php
// navbar.php — БОКОВОЕ МЕНЮ (исправлено: убрано кривое подчёркивание)
?>
<link rel="stylesheet" href="/assets/css/navbar.css">
<nav style="width: 250px; background: #1f1f1f; padding: 20px 0; height: 100vh; color: #fff; box-shadow: 4px 0 12px rgba(0,0,0,0.3);">

    <h2 style="padding: 0 24px 24px; margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -0.5px;">
        Меню
    </h2>
    <ul style="list-style: none; padding: 0; margin: 0;">
        
        <li>
            <a href="/stats" class="menu-link">
                📊 <span>Статистика</span>
            </a>
        </li>
        
        <li>
            <a href="/machines" class="menu-link">
                🛠️ <span>Техника</span>
            </a>
        </li>
        
        <li>
            <a href="/booking" class="menu-link active">
                📅 <span>Бронирование</span>
            </a>
        </li>

        <li>
            <a href="/residents" class="menu-link">
                👨‍🎓 <span>Пользователи</span>
            </a>
        </li>
        
        <li>
            <a href="/notifications" class="menu-link">
                🛎️ <span>Уведомления</span>
            </a>
        </li>

        <?php if (isset($_SESSION['admin_id'])): ?>
        <li style="margin-top: 30px;">
            <a href="/logout.php" class="menu-link logout">
                🚪 <span>Выход</span>
            </a>
        </li>
        <?php endif; ?>
        
    </ul>
</nav>