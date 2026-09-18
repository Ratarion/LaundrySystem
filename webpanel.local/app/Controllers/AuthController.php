<?php
namespace App\Controllers;

use Models\Administrator;

class AuthController extends BaseController
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    /**
     * Получить реальный IP-клиента (с поддержкой reverse proxy Caddy/Beget/Cloudflare)
     */
    private function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = trim(explode(',', $_SERVER['HTTP_X_REAL_IP'])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Проверить блокировку IP по неудачным попыткам входа
     */
    private function checkIpLockout(string $ip): array
    {
        try {
            $windowMinutes = self::LOCKOUT_MINUTES;

            // Время последнего успешного входа с этого IP
            $stmt = $this->pdo->prepare("
                SELECT MAX(timestamp) 
                FROM login_logs 
                WHERE ip_address = ? AND success = TRUE
            ");
            $stmt->execute([$ip]);
            $lastSuccess = $stmt->fetchColumn() ?: '1970-01-01 00:00:00';

            // Неудачные попытки за последние LOCKOUT_MINUTES минут после последнего успеха
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) AS count, MAX(timestamp) AS last_attempt
                FROM login_logs
                WHERE ip_address = ? 
                  AND success = FALSE 
                  AND timestamp > ?
                  AND timestamp > (NOW() - INTERVAL '{$windowMinutes} minutes')
            ");
            $stmt->execute([$ip, $lastSuccess]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            $failedCount = (int)($row['count'] ?? 0);
            $lastAttemptStr = $row['last_attempt'] ?? null;

            if ($failedCount >= self::MAX_LOGIN_ATTEMPTS && $lastAttemptStr) {
                $lastAttemptTime = strtotime($lastAttemptStr);
                $lockoutSeconds = self::LOCKOUT_MINUTES * 60;
                $elapsedSeconds = time() - $lastAttemptTime;
                $remainingSeconds = $lockoutSeconds - $elapsedSeconds;

                if ($remainingSeconds > 0) {
                    $remainingMinutes = (int)ceil($remainingSeconds / 60);
                    return [
                        'is_blocked'        => true,
                        'failed_count'      => $failedCount,
                        'remaining_attempts'=> 0,
                        'remaining_seconds' => $remainingSeconds,
                        'remaining_minutes' => $remainingMinutes,
                    ];
                }
            }

            $remainingAttempts = max(0, self::MAX_LOGIN_ATTEMPTS - $failedCount);
            return [
                'is_blocked'         => false,
                'failed_count'       => $failedCount,
                'remaining_attempts' => $remainingAttempts,
                'remaining_seconds'  => 0,
                'remaining_minutes'  => 0,
            ];
        } catch (\PDOException $e) {
            $this->log->error('Ошибка проверки блокировки IP: ' . $e->getMessage());
            return [
                'is_blocked'         => false,
                'failed_count'       => 0,
                'remaining_attempts' => self::MAX_LOGIN_ATTEMPTS,
                'remaining_seconds'  => 0,
                'remaining_minutes'  => 0,
            ];
        }
    }

    /**
     * Записать попытку входа в таблицу login_logs
     */
    private function recordLoginAttempt(bool $success, string $ip, ?int $adminId = null, ?string $errorMessage = null): void
    {
        try {
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $stmt = $this->pdo->prepare("
                INSERT INTO login_logs (admin_id, success, ip_address, user_agent, error_message, timestamp)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $adminId,
                $success ? 1 : 0,
                $ip,
                mb_substr($userAgent, 0, 500),
                $errorMessage
            ]);
        } catch (\PDOException $e) {
            $this->log->error('Ошибка записи login_logs: ' . $e->getMessage());
        }
    }

    public function login()
    {
        // Уже авторизован → сразу на главную
        if (isset($_SESSION['admin_id'])) {
            $this->redirect('/booking');
        }

        $ip = $this->getClientIp();
        $lockout = $this->checkIpLockout($ip);

        // Если IP заблокирован
        if ($lockout['is_blocked']) {
            $min = $lockout['remaining_minutes'];
            $error = "Превышено максимальное число попыток входа. Доступ с вашего IP временно заблокирован на 15 минут. Попробуйте снова через {$min} мин.";

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                http_response_code(429);
            }

            $this->render('login', [
                'error'             => $error,
                'is_blocked'        => true,
                'remaining_minutes' => $min
            ], false);
            return;
        }

        $error = $_GET['error'] ?? null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->log->info('Попытка входа в админ-панель', ['ip' => $ip]);

            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($password)) {
                $this->redirect('/login?error=' . urlencode('Заполните все поля'));
            }

            $user = Administrator::findByUsername($this->pdo, $username);

            if ($user && password_verify($password, $user->password_hash)) {
                // Успешная попытка входа
                $this->recordLoginAttempt(true, $ip, $user->id, null);

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
                    'ip'           => $ip
                ]);

                $this->redirect('/booking');
            } else {
                // Неудачная попытка входа
                $this->recordLoginAttempt(false, $ip, $user ? $user->id : null, 'Неверный логин или пароль');

                // Перепроверяем блокировку после записи
                $postLockout = $this->checkIpLockout($ip);

                $this->log->warning('❌ Неудачная попытка входа', [
                    'username'     => $username,
                    'ip'           => $ip,
                    'failed_count' => $postLockout['failed_count']
                ]);

                if ($postLockout['is_blocked']) {
                    $min = $postLockout['remaining_minutes'];
                    $errorText = "Превышено максимальное число попыток. Ваш IP-адрес заблокирован на 15 минут. Попробуйте через {$min} мин.";
                } else {
                    $remaining = $postLockout['remaining_attempts'];
                    $errorText = "Неверный логин или пароль. Осталось попыток: {$remaining}";
                }

                $this->redirect('/login?error=' . urlencode($errorText));
            }
        }

        // GET — показ формы
        $this->render('login', [
            'error'      => $error,
            'is_blocked' => false
        ], false);
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