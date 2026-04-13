<?php
// config/logger.php — ЛОГГЕР (Monolog)

$root = dirname(__DIR__);  // корень проекта

require_once $root . '/vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

// Создаём логгер
$log = new Logger('stirka_site');

// Основной лог (с ротацией)
$fileHandler = new RotatingFileHandler($root . '/logs/app.log', 30, Logger::DEBUG);
$formatter = new LineFormatter(
    "[%datetime%] %level_name% : %message% %context% %extra%\n",
    "Y-m-d H:i:s"
);
$fileHandler->setFormatter($formatter);
$log->pushHandler($fileHandler);

// Отдельный файл только для ошибок
$errorHandler = new StreamHandler($root . '/logs/error.log', Logger::ERROR);
$log->pushHandler($errorHandler);