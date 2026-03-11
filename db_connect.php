<?php
// db_connect.php — ЕДИНСТВЕННОЕ место подключения к Supabase + логирование

// 1. Подключение логгер 
require_once __DIR__ . '/logger.php';

// 2. Подключаем автозагрузчик Composer и .env
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// 3. Переменные из .env
$host     = $_ENV['DB_HOST'];
$port     = $_ENV['DB_PORT'];
$dbname   = $_ENV['DB_NAME'];
$user     = $_ENV['DB_USER'];
// $password = $_ENV['DB_PASS']; 

if (empty($password)) {
    $log->critical('В .env нет DB_PASS! Проверь файл .env');
    die('Ошибка: не указан пароль БД в .env');
}

// 4. DSN для Supabase
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Ошибки будут вызывать исключения
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Данные возвращаются в виде массивов
        PDO::ATTR_EMULATE_PREPARES   => false, // Реальная защита от SQL-инъекций
    ]);

    $log->info('✅ Подключение к Supabase успешно установлено', [
        'dbname' => $dbname,
        'host'   => $host
    ]);

    return $pdo;   // ← Возвращаем $pdo для использования в index.php и других файлах

} catch (PDOException $e) {
    $log->error('❌ Ошибка подключения к Supabase', [
        'message' => $e->getMessage(),
        'code'    => $e->getCode()
    ]);
    
    die('Ошибка подключения к базе. Смотри файл logs/error.log');
}