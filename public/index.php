<?php
// public/index.php — FRONT CONTROLLER
session_start();

$root = dirname(__DIR__);

// Подключаем базу
$pdo = require_once $root . '/config/db_connect.php';
if (!($pdo instanceof PDO)) {
    die('Критическая ошибка подключения к базе');
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// ==================== РОУТИНГ ====================
switch ($uri) {
    case '/':
    case '/index.php':
    case '/booking':
    case '/booking.php':
        require_once $root . '/public/booking.php';
        break;

    case '/login':
    case '/login.php':
        require_once $root . '/public/login.php';
        break;

    case '/stats':
    case '/residents':
    case '/machines':
    case '/notifications':
        if (!isset($_SESSION['admin_id'])) {
            header('Location: /booking');
            exit;
        }
        $file = $root . '/public/' . substr($uri, 1) . '.php';
        if (file_exists($file)) {
            require_once $file;
        } else {
            http_response_code(404);
            echo "<h1>404 — Файл не найден</h1>";
        }
        break;

    case '/logout':
        require_once $root . '/public/logout.php';
        break;

    default:
        http_response_code(404);
        echo "<h1>404 — Страница не найдена</h1>";
        break;
}