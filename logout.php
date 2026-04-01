<?php
// logout.php — СКРИПТ ВЫХОДА
require_once __DIR__ . '/logger.php';

// Если сессия еще не запущена (на случай прямого обращения к файлу)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Очищаем массив сессии
$_SESSION = [];

// 2. Удаляем куку сессии в браузере
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// 3. Уничтожаем сессию на сервере
session_destroy();

// 4. Логируем выход (по желанию)
if (isset($log)) {
    $log->info('Пользователь вышел из системы.');
}

// 5. Редирект на главную или страницу входа
header('Location: /booking.php');
exit;