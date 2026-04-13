<?php
// login.php — ПОЛНАЯ СТРАНИЦА ВХОДА (GET — форма, POST — обработка)

session_start();
$root = dirname(__DIR__);
require_once $root . '/config/logger.php';

require_once $root . '/config/db_connect.php';
if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
    die('Критическая ошибка подключения к базе.');
}

use Models\Administrator;

$pdo = $GLOBALS['pdo'];

// Если уже авторизован — сразу на главную
if (isset($_SESSION['admin_id'])) {
    header('Location: /booking.php');
    exit;
}

// ==================== ОБРАБОТКА POST ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $log->info('Попытка входа в админ-панель', ['ip' => $_SERVER['REMOTE_ADDR']]);

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        header('Location: /login?error=Заполните все поля');
        exit;
    }

    // Используем модель вместо сырого SQL
    $user = Administrator::findByUsername($pdo, $username);

    if ($user && password_verify($password, $user->password_hash)) {

        $_SESSION['admin_id'] = $user->id;
        $_SESSION['username'] = $user->username;
        $_SESSION['role']     = $user->role;

        $log->info('✅ Успешный вход', [
            'admin_id' => $user->id,
            'username' => $user->username,
            'role'     => $user->role,
            'ip'       => $_SERVER['REMOTE_ADDR']
        ]);

        header('Location: /booking.php');
        exit;

    } else {
        $log->warning('❌ Неудачная попытка входа', [
            'username' => $username,
            'ip'       => $_SERVER['REMOTE_ADDR']
        ]);

        header('Location: /login?error=Неверный логин или пароль');
        exit;
    }
}
?>

<?php require_once $root . '/templates/header.php'; ?>

<div style="flex: 1; 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); 
            padding: 20px;">

    <div style="position: relative; max-width: 420px; width: 100%; background: white; 
                padding: 50px 40px 40px; border-radius: 16px; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.15);">

        <!-- Кнопка «Вернуться назад» -->
        <a href="/booking.php" 
           style="position: absolute; top: 20px; left: 20px; 
                  color: #1976d2; text-decoration: none; font-size: 15px; 
                  display: flex; align-items: center; gap: 4px;">
            ← Вернуться назад
        </a>

        <!-- Иконка ключа -->
        <div style="text-align: center; margin-bottom: 25px;">
            <span style="font-size: 52px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">🔑</span>
        </div>

        <h1 style="text-align: center; margin-bottom: 35px; font-size: 26px; color: #263238;">
            Вход в админ-панель
        </h1>

        <?php if (isset($_GET['error'])): ?>
            <div style="background: #ffebee; color: #c62828; padding: 14px; 
                        border-radius: 8px; margin-bottom: 25px; text-align: center; font-size: 15px;">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input 
                type="text" 
                name="username" 
                required 
                autofocus
                placeholder="Введите логин"
                style="width: 100%; padding: 16px 18px; margin-bottom: 18px; 
                       border: 2px solid #ddd; border-radius: 10px; font-size: 16px; 
                       box-sizing: border-box;"
            >

            <input 
                type="password" 
                name="password" 
                required
                placeholder="Введите пароль"
                style="width: 100%; padding: 16px 18px; margin-bottom: 28px; 
                       border: 2px solid #ddd; border-radius: 10px; font-size: 16px; 
                       box-sizing: border-box;"
            >

            <button 
                type="submit" 
                style="width: 100%; padding: 16px; background: #1976d2; color: white; 
                       border: none; border-radius: 10px; font-size: 17px; font-weight: 600; 
                       cursor: pointer; transition: background 0.2s;"
                onmouseover="this.style.background='#1565c0'"
                onmouseout="this.style.background='#1976d2'"
            >
                Войти
            </button>
        </form>

        <p style="text-align: center; margin-top: 25px; color: #666; font-size: 14px;">
            Только администраторам и техническому персоналу
        </p>
    </div>
</div>

<?php require_once $root . '/templates/footer.php'; ?>