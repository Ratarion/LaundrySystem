<!-- шапка + имя пользователя + роль -->
<?php
if (!function_exists('e')) {
    function e($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Стирка</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { font-family: Arial, sans-serif; background: #121212; color: #fff; margin: 0; }
        .container { display: flex; min-height: 100vh; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; border: 1px solid #333; text-align: left; }
        th { background: #1f1f1f; }
        tr:nth-child(even) { background: #1a1a1a; }
        .btn { padding: 8px 16px; margin: 5px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-danger { background: #d32f2f; color: white; }
        .btn-primary { background: #1976d2; color: white; }
        .filter-form { background: #1f1f1f; padding: 15px; margin-bottom: 20px; border-radius: 8px; }
        div[style*="max-height: 60vh"] {
            scrollbar-width: thin;
            scrollbar-color: #00bcd4 #1f1f1f;
        }
        div[style*="max-height: 60vh"]::-webkit-scrollbar {
            height: 8px;
        }
        div[style*="max-height: 60vh"]::-webkit-scrollbar-thumb {
            background: #00bcd4;
            border-radius: 4px;
        }

        /* === СТИЛИ ДЛЯ МЕНЮ === */
        .menu-link {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px 24px;
            color: #fff;
            text-decoration: none !important;
            font-size: 16px;
            transition: all 0.2s ease;
            border-radius: 0 30px 30px 0;
            margin-bottom: 4px;
        }
        .menu-link:hover {
            background: rgba(255,255,255,0.12);
            color: #fff;
            transform: translateX(4px);
        }
        .menu-link.active {
            background: rgba(255,255,255,0.15);
            font-weight: 600;
            border-left: 4px solid #4caf50;
        }
        .menu-link.logout {
            color: #ff5252;
        }
        .menu-link.logout:hover {
            background: rgba(255,82,82,0.15);
            color: #ff5252;
        }
        
        /* === КНОПКА МАССОВОЙ ОТМЕНЫ === */
        .btn-warning {
            background: #ff9800;
            color: white;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 8px;
        }
        .btn-warning:hover {
            background: #f57c00;
        }

        /* ====================== ВСПЛЫВАЮЩЕЕ УВЕДОМЛЕНИЕ ====================== */
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4caf50;
            color: #fff;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 9999;
            min-width: 320px;
            animation: toastIn 0.3s ease;
        }

        .toast-content {
            font-weight: 600;
        }

        .toast-close {
            background: none;
            border: none;
            color: #fff;
            font-size: 22px;
            cursor: pointer;
            opacity: 0.8;
            line-height: 1;
        }

        .toast-close:hover {
            opacity: 1;
        }

        /* Анимация появления */
        @keyframes toastIn {
            from { transform: translateY(-30px); opacity: 0; }
            to   { transform: translateY(0); opacity: 1; }
        }


        /* Красивый тоггл-переключатель */
        .switch {
            position: relative;
            display: inline-block;
            width: 62px;
            height: 32px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #f44336;
            transition: .3s;
            border-radius: 9999px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 24px;
            width: 24px;
            left: 4px;
            bottom: 4px;
            background: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background: #4caf50;
        }
        input:checked + .slider:before {
            transform: translateX(30px);
        }
    </style>
</head>
<body>
<div class="container">