<?php

require_once __DIR__ . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

// Создаём логгер
$log = new Logger('stirka_site');

// Основной лог (с ротацией: новый файл каждый день, храним 30 дней)
$fileHandler = new RotatingFileHandler(__DIR__ . '/logs/app.log', 30, Logger::DEBUG);
$formatter = new LineFormatter(
    "[%datetime%] %level_name% : %message% %context% %extra%\n",
    "Y-m-d H:i:s"
);
$fileHandler->setFormatter($formatter);
$log->pushHandler($fileHandler);

// Отдельный файл ТОЛЬКО для ошибок (удобно для мониторинга)
$errorHandler = new StreamHandler(__DIR__ . '/logs/error.log', Logger::ERROR);
$log->pushHandler($errorHandler);

// ===================== ПРИМЕРЫ ИСПОЛЬЗОВАНИЯ =====================
// $log->debug('Отладка', ['user_id' => 5]);
// $log->info('Пользователь вошёл в систему', ['admin_id' => $_SESSION['admin_id'] ?? null, 'ip' => $_SERVER['REMOTE_ADDR']]);
// $log->warning('Неудачная попытка входа');
// $log->error('Ошибка SQL', ['exception' => $e->getMessage(), 'query' => $sql]);
// $log->critical('Сайт упал!');