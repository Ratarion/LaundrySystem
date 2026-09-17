<?php
// templates/navbar.php
$current_uri = $_SERVER['REQUEST_URI'] ?? '/';
$isAdmin = ($_SESSION['role'] ?? 0) === 1;
$isChairman = $isAdmin && empty($_SESSION['dormitory_id']);
$userDormId = $_SESSION['dormitory_id'] ?? null;
?>

<nav class="sidebar">

    <h2 class="sidebar-title">
        Меню
    </h2>

    <ul class="sidebar-menu">
        
        <?php if ($isAdmin): ?>
        <li>
            <a href="/stats" class="menu-link <?= strpos($current_uri, '/stats') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-simple"></i> <span>Статистика</span>
            </a>
        </li>
        <?php if ($isChairman): ?>
        <li>
            <a href="/dormitories" class="menu-link <?= strpos($current_uri, '/dormitories') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-hotel"></i> <span>Общежития</span>
            </a>
        </li>
        <?php endif; ?>
        <?php endif; ?>

        <li>
            <a href="/machines" class="menu-link <?= strpos($current_uri, '/machines') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-screwdriver-wrench"></i> <span>Техника</span>
            </a>
        </li>
        
        <li>
            <a href="/booking" class="menu-link <?= strpos($current_uri, '/booking') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-days"></i> <span>Бронирование</span>
            </a>
        </li>

        <li>
            <a href="/residents" class="menu-link <?= strpos($current_uri, '/residents') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-user-graduate"></i> <span>Пользователи</span>
            </a>
        </li>
        
        <li>
            <a href="/notifications" class="menu-link <?= strpos($current_uri, '/notifications') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-bell"></i> <span>Уведомления</span>
            </a>
        </li>

        <li>
            <a href="/change-password" class="menu-link <?= strpos($current_uri, '/change-password') !== false ? 'active' : '' ?>">
                <i class="fa-solid fa-key"></i> <span>Сменить пароль</span>
            </a>
        </li>

        <?php if (isset($_SESSION['admin_id'])): ?>
        <li style="margin-top: 30px;">
            <a href="/logout" class="menu-link logout">
                <i class="fa-solid fa-right-from-bracket"></i> <span>Выход</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</nav>