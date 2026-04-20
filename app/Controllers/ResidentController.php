<?php
namespace App\Controllers;

use Models\Resident;

class ResidentController extends BaseController
{
    public function index()
    {
        $this->log->info('Открыта страница Пользователи', [
            'ip' => $_SERVER['REMOTE_ADDR'],
            'role' => $_SESSION['role'] ?? 0
        ]);

        if (!isset($_SESSION['admin_id'])) {
            $this->redirect('/booking');
        }

        $role = $_SESSION['role'] ?? 0;
        $roleName = $role === 1 ? 'Администратор' : 'Техник';

        $successMessage = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['add_resident'])) {
                $resident = new Resident($this->pdo);
                $resident->last_name  = trim($_POST['last_name']);
                $resident->first_name = trim($_POST['first_name']);
                $resident->inidroom   = trim($_POST['inidroom']);
                $resident->save();
                $this->log->info('Добавлен новый житель', ['room' => $resident->inidroom, 'role' => $roleName]);
                $successMessage = 'Житель успешно добавлен!';
            }

            if (isset($_POST['edit_resident'])) {
                $resident = new Resident($this->pdo);
                $resident->load((int)$_POST['id']);
                $resident->last_name  = trim($_POST['last_name']);
                $resident->first_name = trim($_POST['first_name']);
                $resident->inidroom   = trim($_POST['inidroom']);
                $resident->save();
                $this->log->info('Отредактирован житель', ['id' => $resident->id, 'role' => $roleName]);
                $successMessage = 'Данные жителя обновлены!';
            }

            if (isset($_POST['delete_id'])) {
                $resident = new Resident($this->pdo);
                $resident->load((int)$_POST['delete_id']);
                $resident->delete();
                $this->log->info('Удалён житель', ['id' => $_POST['delete_id'], 'role' => $roleName]);
                $successMessage = 'Житель успешно удалён!';
            }

            if ($successMessage) {
                $this->redirect("/residents?success=" . urlencode($successMessage));
            }
        }

        $editResident = null;
        if (isset($_GET['edit'])) {
            $editResidentObj = new Resident($this->pdo);
            if ($editResidentObj->load((int)$_GET['edit'])) {
                $editResident = [
                    'id'         => $editResidentObj->id,
                    'last_name'  => $editResidentObj->last_name,
                    'first_name' => $editResidentObj->first_name,
                    'inidroom'   => $editResidentObj->inidroom
                ];
            }
        }

        $residents = Resident::getAll($this->pdo);

        $this->render('residents', [
            'residents'    => $residents,
            'editResident' => $editResident,
            'roleName'     => $roleName,
            'success'      => $_GET['success'] ?? null
        ]);
    }
}