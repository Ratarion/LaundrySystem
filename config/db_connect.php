<?php
// Подключаем логгер (теперь в той же папке config/)
require_once $root . '/config/logger.php';


// Подключаем Composer autoload
$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable($root);
$dotenv->load();

// Переменные из .env
$host     = $_ENV['DB_HOST'];
$port     = $_ENV['DB_PORT'];
$dbname   = $_ENV['DB_NAME'];
$user     = $_ENV['DB_USER'];
$password = $_ENV['DB_PASS']; 

if (empty($password)) {
    $log->critical('В .env нет DB_PASS!');
    die('Ошибка: не указан пароль БД в .env');
}

// DSN для Supabase (PostgreSQL)
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ]);

    $GLOBALS['pdo'] = $pdo;

    $log->info('✅ Подключение к Supabase успешно', [
        'dbname' => $dbname,
        'host'   => $host
    ]);

    return $pdo;

} catch (PDOException $e) {
    $log->error('❌ Ошибка подключения к Supabase', [
        'message' => $e->getMessage(),
        'code'    => $e->getCode()
    ]);
    
    die('Ошибка подключения к базе. Смотри logs/error.log');
}