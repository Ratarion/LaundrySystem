<!-- шапка + имя пользователя + роль -->
<?php
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('getUserRoleTitle')) {
    function getUserRoleTitle($role = null, $dormitoryId = null, $dormitoryName = null) {
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
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Стирка</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/flatpickr.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="container">