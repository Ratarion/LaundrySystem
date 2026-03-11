<?php

require_once __DIR__ . '/vendor/autoload.php'; // Подключаем автозагрузчик Composer

// Загружаем переменные окружения из .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$host     = $_ENV['DB_HOST']; //Хостт
$port     = $_ENV['DB_PORT']; //Порт
$dbname   = $_ENV['DB_NAME']; //Имя БД
$user     = $_ENV['DB_USER']; //Пользователь
$password = $_ENV['DB_PASS']; //Пароль

// Формируем строку подключения (DSN)
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

try {
    // Создаем объект PDO
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Ошибки будут вызывать исключения
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Данные возвращаются в виде массивов
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Реальная защита от SQL-инъекций
    ]);

    echo "Ура! Подключение к Supabase успешно установлено.";

} catch (PDOException $e) {
    // В случае ошибки выводим её на экран
    die("Ошибка подключения: " . $e->getMessage());
}