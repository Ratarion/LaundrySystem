<?php
namespace App\Controllers;

use Models\Notification;

class NotificationController extends BaseController
{
    public function index()
    {
        $this->log->info('Открыта страница Уведомления', ['ip' => $_SERVER['REMOTE_ADDR']]);

        if (!isset($_SESSION['admin_id'])) {
            $this->redirect('/booking');
        }

        $roleName = ($_SESSION['role'] ?? 0) === 1 ? 'Администратор' : 'Техник';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
            $notification = new Notification($this->pdo);
            $notification->id_residents = (int)$_POST['resident_id'];
            $notification->description  = trim($_POST['description']);
            $notification->save();

            $this->log->info('Отправлено уведомление', [
                'resident_id' => $notification->id_residents,
                'role'        => $roleName
            ]);

            $this->redirect("/notifications?success=Уведомление успешно отправлено!");
        }

        $notifications = Notification::getAll($this->pdo);
        $residents     = Notification::getAllResidents($this->pdo);

        $this->render('notifications', [
            'notifications' => $notifications,
            'residents'     => $residents,
            'roleName'      => $roleName,
            'success'       => $_GET['success'] ?? null
        ]);
    }
}