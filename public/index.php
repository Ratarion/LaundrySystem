<?php
// public/index.php — FRONT CONTROLLER (MVC)
session_start();

$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';


// Подключаем базу
$pdo = require_once $root . '/config/db_connect.php';
if (!($pdo instanceof PDO)) {
    die('Критическая ошибка подключения к базе');
}

// Автозагрузка наших контроллеров
spl_autoload_register(function ($class) use ($root) {
    if (strpos($class, 'App\\Controllers\\') === 0) {
        $file = $root . '/app/Controllers/' . str_replace('App\\Controllers\\', '', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';

// ==================== РОУТИНГ ====================
switch ($uri) {
    case '/':
    case '/index.php':
    case '/booking':
    case '/booking.php':
        $controller = new App\Controllers\BookingController($pdo);
        $controller->index();
        break;

    case '/login':
    case '/login.php':
        $controller = new App\Controllers\AuthController($pdo);
        $controller->login();
        break;

    case '/residents':
        $controller = new App\Controllers\ResidentController($pdo);
        $controller->index();
        break;

    case '/machines':
        $controller = new App\Controllers\MachineController($pdo);
        $controller->index();
        break;

    case '/notifications':
        $controller = new App\Controllers\NotificationController($pdo);
        $controller->index();
        break;

    case '/stats':
        $controller = new App\Controllers\StatsController($pdo);
        $controller->index();
        break;

    case '/logout':
    case '/logout.php':
        $controller = new App\Controllers\AuthController($pdo);
        $controller->logout();
        break;

    case '/stats/export/xlsx':
        $controller = new App\Controllers\StatsController($pdo);
        $controller->exportXlsx();
        break;

    case '/stats/export/docx':
        $controller = new App\Controllers\StatsController($pdo);
        $controller->exportDocx();
        break;

    default:
        http_response_code(404);
        echo "<h1>404 — Страница не найдена</h1>";
        break;
}