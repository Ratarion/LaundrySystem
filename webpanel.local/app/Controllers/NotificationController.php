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
            $residentTarget = trim($_POST['resident_id'] ?? '');
            $description    = trim($_POST['description'] ?? '');
            $filterDormId   = !empty($_POST['filter_dormitory_id']) ? (int)$_POST['filter_dormitory_id'] : null;

            // Формируем URL для возврата с сохранением текущего фильтра
            $backUrl = '/notifications' . ($filterDormId ? '?dormitory_id=' . $filterDormId : '');
            $sep = (strpos($backUrl, '?') !== false) ? '&' : '?';

            if (empty($description)) {
                $this->redirect($backUrl . $sep . 'error=' . urlencode('Пожалуйста, укажите текст уведомления.'));
            }

            if (empty($residentTarget)) {
                $this->redirect($backUrl . $sep . 'error=' . urlencode('Пожалуйста, выберите получателя.'));
            }

            $isMass = false;
            $targetResidents = [];
            $targetLabel = '';

            if ($residentTarget === 'all') {
                $isMass = true;
                if ($sessionDormId !== null) {
                    $targetResidents = Notification::getAllResidents($this->pdo, $sessionDormId);
                    $targetLabel = 'всем жильцам вашего общежития';
                } elseif (!empty($filterDormId)) {
                    $targetResidents = Notification::getAllResidents($this->pdo, $filterDormId);
                    $targetLabel = 'всем жильцам выбранного общежития';
                } else {
                    $targetResidents = Notification::getAllResidents($this->pdo, null);
                    $targetLabel = 'всем жильцам всех общежитий';
                }
            } elseif ($residentTarget === 'all_everywhere') {
                if ($sessionDormId !== null) {
                    $this->redirect($backUrl . $sep . 'error=' . urlencode('Вы можете отправлять уведомления только жителям своего общежития!'));
                }
                $isMass = true;
                $targetResidents = Notification::getAllResidents($this->pdo, null);
                $targetLabel = 'всем жильцам всех общежитий';
            } elseif (strpos($residentTarget, 'dorm_') === 0) {
                $targetDormId = (int)substr($residentTarget, 5);
                if ($sessionDormId !== null && $sessionDormId !== $targetDormId) {
                    $this->redirect($backUrl . $sep . 'error=' . urlencode('Вы можете отправлять уведомления только жителям своего общежития!'));
                }
                $isMass = true;
                $targetResidents = Notification::getAllResidents($this->pdo, $targetDormId);
                $targetLabel = 'всем жильцам общежития №' . $targetDormId;
            } elseif (is_numeric($residentTarget)) {
                $residentId = (int)$residentTarget;
                if ($residentId > 0) {
                    if ($sessionDormId !== null) {
                        $chkStmt = $this->pdo->prepare("SELECT dormitory_id FROM residents WHERE id = ?");
                        $chkStmt->execute([$residentId]);
                        $resDorm = $chkStmt->fetchColumn();
                        if ((int)$resDorm !== $sessionDormId) {
                            $this->redirect($backUrl . $sep . 'error=' . urlencode('Вы можете отправлять уведомления только жителям своего общежития!'));
                        }
                    }
                    $chkStmt = $this->pdo->prepare("SELECT id, tg_id, vk_id, max_id, first_name, last_name FROM residents WHERE id = ?");
                    $chkStmt->execute([$residentId]);
                    $res = $chkStmt->fetch(\PDO::FETCH_ASSOC);
                    if ($res) {
                        $targetResidents = [$res];
                    }
                }
            }

            if (empty($targetResidents)) {
                $this->redirect($backUrl . $sep . 'error=' . urlencode('Не найдено жителей для отправки уведомления.'));
            }

            if ($isMass) {
                // Увеличиваем лимит времени выполнения скрипта при массовой рассылке
                set_time_limit(180);

                // 1. Сохраняем уведомления в базу данных для всех целевых жильцов
                $this->pdo->beginTransaction();
                try {
                    $insertStmt = $this->pdo->prepare("
                        INSERT INTO notifications (id_residents, description, create_date)
                        VALUES (?, ?, NOW())
                    ");
                    foreach ($targetResidents as $r) {
                        $insertStmt->execute([$r['id'], $description]);
                    }
                    $this->pdo->commit();
                } catch (\PDOException $e) {
                    $this->pdo->rollBack();
                    $this->log->error('Ошибка массового сохранения уведомлений: ' . $e->getMessage());
                    $this->redirect($backUrl . $sep . 'error=' . urlencode('Ошибка при сохранении уведомлений в базу данных.'));
                }

                // 2. Рассылаем через ботов (Telegram, VK, MAX)
                $botNotifier = new BotNotifier();
                $msg = "📢 <b>Объявление от администрации прачечной:</b>\n\n" . htmlspecialchars($description);

                $stats = [
                    'total'     => count($targetResidents),
                    'delivered' => 0,
                    'tg'        => 0,
                    'vk'        => 0,
                    'max'       => 0,
                    'no_bot'    => 0,
                ];

                foreach ($targetResidents as $resident) {
                    $results = $botNotifier->notifyResident($resident, $msg);
                    $userDelivered = false;
                    if (!empty($results['tg'])) {
                        $stats['tg']++;
                        $userDelivered = true;
                    }
                    if (!empty($results['vk'])) {
                        $stats['vk']++;
                        $userDelivered = true;
                    }
                    if (!empty($results['max'])) {
                        $stats['max']++;
                        $userDelivered = true;
                    }

                    if ($userDelivered) {
                        $stats['delivered']++;
                    } else {
                        $stats['no_bot']++;
                    }
                }

                $this->log->info('Массовая рассылка уведомлений', [
                    'target' => $residentTarget,
                    'stats'  => $stats,
                    'role'   => $roleName
                ]);

                $channels = [];
                if ($stats['tg'] > 0)  $channels[] = "Telegram ({$stats['tg']})";
                if ($stats['vk'] > 0)  $channels[] = "VK ({$stats['vk']})";
                if ($stats['max'] > 0) $channels[] = "MAX ({$stats['max']})";

                $channelsStr = !empty($channels) ? ' (доставлено через ' . implode(', ', $channels) . ')' : '';
                $successMsg = "Уведомление успешно разослано {$targetLabel}: доставлено {$stats['delivered']} из {$stats['total']}{$channelsStr}.";
                if ($stats['no_bot'] > 0) {
                    $successMsg .= " У {$stats['no_bot']} чел. боты не подключены.";
                }

                $this->redirect($backUrl . $sep . 'success=' . urlencode($successMsg));
            } else {
                // Одиночное уведомление
                $resident = $targetResidents[0];
                $residentId = $resident['id'];

                $notification = new Notification($this->pdo);
                $notification->id_residents = $residentId;
                $notification->description  = $description;
                $notification->save();

                $botNotifier = new BotNotifier();
                $msg = "📢 <b>Сообщение от администрации прачечной:</b>\n\n" . htmlspecialchars($description);
                $sendResults = $botNotifier->notifyResident($resident, $msg);

                $this->log->info('Отправлено уведомление через ботов', [
                    'resident_id' => $residentId,
                    'results'     => $sendResults,
                    'role'        => $roleName
                ]);

                $successMsg = 'Уведомление сохранено';
                if (!empty($sendResults['tg']) || !empty($sendResults['vk']) || !empty($sendResults['max'])) {
                    $channels = [];
                    if (!empty($sendResults['tg']))  $channels[] = 'Telegram';
                    if (!empty($sendResults['vk']))  $channels[] = 'VK';
                    if (!empty($sendResults['max'])) $channels[] = 'MAX';
                    $successMsg .= ' и доставлено в ' . implode(', ', $channels);
                } else {
                    $successMsg .= ' (у жильца не привязан Telegram/VK/MAX или бот заблокирован)';
                }

                $this->redirect($backUrl . $sep . 'success=' . urlencode($successMsg));
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