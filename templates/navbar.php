<?php
// templates/navbar.php — меню с учётом роли
$current_uri = $_SERVER['REQUEST_URI'] ?? '/';
$isAdmin = ($_SESSION['role'] ?? 0) === 1;
?>

<nav style="width: 250px; background: #1f1f1f; padding: 20px 0; height: 100vh; color: #fff; box-shadow: 4px 0 12px rgba(0,0,0,0.3);">

    <h2 style="padding: 0 24px 24px; margin: 0; font-size: 22px; font-weight: 600; letter-spacing: -0.5px;">
        Меню
    </h2>

    <ul style="list-style: none; padding: 0; margin: 0;">
        
        <?php if ($isAdmin): ?>
        <li>
            <a href="/stats" class="menu-link <?= strpos($current_uri, '/stats') !== false ? 'active' : '' ?>">
                📊 <span>Статистика</span>
            </a>
        </li>
        <?php endif; ?>

        <li>
            <a href="/machines" class="menu-link <?= strpos($current_uri, '/machines') !== false ? 'active' : '' ?>">
                🛠️ <span>Техника</span>
            </a>
        </li>
        
        <li>
            <a href="/booking" class="menu-link <?= strpos($current_uri, '/booking') !== false ? 'active' : '' ?>">
                📅 <span>Бронирование</span>
            </a>
        </li>

        <li>
            <a href="/residents" class="menu-link <?= strpos($current_uri, '/residents') !== false ? 'active' : '' ?>">
                👨‍🎓 <span>Пользователи</span>
            </a>
        </li>
        
        <li>
            <a href="/notifications" class="menu-link <?= strpos($current_uri, '/notifications') !== false ? 'active' : '' ?>">
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