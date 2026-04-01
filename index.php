<?php
// index.php — FRONT CONTROLLER (главный файл проекта)
session_start();

// Подключаем базу
$pdo = require_once __DIR__ . '/db_connect.php';
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
        require_once __DIR__ . '/booking.php';
        break;

    case '/login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_once __DIR__ . '/login.php';
        } else {
            header('Location: /login.php');
            exit;
        }
        break;

    case '/stats':
    case '/residents':
    case '/machines':
    case '/notifications':
        if (!isset($_SESSION['admin_id'])) {
            header('Location: /booking');
            exit;
        }
        $file = substr($uri, 1) . '.php';
        if (file_exists(__DIR__ . '/' . $file)) {
            require_once __DIR__ . '/' . $file;
        } else {
            http_response_code(404);
            echo "<h1>404 — Файл не найден</h1>";
        }
        break;

    case '/logout':
        require_once __DIR__ . '/logout.php';
        break;

    default:
        http_response_code(404);
        echo "<h1>404 — Страница не найдена</h1>";
        break;
}