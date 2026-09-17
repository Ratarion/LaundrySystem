<?php
namespace App\Controllers;

use Models\Machine;
use Models\Dormitory;

class MachineController extends BaseController
{
    public function index()
    {
        $this->log->info('Открыта страница Техника', ['ip' => $_SERVER['REMOTE_ADDR']]);

        if (!isset($_SESSION['admin_id'])) {
            $this->redirect('/login?error=' . urlencode('Доступ запрещён. Пожалуйста, войдите в систему.'));
        }

        $role = $_SESSION['role'] ?? 0;
        $sessionDormId = !empty($_SESSION['dormitory_id']) ? (int)$_SESSION['dormitory_id'] : null;
        $roleName = getUserRoleTitle($role, $sessionDormId, $_SESSION['dormitory_name'] ?? null);

        $successMessage = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['add_machine'])) {
                $num = filter_var(trim($_POST['number_machine'] ?? ''), FILTER_VALIDATE_INT);
                if ($num === false || $num <= 0) {
                    $this->redirect('/machines?error=' . urlencode('Номер машины должен быть положительным целым числом (например: 1, 2, 3...)!'));
                }

                $machine = new Machine($this->pdo);
                $machine->dormitory_id   = $sessionDormId !== null ? $sessionDormId : (int)($_POST['dormitory_id'] ?? 1);
                $machine->type_machine   = trim($_POST['type_machine']);
                $machine->number_machine = $num;
                $machine->status         = (int)$_POST['status'];
                if ($machine->save()) {
                    $this->log->info('Добавлена машина', ['dormitory_id' => $machine->dormitory_id, 'number' => $machine->number_machine]);
                    $successMessage = 'Машина успешно добавлена!';
                } else {
                    $this->redirect('/machines?error=' . urlencode('Ошибка при сохранении машины в базе данных.'));
                }
            }

            if (isset($_POST['edit_machine'])) {
                $num = filter_var(trim($_POST['number_machine'] ?? ''), FILTER_VALIDATE_INT);
                if ($num === false || $num <= 0) {
                    $this->redirect('/machines?error=' . urlencode('Номер машины должен быть положительным целым числом (например: 1, 2, 3...)!'));
                }

                $machine = new Machine($this->pdo);
                $machine->load((int)$_POST['id']);

                if ($sessionDormId !== null && (int)$machine->dormitory_id !== $sessionDormId) {
                    $this->redirect("/machines?error=" . urlencode('Вы можете редактировать только машины своего общежития!'));
                }

                $machine->dormitory_id   = $sessionDormId !== null ? $sessionDormId : (int)($_POST['dormitory_id'] ?? 1);
                $machine->type_machine   = trim($_POST['type_machine']);
                $machine->number_machine = $num;
                $machine->status         = (int)$_POST['status'];
                if ($machine->save()) {
                    $this->log->info('Отредактирована машина', ['id' => $machine->id]);
                    $successMessage = 'Машина успешно обновлена!';
                } else {
                    $this->redirect('/machines?error=' . urlencode('Ошибка при обновлении машины в базе данных.'));
                }
            }

            if (isset($_POST['delete_id'])) {
                $machine = new Machine($this->pdo);
                $machine->load((int)$_POST['delete_id']);

                if ($sessionDormId !== null && (int)$machine->dormitory_id !== $sessionDormId) {
                    $this->redirect("/machines?error=" . urlencode('Вы можете удалять только машины своего общежития!'));
                }

                $machine->delete();
                $this->log->info('Удалена машина', ['id' => $_POST['delete_id']]);
                $successMessage = 'Машина успешно удалена!';
            }

            if (isset($_POST['toggle_id'])) {
                $machine = new Machine($this->pdo);
                $machine->load((int)$_POST['toggle_id']);

                if ($sessionDormId !== null && (int)$machine->dormitory_id !== $sessionDormId) {
                    $this->redirect("/machines?error=" . urlencode('Вы можете переключать статус машин только своего общежития!'));
                }

                $machine->toggleStatus();
                $successMessage = 'Статус машины изменён!';
            }

            if ($successMessage) {
                $this->redirect("/machines?success=" . urlencode($successMessage));
            }
        }

        $editMachine = null;
        if (isset($_GET['edit'])) {
            $editMachineObj = new Machine($this->pdo);
            if ($editMachineObj->load((int)$_GET['edit'])) {
                if ($sessionDormId !== null && (int)$editMachineObj->dormitory_id !== $sessionDormId) {
                    $this->redirect("/machines?error=" . urlencode('Вы можете просматривать машины только своего общежития!'));
                }
                $editMachine = [
                    'id'             => $editMachineObj->id,
                    'dormitory_id'   => $editMachineObj->dormitory_id,
                    'type_machine'   => $editMachineObj->type_machine,
                    'number_machine' => $editMachineObj->number_machine,
                    'status'         => $editMachineObj->status
                ];
            }
        }

        // Если пользователь привязан к корпусу — фиксируем фильтр!
        $dormitory_id = $sessionDormId !== null ? $sessionDormId : ($_GET['dormitory_id'] ?? '');
        $machines     = Machine::getAll($this->pdo, $dormitory_id);
        $dormitories  = Dormitory::getAll($this->pdo);

        $this->render('machines', [
            'machines'      => $machines,
            'dormitories'   => $dormitories,
            'dormitory_id'  => $dormitory_id,
            'sessionDormId' => $sessionDormId,
            'editMachine'   => $editMachine,
            'roleName'      => $roleName,
            'success'       => $_GET['success'] ?? null,
            'error'         => $_GET['error'] ?? null
        ]);
    }
}