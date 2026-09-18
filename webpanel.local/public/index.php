<?php
// public/index.php — FRONT CONTROLLER (MVC)
date_default_timezone_set('Asia/Novokuznetsk');

// Безопасность сессий (защита от XSS и перехвата cookie через document.cookie)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// HTTP Security Headers (Защита от XSS, Clickjacking, MIME-sniffing)
if (!headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none';");
    header("X-XSS-Protection: 1; mode=block");
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

// Очистка входных данных от null-байтов (защита от обхода валидации и инъекций)
$sanitizeInput = function (&$value) use (&$sanitizeInput) {
    if (is_array($value)) {
        foreach ($value as $k => &$v) {
            $sanitizeInput($v);
        }
    } elseif (is_string($value)) {
        $value = str_replace(chr(0), '', $value);
    }
};
$sanitizeInput($_GET);
$sanitizeInput($_POST);
$sanitizeInput($_COOKIE);
$sanitizeInput($_REQUEST);

$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';

// Подключаем базу
$pdo = require_once $root . '/config/db_connect.php';
if (!($pdo instanceof PDO)) {
    die('Критическая ошибка подключения к базе');
}

// Автозагрузка классов App (Controllers, Services и т.д.)
spl_autoload_register(function ($class) use ($root) {
    if (strpos($class, 'App\\') === 0) {
        $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$normalizedUri = rtrim($uri, '/');
if ($normalizedUri === '') {
    $normalizedUri = '/';
}

// Прямой ввод адреса админки (/admin, /admin/, /administrator, /panel, /adminka)
$adminAliases = ['/admin', '/administrator', '/panel', '/adminka'];
if (in_array($normalizedUri, $adminAliases, true)) {
    if (isset($_SESSION['admin_id'])) {
        header('Location: /booking');
    } else {
        header('Location: /login?error=' . urlencode('Доступ запрещён. Пожалуйста, войдите в систему.'));
    }
    exit;
}

// Защита админских маршрутов от прямого перехода по URL без авторизации
$adminProtectedRoutes = [
    '/residents',
    '/residents.php',
    '/machines',
    '/machines.php',
    '/notifications',
    '/notifications.php',
    '/stats',
    '/stats.php',
    '/stats/export/xlsx',
    '/stats/export/docx',
    '/dormitories',
    '/dormitories.php',
    '/change-password'
];

if (in_array($normalizedUri, $adminProtectedRoutes, true) && !isset($_SESSION['admin_id'])) {
    header('Location: /login?error=' . urlencode('Доступ запрещён. Пожалуйста, войдите в систему.'));
    exit;
}

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

    case '/dormitories':
    case '/dormitories.php':
        $controller = new App\Controllers\DormitoryController($pdo);
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

    case '/change-password':
        $controller = new App\Controllers\AuthController($pdo);
        $controller->changePassword();
        break;

    default:
        http_response_code(404);
        echo "<h1>404 — Страница не найдена</h1>";
        break;
}