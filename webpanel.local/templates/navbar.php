<?php
// templates/navbar.php
$current_uri = $_SERVER['REQUEST_URI'] ?? '/';
$isAdmin = ($_SESSION['role'] ?? 0) === 1;
$isChairman = $isAdmin && empty($_SESSION['dormitory_id']);
$userDormId = $_SESSION['dormitory_id'] ?? null;
?>

<nav class="sidebar" id="mainSidebar">

    <div class="sidebar-header">
        <h2 class="sidebar-title">
            <span class="sidebar-title-text">Меню</span>
        </h2>
        <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Свернуть / развернуть меню" aria-label="Свернуть / развернуть меню">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>

    <ul class="sidebar-menu">
        
        <?php if ($isAdmin): ?>
        <li>
            <a href="/stats" class="menu-link <?= strpos($current_uri, '/stats') !== false ? 'active' : '' ?>" title="Статистика">
                <i class="fa-solid fa-chart-simple"></i> <span>Статистика</span>
            </a>
        </li>
        <?php if ($isChairman): ?>
        <li>
            <a href="/dormitories" class="menu-link <?= strpos($current_uri, '/dormitories') !== false ? 'active' : '' ?>" title="Общежития">
                <i class="fa-solid fa-hotel"></i> <span>Общежития</span>
            </a>
        </li>
        <?php endif; ?>
        <?php endif; ?>

        <li>
            <a href="/machines" class="menu-link <?= strpos($current_uri, '/machines') !== false ? 'active' : '' ?>" title="Техника">
                <i class="fa-solid fa-screwdriver-wrench"></i> <span>Техника</span>
            </a>
        </li>
        
        <li>
            <a href="/booking" class="menu-link <?= strpos($current_uri, '/booking') !== false ? 'active' : '' ?>" title="Бронирование">
                <i class="fa-solid fa-calendar-days"></i> <span>Бронирование</span>
            </a>
        </li>

        <li>
            <a href="/residents" class="menu-link <?= strpos($current_uri, '/residents') !== false ? 'active' : '' ?>" title="Пользователи">
                <i class="fa-solid fa-user-graduate"></i> <span>Пользователи</span>
            </a>
        </li>
        
        <li>
            <a href="/notifications" class="menu-link <?= strpos($current_uri, '/notifications') !== false ? 'active' : '' ?>" title="Уведомления">
                <i class="fa-solid fa-bell"></i> <span>Уведомления</span>
            </a>
        </li>

        <li>
            <a href="/change-password" class="menu-link <?= strpos($current_uri, '/change-password') !== false ? 'active' : '' ?>" title="Сменить пароль">
                <i class="fa-solid fa-key"></i> <span>Сменить пароль</span>
            </a>
        </li>

        <?php if (isset($_SESSION['admin_id'])): ?>
        <li style="margin-top: 30px;">
            <a href="/logout" class="menu-link logout" title="Выход">
                <i class="fa-solid fa-right-from-bracket"></i> <span>Выход</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</nav>