<?php
namespace App\Controllers;

use Models\Resident;
use Models\Dormitory;
use Models\Notification;
use App\Services\BotNotifier;

class ResidentController extends BaseController
{
    public function index()
    {
        $this->log->info('Открыта страница Пользователи', [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'role' => $_SESSION['role'] ?? 0
        ]);

        if (!isset($_SESSION['admin_id'])) {
            $this->redirect('/login?error=' . urlencode('Доступ запрещён. Пожалуйста, войдите в систему.'));
        }

        $role = $_SESSION['role'] ?? 0;
        $sessionDormId = !empty($_SESSION['dormitory_id']) ? (int)$_SESSION['dormitory_id'] : null;
        $roleName = getUserRoleTitle($role, $sessionDormId, $_SESSION['dormitory_name'] ?? null);

        $successMessage = '';
        $errorMessage = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['add_resident'])) {
                $resident = new Resident($this->pdo);
                $resident->dormitory_id = $sessionDormId !== null ? $sessionDormId : (int)($_POST['dormitory_id'] ?? 1);
                $resident->last_name    = trim($_POST['last_name'] ?? '');
                $resident->first_name   = trim($_POST['first_name'] ?? '');
                $resident->patronymic   = trim($_POST['patronymic'] ?? '');
                $resident->inidroom     = trim($_POST['inidroom'] ?? '');
                $resident->idcards      = trim($_POST['idcards'] ?? '');
                $resident->notify_unconfirmed = (isset($_POST['notify_unconfirmed']) && (string)$_POST['notify_unconfirmed'] === '1');
                $resident->is_banned = (isset($_POST['is_banned']) && (string)$_POST['is_banned'] === '1');
                if ($resident->save()) {
                    $this->log->info('Добавлен новый житель', ['room' => $resident->inidroom, 'dormitory_id' => $resident->dormitory_id, 'role' => $roleName]);
                    $successMessage = 'Житель успешно добавлен!';
                } else {
                    $errorMessage = $resident->getLastError() ?: 'Ошибка при добавлении жителя.';
                }
            }

            if (isset($_POST['edit_resident'])) {
                $resident = new Resident($this->pdo);
                $resident->load((int)$_POST['id']);

                if ($sessionDormId !== null && (int)$resident->dormitory_id !== $sessionDormId) {
                    $this->redirect("/residents?error=" . urlencode('Вы можете редактировать только жителей своего общежития!'));
                }

                $wasBanned = (bool)$resident->is_banned;
                $newBanned = (isset($_POST['is_banned']) && (string)$_POST['is_banned'] === '1');

                $resident->dormitory_id = $sessionDormId !== null ? $sessionDormId : (int)($_POST['dormitory_id'] ?? 1);
                $resident->last_name    = trim($_POST['last_name'] ?? '');
                $resident->first_name   = trim($_POST['first_name'] ?? '');
                $resident->patronymic   = trim($_POST['patronymic'] ?? '');
                $resident->inidroom     = trim($_POST['inidroom'] ?? '');
                $resident->idcards      = trim($_POST['idcards'] ?? '');
                $resident->notify_unconfirmed = (isset($_POST['notify_unconfirmed']) && (string)$_POST['notify_unconfirmed'] === '1');
                $resident->is_banned = $newBanned;
                if ($resident->save()) {
                    $this->log->info('Отредактирован житель', ['id' => $resident->id, 'dormitory_id' => $resident->dormitory_id, 'role' => $roleName]);
                    $successMessage = 'Данные жителя обновлены!';
                    if ($wasBanned !== $newBanned) {
                        $notifyResult = $this->sendBanNotification($resident);
                        if ($notifyResult) {
                            $successMessage .= " {$notifyResult}";
                        }
                    }
                } else {
                    $errorMessage = $resident->getLastError() ?: 'Ошибка при сохранении жителя.';
                }
            }

            if (isset($_POST['toggle_ban_id'])) {
                $resident = new Resident($this->pdo);
                if ($resident->load((int)$_POST['toggle_ban_id'])) {
                    if ($sessionDormId !== null && (int)$resident->dormitory_id !== $sessionDormId) {
                        $this->redirect("/residents?error=" . urlencode('Вы можете изменять статус только жителей своего общежития!'));
                    }
                    $resident->is_banned = !$resident->is_banned;
                    if ($resident->save()) {
                        $action = $resident->is_banned ? 'заблокирован' : 'разблокирован';
                        $this->log->info("Житель {$action}", ['id' => $resident->id, 'role' => $roleName]);
                        
                        $notifyResult = $this->sendBanNotification($resident);
                        $successMessage = "Житель успешно {$action}!" . ($notifyResult ? " {$notifyResult}" : "");
                    } else {
                        $errorMessage = 'Ошибка при изменении статуса жителя.';
                    }
                }
            }

            if (isset($_POST['adjust_score'])) {
                $residentId = (int)$_POST['resident_id'];
                $delta      = (int)($_POST['score_delta'] ?? 0);
                $reason     = trim($_POST['score_reason'] ?? 'ADMIN_ADJUSTMENT');
                $details    = trim($_POST['score_details'] ?? '');

                $resident = new Resident($this->pdo);
                if ($resident->load($residentId)) {
                    $wasBanned = (bool)$resident->is_banned;
                    if ($sessionDormId !== null && (int)$resident->dormitory_id !== $sessionDormId) {
                        $this->redirect("/residents?error=" . urlencode('Вы можете изменять баллы только жителей своего общежития!'));
                    }
                    if (Resident::adjustScore($this->pdo, $residentId, $delta, $reason, $details)) {
                        $resident->load($residentId);
                        $sign = $delta > 0 ? "+{$delta}" : "{$delta}";
                        $notifyResult = '';
                        if (!$wasBanned && $resident->is_banned) {
                            $notifyResult = ' ' . $this->sendBanNotification($resident);
                        }
                        $this->log->info('Ручная корректировка баллов', ['resident_id' => $residentId, 'delta' => $delta, 'admin' => $_SESSION['username'] ?? 'admin']);
                        $successMessage = "Баллы жителя успешно изменены ({$sign})! Текущий баланс: {$resident->score} б.{$notifyResult}";
                    } else {
                        $errorMessage = 'Ошибка при изменении баллов жителя.';
                    }
                }
            }

            if (isset($_POST['delete_id'])) {
                $resident = new Resident($this->pdo);
                $resident->load((int)$_POST['delete_id']);

                if ($sessionDormId !== null && (int)$resident->dormitory_id !== $sessionDormId) {
                    $this->redirect("/residents?error=" . urlencode('Вы можете удалять только жителей своего общежития!'));
                }

                $resident->delete();
                $this->log->info('Удалён житель', ['id' => $_POST['delete_id'], 'role' => $roleName]);
                $successMessage = 'Житель успешно удалён!';
            }

            if ($successMessage) {
                $this->redirect("/residents?success=" . urlencode($successMessage));
            }
        }

        // AJAX запрос на получение истории баллов жителя
        if (isset($_GET['score_history'])) {
            header('Content-Type: application/json; charset=utf-8');
            $residentId = (int)$_GET['score_history'];
            $logs = \Models\ScoreLog::getByResident($this->pdo, $residentId, 30);
            echo json_encode(['success' => true, 'logs' => $logs], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $editResident = null;
        if (isset($_GET['edit'])) {
            $editResidentObj = new Resident($this->pdo);
            if ($editResidentObj->load((int)$_GET['edit'])) {
                if ($sessionDormId !== null && (int)$editResidentObj->dormitory_id !== $sessionDormId) {
                    $this->redirect("/residents?error=" . urlencode('Вы можете просматривать жителей только своего общежития!'));
                }
                $editResident = [
                    'id'           => $editResidentObj->id,
                    'dormitory_id' => $editResidentObj->dormitory_id,
                    'last_name'    => $editResidentObj->last_name,
                    'first_name'   => $editResidentObj->first_name,
                    'patronymic'   => $editResidentObj->patronymic,
                    'inidroom'           => $editResidentObj->inidroom,
                    'idcards'            => $editResidentObj->idcards,
                    'notify_unconfirmed' => $editResidentObj->notify_unconfirmed,
                    'is_banned'          => $editResidentObj->is_banned,
                    'score'              => $editResidentObj->score,
                    'confirm_streak'     => $editResidentObj->confirm_streak,
                    'miss_streak'        => $editResidentObj->miss_streak
                ];
            }
        }

        // Если пользователь привязан к корпусу — фиксируем фильтр!
        $dormitory_id  = $sessionDormId !== null ? $sessionDormId : ($_GET['dormitory_id'] ?? '');
        $fio           = trim($_GET['fio'] ?? '');
        $room          = trim($_GET['room'] ?? '');
        $idcard        = trim($_GET['idcard'] ?? '');
        $bot_status    = trim($_GET['bot_status'] ?? '');
        $notify_status = isset($_GET['notify_status']) ? trim($_GET['notify_status']) : '';
        $ban_status    = trim($_GET['ban_status'] ?? '');
        $score_status  = trim($_GET['score_status'] ?? '');

        $residents    = Resident::getAll($this->pdo, $dormitory_id, [
            'fio'           => $fio,
            'room'          => $room,
            'idcard'        => $idcard,
            'bot_status'    => $bot_status,
            'notify_status' => $notify_status,
            'ban_status'    => $ban_status,
            'score_status'  => $score_status,
        ]);
        $dormitories  = Dormitory::getAll($this->pdo);

        $this->render('residents', [
            'residents'     => $residents,
            'dormitories'   => $dormitories,
            'dormitory_id'  => $dormitory_id,
            'fio'           => $fio,
            'room'          => $room,
            'idcard'        => $idcard,
            'bot_status'    => $bot_status,
            'notify_status' => $notify_status,
            'ban_status'    => $ban_status,
            'score_status'  => $score_status,
            'sessionDormId' => $sessionDormId,
            'editResident'  => $editResident,
            'roleName'      => $roleName,
            'success'       => $_GET['success'] ?? null,
            'error'         => $errorMessage ?: ($_GET['error'] ?? null)
        ]);
    }

    /**
     * Отправка уведомления жителю при изменении статуса блокировки
     * 
     * @param Resident $resident
     * @return string Текстовый статус доставки в боты
     */
    private function sendBanNotification(Resident $resident): string
    {
        $lang = strtoupper(trim($resident->language ?? 'RU'));

        if ($resident->is_banned) {
            $isScoreBan = ($resident->score !== null && (int)$resident->score <= -1000);
            // Текст при блокировке
            if ($lang === 'EN') {
                $cause = $isScoreBan ? "your discipline score dropped to -1000 points" : "decision of the administration";
                $msgHtml = "🚫 <b>Laundry booking access suspended</b>\n\nYour account has been locked ({$cause}). Laundry booking is unavailable.\n\nPlease contact your dormitory elder or administrator.";
                $plainDesc = "Доступ заблокирован ({$cause}). Обратитесь к старосте/администратору.";
            } elseif ($lang === 'CN') {
                $cause = $isScoreBan ? "您的纪律积分已降至 -1000 分" : "管理员决定";
                $msgHtml = "🚫 <b>洗衣预约权限已暂停</b>\n\n您的账号已被封禁（{$cause}），无法预约洗衣。\n\n如有疑问，请联系宿舍长或管理员。";
                $plainDesc = "账号已被封禁（{$cause}），请联系宿舍长或管理员。";
            } else {
                $cause = $isScoreBan ? "ваш рейтинг дисциплины опустился до -1000 баллов" : "по решению администрации";
                $msgHtml = "🚫 <b>Доступ к записи на стирку заблокирован</b>\n\nВаш аккаунт заблокирован ({$cause}). Запись на стирку недоступна.\n\nПожалуйста, обратитесь к старосте или администратору общежития.";
                $plainDesc = "Доступ заблокирован ({$cause}). Обратитесь к старосте или администратору.";
            }
        } else {
            // Текст при разблокировке
            if ($lang === 'EN') {
                $msgHtml = "✅ <b>Laundry booking access restored</b>\n\nYour laundry booking access has been restored. You can now book laundry slots again.";
                $plainDesc = "Доступ к записи на стирку восстановлен.";
            } elseif ($lang === 'CN') {
                $msgHtml = "✅ <b>洗衣预约权限已恢复</b>\n\n您的洗衣预约权限已恢复，现在可以重新预约洗衣。";
                $plainDesc = "洗衣预约权限已恢复。";
            } else {
                $msgHtml = "✅ <b>Доступ к записи на стирку разблокирован</b>\n\nВаш доступ к записи на стирку восстановлен. Теперь вы снова можете бронировать стирку.";
                $plainDesc = "Доступ к записи на стирку восстановлен.";
            }
        }

        // 1. Сохраняем уведомление в БД (таблица notifications)
        try {
            $notification = new Notification($this->pdo);
            $notification->id_residents = $resident->id;
            $notification->description  = $plainDesc;
            $notification->save();
        } catch (\Exception $e) {
            $this->log->error('Ошибка сохранения уведомления о бане в БД: ' . $e->getMessage());
        }

        // 2. Отправляем через BotNotifier во все подключенные мессенджеры жителя
        $botNotifier = new BotNotifier();
        $sendResults = $botNotifier->notifyResident([
            'id'     => $resident->id,
            'tg_id'  => $resident->tg_id,
            'vk_id'  => $resident->vk_id,
            'max_id' => $resident->max_id,
        ], $msgHtml);

        $delivered = [];
        if (!empty($sendResults['tg']))  $delivered[] = 'Telegram';
        if (!empty($sendResults['vk']))  $delivered[] = 'VK';
        if (!empty($sendResults['max'])) $delivered[] = 'MAX';

        if (!empty($delivered)) {
            return 'Уведомление отправлено в ' . implode(', ', $delivered) . '.';
        } elseif (empty($resident->tg_id) && empty($resident->vk_id) && empty($resident->max_id)) {
            return '(боты у жителя не подключены)';
        } else {
            return '(не удалось доставить в боты)';
        }
    }
}