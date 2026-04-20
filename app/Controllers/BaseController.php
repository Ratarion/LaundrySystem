<?php
namespace App\Controllers;

abstract class BaseController
{
    protected $pdo;
    protected $root;
    protected $log;

    public function __construct($pdo)
    {
        $this->pdo  = $pdo;
        $this->root = dirname(__DIR__, 2);

        // Загружаем логгер один раз в конструкторе
        require_once $this->root . '/config/logger.php';
        global $log;
        $this->log = $log;
    }

    protected function render($view, $data = [], $includeNavbar = true)
    {
        extract($data);
        require_once $this->root . '/templates/header.php';

        if ($includeNavbar && isset($_SESSION['admin_id'])) {
            require_once $this->root . '/templates/navbar.php';
        }

        require_once $this->root . '/views/' . $view . '.php';
        require_once $this->root . '/templates/footer.php';
    }

    protected function redirect($url)
    {
        header("Location: $url");
        exit;
    }
}