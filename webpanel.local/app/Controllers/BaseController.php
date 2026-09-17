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

    public static function getRoleTitle($role = null, $dormitoryId = null, $dormitoryName = null)
    {
        $r = $role !== null ? (int)$role : (int)($_SESSION['role'] ?? 0);
        $dId = $dormitoryId !== null ? $dormitoryId : ($_SESSION['dormitory_id'] ?? null);
        $dName = $dormitoryName ?? ($_SESSION['dormitory_name'] ?? null);
        
        if ($r === 1) {
            if (empty($dId)) {
                return 'Председатель студгородка';
            }
            return 'Староста (' . ($dName ?: 'Общежитие №' . $dId) . ')';
        } elseif ($r === 2) {
            if (empty($dId)) {
                return 'Главный техник';
            }
            return 'Техник / Староста этажа (' . ($dName ?: 'Общежитие №' . $dId) . ')';
        }
        return 'Житель';
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

if (!function_exists('App\Controllers\getUserRoleTitle')) {
    function getUserRoleTitle($role = null, $dormitoryId = null, $dormitoryName = null) {
        return BaseController::getRoleTitle($role, $dormitoryId, $dormitoryName);
    }
}