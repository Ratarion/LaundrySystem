<?php
namespace App\Controllers;

use Models\Notification;
use Models\Dormitory;
use App\Services\BotNotifier;

class NotificationController extends BaseController
{
    public function index()
    {
        $this->log->info('Открыта страница Уведомления', ['ip' => $_SERVER['REMOTE_ADDR']]);

        if (!isset($_SESSION['admin_id'])) {
            $this->redirect('/login?error=' . urlencode('Доступ запрещён. Пожалуйста, войдите в систему.'));
        }

        $role = $_SESSION['role'] ?? 0;
        $sessionDormId = !empty($_SESSION['dormitory_id']) ? (int)$_SESSION['dormitory_id'] : null;
        $roleName = getUserRoleTitle($role, $sessionDormId, $_SESSION['dormitory_name'] ?? null);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
            $residentId  = (int)($_POST['resident_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');

            if ($residentId > 0 && !empty($description)) {
                // Проверка прав: житель должен принадлежать общежитию пользователя
                if ($sessionDormId !== null) {
                    $chkStmt = $this->pdo->prepare("SELECT dormitory_id FROM residents WHERE id = ?");
                    $chkStmt->execute([$residentId]);
                    $resDorm = $chkStmt->fetchColumn();
                    if ((int)$resDorm !== $sessionDormId) {
                        $this->redirect("/notifications?error=" . urlencode('Вы можете отправлять уведомления только жителям своего общежития!'));
                    }
                }

                $notification = new Notification($this->pdo);
                $notification->id_residents = $residentId;
                $notification->description  = $description;
                $notification->save();

                // Отправляем уведомление жильцу через Telegram и/или VK
                $botNotifier = new BotNotifier();
                $msg = "📢 <b>Сообщение от администрации прачечной:</b>\n\n" . htmlspecialchars($description);
                $sendResults = $botNotifier->notifyResidentById($this->pdo, $residentId, $msg);

                $this->log->info('Отправлено уведомление через ботов', [
                    'resident_id' => $residentId,
                    'results'     => $sendResults,
                    'role'        => $roleName
                ]);

                $successMsg = 'Уведомление сохранено';
                if ($sendResults['tg'] || $sendResults['vk'] || $sendResults['max']) {
                    $channels = [];
                    if ($sendResults['tg']) $channels[] = 'Telegram';
                    if ($sendResults['vk']) $channels[] = 'VK';
                    if ($sendResults['max']) $channels[] = 'MAX';
                    $successMsg .= ' и доставлено в ' . implode(', ', $channels);
                } else {
                    $successMsg .= ' (у жильца не привязан Telegram/VK/MAX или бот заблокирован)';
                }

                $this->redirect("/notifications?success=" . urlencode($successMsg));
            }
        }

        // Если пользователь привязан к корпусу — фильтруем жестко!
        $dormitory_id  = $sessionDormId !== null ? $sessionDormId : ($_GET['dormitory_id'] ?? '');
        $dormitories   = Dormitory::getAll($this->pdo);
        $notifications = Notification::getAll($this->pdo, $dormitory_id);
        $residents     = Notification::getAllResidents($this->pdo, $dormitory_id);

        $this->render('notifications', [
            'notifications' => $notifications,
            'residents'     => $residents,
            'dormitories'   => $dormitories,
            'dormitory_id'  => $dormitory_id,
            'sessionDormId' => $sessionDormId,
            'roleName'      => $roleName,
            'success'       => $_GET['success'] ?? null,
            'error'         => $_GET['error'] ?? null
        ]);
    }
}