<?php
namespace App\Controllers;

use Models\Administrator;

class AuthController extends BaseController
{
    public function login()
    {
        // require_once logger.php УБРАЛИ — он уже загружен в BaseController

        // Уже авторизован → сразу на главную
        if (isset($_SESSION['admin_id'])) {
            $this->redirect('/booking');
        }

        $error = $_GET['error'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->log->info('Попытка входа в админ-панель', ['ip' => $_SERVER['REMOTE_ADDR']]);

            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $this->redirect('/login?error=Заполните все поля');
            }

            $user = Administrator::findByUsername($this->pdo, $username);

            if ($user && password_verify($password, $user->password_hash)) {
                // Защита от Session Fixation
                session_regenerate_id(true);

                $_SESSION['admin_id']       = $user->id;
                $_SESSION['username']       = $user->username;
                $_SESSION['role']           = $user->role;
                $_SESSION['dormitory_id']   = $user->dormitory_id;
                $_SESSION['dormitory_name'] = $user->dormitory_name;

                $this->log->info('✅ Успешный вход', [
                    'admin_id'     => $user->id,
                    'username'     => $user->username,
                    'role'         => $user->role,
                    'dormitory_id' => $user->dormitory_id,
                    'ip'           => $_SERVER['REMOTE_ADDR']
                ]);

                $this->redirect('/booking');
            } else {
                $this->log->warning('❌ Неудачная попытка входа', [
                    'username' => $username,
                    'ip'       => $_SERVER['REMOTE_ADDR']
                ]);
                $this->redirect('/login?error=Неверный логин или пароль');
            }
        }

        // GET — показ формы
        $this->render('login', ['error' => $error], false); // без navbar
    }

    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        session_destroy();

        // Исправленная проверка
        if (isset($this->log)) {
            $this->log->info('Пользователь вышел из системы.');
        }

        $this->redirect('/booking');
    }

    public function changePassword()
    {
        // Проверка авторизации
        if (!isset($_SESSION['admin_id'])) {
            $this->redirect('/login?error=' . urlencode('Доступ запрещён. Пожалуйста, войдите в систему.'));
        }

        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newPass = $_POST['new_password'] ?? '';
            
            if (strlen($newPass) < 4) {
                $message = "Пароль слишком короткий";
            } else {
                $admin = new Administrator($this->pdo);
                if ($admin->load($_SESSION['admin_id'])) {
                    $admin->setPassword($newPass); // Хешируем
                    if ($admin->save()) {
                        $message = "Пароль успешно обновлен!";
                        $this->log->info("Админ {$_SESSION['username']} сменил пароль");
                    }
                }
            }
        }

        $this->render('change_password', ['message' => $message]);
    }
}