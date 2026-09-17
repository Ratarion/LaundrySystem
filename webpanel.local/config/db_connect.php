<?php
// Подключаем Composer autoload и определяем корень проекта
$root = dirname(__DIR__);
require_once $root . '/config/logger.php';

if (file_exists($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}

use Dotenv\Dotenv;

if (class_exists(Dotenv::class) && file_exists($root . '/.env')) {
    $dotenv = Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}

// Переменные из .env или переменных окружения сервера
$host     = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'localhost';
$port     = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? '5432';
$dbname   = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'layndaru_db';
$user     = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? 'postgres';
$password = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?? 'postgres'; 
$sslmode  = $_ENV['DB_SSLMODE'] ?? getenv('DB_SSLMODE') ?? 'disable';

// DSN для PostgreSQL с настраиваемым sslmode и таймаутом подключения
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode;connect_timeout=5";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ]);

    $GLOBALS['pdo'] = $pdo;

    $log->info('✅ Подключение к PostgreSQL успешно', [
        'dbname' => $dbname,
        'host'   => $host
    ]);

    return $pdo;

} catch (PDOException $e) {
    $log->error('❌ Ошибка подключения к PostgreSQL', [
        'message' => $e->getMessage(),
        'code'    => $e->getCode()
    ]);
    
    // Возвращаем HTTP 503 Service Unavailable и понятный текст вместо зависания
    http_response_code(503);
    die('<h3>⚠️ Ошибка подключения к базе данных</h3>
         <p>Сервер базы данных PostgreSQL не отвечает. Убедитесь, что контейнер с базой данных запущен (<code>docker compose up -d postgres</code>).</p>
         <p>Пожалуйста, <b>обновите страницу</b> через несколько секунд.</p>
         <p><small>Подробности записаны в logs/error.log</small></p>');
}