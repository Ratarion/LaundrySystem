<?php
namespace App\Controllers;

use Models\Booking;

class BookingController extends BaseController
{
    public function index()
    {
        $this->log->info('Открыта главная страница', ['ip' => $_SERVER['REMOTE_ADDR']]);

        $role = $_SESSION['role'] ?? 0;
        $isLoggedIn   = isset($_SESSION['admin_id']);
        $isAdmin      = $role === 1;
        $isTechnician = $role === 2;
        $roleName     = $role === 1 ? 'Администратор' : ($role === 2 ? 'Техник' : 'Житель');

        $successMessage = $_GET['success'] ?? '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {
            if (isset($_POST['mass_cancel'])) {
                $date = $_POST['cancel_date'];
                $type = $_POST['type_machine'];
                $result = Booking::massCancel($this->pdo, $date, $type);

                if ($result) {
                    $this->log->info('Массовая отмена', ['date' => $date, 'type' => $type, 'role' => $roleName]);
                    $this->redirect('/booking?success=Массовая отмена выполнена!');
                } else {
                    $this->log->error('Ошибка массовой отмены');
                    die('Ошибка при массовой отмене.');
                }
            }

            if ($isAdmin && isset($_POST['cancel_id'])) {
                $id = (int)$_POST['cancel_id'];
                $result = Booking::cancelOne($this->pdo, $id);

                if ($result) {
                    $this->log->info('Отменена запись', ['booking_id' => $id, 'role' => $roleName]);
                    $this->redirect('/booking?success=Запись отменена');
                }
            }
        }

        $date_from = $_POST['date_from'] ?? date('Y-m-d');
        $date_to   = $_POST['date_to']   ?? date('Y-m-d');
        $status    = $_POST['status']    ?? '';

        $bookings = Booking::getAll($this->pdo, $date_from, $date_to, $status);

        $this->render('booking', [
            'bookings'     => $bookings,
            'isLoggedIn'   => $isLoggedIn,
            'isAdmin'      => $isAdmin,
            'roleName'     => $roleName,
            'date_from'    => $date_from,
            'date_to'      => $date_to,
            'status'       => $status,
            'success'      => $successMessage
        ], $isLoggedIn);
    }
}