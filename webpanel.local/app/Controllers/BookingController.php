<?php
namespace App\Controllers;

use Models\Booking;
use Models\Dormitory;
use Models\Machine;
use App\Services\BotNotifier;

class BookingController extends BaseController
{
    public function index()
    {
        $this->log->info('Открыта главная страница', ['ip' => $_SERVER['REMOTE_ADDR']]);

        $role = $_SESSION['role'] ?? 0;
        $isLoggedIn    = isset($_SESSION['admin_id']);
        $isAdmin       = $role === 1;
        $isTechnician  = $role === 2;
        $sessionDormId = !empty($_SESSION['dormitory_id']) ? (int)$_SESSION['dormitory_id'] : null;
        $roleName      = getUserRoleTitle($role, $sessionDormId, $_SESSION['dormitory_name'] ?? null);

        $successMessage = $_GET['success'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
            if (isset($_POST['mass_cancel'])) {
                $date        = trim($_POST['cancel_date'] ?? '');
                $type        = trim($_POST['type_machine'] ?? '');

                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    $this->redirect('/booking?error=' . urlencode('Некорректный формат даты для отмены!'));
                }
                if (!in_array($type, ['Стиральная', 'Сушильная'], true)) {
                    $type = 'Стиральная';
                }

                // Если пользователь привязан к общежитию, он может отменять только своё общежитие!
                $massDormId  = $sessionDormId !== null ? $sessionDormId : (!empty($_POST['cancel_dormitory_id']) ? (int)$_POST['cancel_dormitory_id'] : null);
                $reason      = trim(strip_tags($_POST['cancel_reason'] ?? ''));
                if (empty($reason)) {
                    $reason = 'Технические работы в прачечной';
                }
                
                // Получаем список затронутых бронирований до физической отмены
                $affectedBookings = Booking::getAffectedByMassCancel($this->pdo, $date, $type, $massDormId);
                
                $result = Booking::massCancel($this->pdo, $date, $type, $massDormId);

                if ($result) {
                    $this->log->info('Массовая отмена', ['date' => $date, 'type' => $type, 'dormitory_id' => $massDormId, 'role' => $roleName, 'reason' => $reason]);
                    
                    // Уведомляем пользователей через Telegram, VK и MAX
                    $botNotifier = new BotNotifier();
                    $notifiedCount = 0;
                    foreach ($affectedBookings as $b) {
                        $startTime = strtotime($b['start_time']);
                        $endTime   = !empty($b['end_time']) ? strtotime($b['end_time']) : ($startTime + 90 * 60);
                        if (date('Y-m-d', $startTime) === date('Y-m-d', $endTime)) {
                            $timeStr = date('d.m.Y H:i', $startTime) . ' - ' . date('H:i', $endTime);
                        } else {
                            $timeStr = date('d.m.Y H:i', $startTime) . ' - ' . date('d.m.Y H:i', $endTime);
                        }
                        $typeStr = mb_strtolower($b['type_machine'] ?? $type ?? 'стиральная');
                        $numStr  = $b['number_machine'] ?? '';
                        $dormStr = !empty($b['dormitory_name']) ? " ({$b['dormitory_name']})" : '';
                        $name    = !empty($b['first_name']) ? "Здравствуйте, {$b['first_name']}!" : "Здравствуйте!";
                        
                        $msg     = "⚠️ <b>Внимание: отмена бронирования</b>\n\n"
                                 . "{$name}\n"
                                 . "Ваше бронирование (<b>{$typeStr} машина №{$numStr}</b>{$dormStr} на <b>{$timeStr}</b>) было отменено администратором.\n\n"
                                 . "💬 <b>Причина отмены:</b> " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
                        
                        $res = $botNotifier->notifyResident($b, $msg, true);
                        if (!empty($res['tg']) || !empty($res['vk']) || !empty($res['max'])) {
                            $notifiedCount++;
                        }

                        // Сохраняем уведомление в БД
                        try {
                            if (!empty($b['inidresidents'])) {
                                $stmtN = $this->pdo->prepare("INSERT INTO notifications (id_residents, id_machines, description) VALUES (?, ?, ?)");
                                $stmtN->execute([
                                    $b['inidresidents'],
                                    $b['inidmachine'] ?? null,
                                    "Массовая отмена ({$timeStr}, {$typeStr} #{$numStr}): {$reason}"
                                ]);
                            }
                        } catch (\Exception $e) {
                            $this->log->error("Ошибка сохранения уведомления: " . $e->getMessage());
                        }
                    }

                    $totalCount = count($affectedBookings);
                    $msgText = "Массовая отмена выполнена! Отменено записей: {$totalCount}. Уведомления разосланы жильцам в боты.";
                    $this->redirect('/booking?success=' . urlencode($msgText));
                } else {
                    $this->log->error('Ошибка массовой отмены');
                    die('Ошибка при массовой отмене.');
                }
            }

            if ($isAdmin && isset($_POST['cancel_id'])) {
                $id = (int)$_POST['cancel_id'];
                $reason = trim($_POST['cancel_reason'] ?? '');
                if (empty($reason)) {
                    $reason = 'По решению администратора';
                }
                
                $booking = new Booking($this->pdo);
                $isLoaded = $booking->load($id);

                if ($isLoaded && $sessionDormId !== null && (int)$booking->dormitory_id !== $sessionDormId) {
                    $this->log->warning('Попытка отмены чужого бронирования', ['booking_id' => $id, 'user_dorm' => $sessionDormId]);
                    $this->redirect('/booking?error=' . urlencode('Вы можете отменять бронирования только своего общежития!'));
                }
                
                $result = Booking::cancelOne($this->pdo, $id);

                if ($result) {
                    $this->log->info('Отменена запись', ['booking_id' => $id, 'role' => $roleName, 'reason' => $reason]);
                    
                    if ($isLoaded) {
                        $botNotifier = new BotNotifier();
                        $startTime = strtotime($booking->start_time);
                        $endTime   = !empty($booking->end_time) ? strtotime($booking->end_time) : ($startTime + 90 * 60);
                        if (date('Y-m-d', $startTime) === date('Y-m-d', $endTime)) {
                            $timeStr = date('d.m.Y H:i', $startTime) . ' - ' . date('H:i', $endTime);
                        } else {
                            $timeStr = date('d.m.Y H:i', $startTime) . ' - ' . date('d.m.Y H:i', $endTime);
                        }
                        $typeStr = mb_strtolower($booking->type_machine ?? 'стиральная');
                        $numStr  = $booking->number_machine ?? '';
                        $dormStr = !empty($booking->dormitory_name) ? " ({$booking->dormitory_name})" : '';
                        $name    = !empty($booking->resident_name) ? "Здравствуйте, {$booking->resident_name}!" : "Здравствуйте!";
                        
                        $msg     = "⚠️ <b>Внимание: отмена бронирования</b>\n\n"
                                 . "{$name}\n"
                                 . "Ваше бронирование (<b>{$typeStr} машина №{$numStr}</b>{$dormStr} на <b>{$timeStr}</b>) было отменено администратором.\n\n"
                                 . "💬 <b>Причина отмены:</b> " . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
                        
                        $notifyResult = $botNotifier->notifyResident([
                            'tg_id'  => $booking->tg_id,
                            'vk_id'  => $booking->vk_id,
                            'max_id' => $booking->max_id,
                        ], $msg, true);

                        // Сохраняем уведомление в БД
                        try {
                            if (!empty($booking->inidresidents)) {
                                $stmtN = $this->pdo->prepare("INSERT INTO notifications (id_residents, id_machines, description) VALUES (?, ?, ?)");
                                $stmtN->execute([
                                    $booking->inidresidents,
                                    $booking->inidmachine ?? null,
                                    "Отмена бронирования ({$timeStr}): {$reason}"
                                ]);
                            }
                        } catch (\Exception $e) {
                            $this->log->error("Ошибка сохранения уведомления: " . $e->getMessage());
                        }

                        $this->log->info('Результат отправки бот-уведомления', ['booking_id' => $id, 'results' => $notifyResult]);
                    }

                    $this->redirect('/booking?success=' . urlencode('Запись отменена. Уведомление с причиной отправлено жителю.'));
                } else {
                    $this->log->error('Ошибка при одиночной отмене (Booking::cancelOne вернул false)', ['booking_id' => $id]);
                }
            }
        }

