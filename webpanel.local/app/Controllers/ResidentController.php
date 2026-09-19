<?php
namespace App\Controllers;

use Models\Resident;
use Models\Dormitory;

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
                $resident->notify_unconfirmed = !empty($_POST['notify_unconfirmed']);
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

                $resident->dormitory_id = $sessionDormId !== null ? $sessionDormId : (int)($_POST['dormitory_id'] ?? 1);
                $resident->last_name    = trim($_POST['last_name'] ?? '');
                $resident->first_name   = trim($_POST['first_name'] ?? '');
                $resident->patronymic   = trim($_POST['patronymic'] ?? '');
                $resident->inidroom     = trim($_POST['inidroom'] ?? '');
                $resident->idcards      = trim($_POST['idcards'] ?? '');
                $resident->notify_unconfirmed = !empty($_POST['notify_unconfirmed']);
                if ($resident->save()) {
                    $this->log->info('Отредактирован житель', ['id' => $resident->id, 'dormitory_id' => $resident->dormitory_id, 'role' => $roleName]);
                    $successMessage = 'Данные жителя обновлены!';
                } else {
                    $errorMessage = $resident->getLastError() ?: 'Ошибка при сохранении жителя.';
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
                    'notify_unconfirmed' => $editResidentObj->notify_unconfirmed
                ];
            }
        }

        // Если пользователь привязан к корпусу — фиксируем фильтр!
        $dormitory_id = $sessionDormId !== null ? $sessionDormId : ($_GET['dormitory_id'] ?? '');
        $residents    = Resident::getAll($this->pdo, $dormitory_id);
        $dormitories  = Dormitory::getAll($this->pdo);

        $this->render('residents', [
            'residents'     => $residents,
            'dormitories'   => $dormitories,
            'dormitory_id'  => $dormitory_id,
            'sessionDormId' => $sessionDormId,
            'editResident'  => $editResident,
            'roleName'      => $roleName,
            'success'       => $_GET['success'] ?? null,
            'error'         => $_GET['error'] ?? null
        ]);
    }
}