        // Фильтры: если пользователь привязан к общежитию — принудительно фиксируем его общежитие!
        if ($sessionDormId !== null) {
            $dormitory_id = $sessionDormId;
        } else {
            $dormitory_id = !empty($_REQUEST['dormitory_id']) ? (int)$_REQUEST['dormitory_id'] : '';
        }

        $machine_id = !empty($_REQUEST['machine_id']) ? (int)$_REQUEST['machine_id'] : '';
        $validStatuses = ['Ожидание', 'Ожидание подтверждения', 'Подтверждено', 'Отменено'];
        $status = in_array($_REQUEST['status'] ?? '', $validStatuses, true) ? $_REQUEST['status'] : '';
        $fio = trim($_REQUEST['fio'] ?? '');

        // Выбранная дата: одиночная или диапазон
        $single_date = trim($_REQUEST['date'] ?? '');
        if (!empty($single_date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $single_date)) {
            $single_date = '';
        }

        if (!empty($single_date)) {
            $date_from = $single_date;
            $date_to   = $single_date;
        } else {
            $date_from = trim($_REQUEST['date_from'] ?? '');
            $date_to   = trim($_REQUEST['date_to'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
                $date_from = date('Y-m-d');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
                $date_to = date('Y-m-d', strtotime('+1 day'));
            }
        }

        $bookings    = Booking::getAll($this->pdo, $date_from, $date_to, $status, $dormitory_id, $machine_id, $fio);
        $dormitories = Dormitory::getAll($this->pdo);
        $machines    = Machine::getAll($this->pdo, $dormitory_id ?: null);

        $this->render('booking', [
            'bookings'      => $bookings,
            'dormitories'   => $dormitories,
            'dormitory_id'  => $dormitory_id,
            'sessionDormId' => $sessionDormId,
            'machines'      => $machines,
            'machine_id'    => $machine_id,
            'isLoggedIn'    => $isLoggedIn,
            'isAdmin'       => $isAdmin,
            'roleName'      => $roleName,
            'single_date'   => $single_date,
            'date_from'     => $date_from,
            'date_to'       => $date_to,
            'status'        => $status,
            'fio'           => $fio,
            'success'       => $successMessage,
            'error'         => $_GET['error'] ?? ''
        ], $isLoggedIn);
    }
}