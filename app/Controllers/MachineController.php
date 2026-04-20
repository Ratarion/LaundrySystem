<?php
namespace App\Controllers;

use Models\Machine;

class MachineController extends BaseController
{
    public function index()
    {
        $this->log->info('Открыта страница Техника', ['ip' => $_SERVER['REMOTE_ADDR']]);

        if (!isset($_SESSION['admin_id'])) {
            $this->redirect('/booking');
        }

        $role = $_SESSION['role'] ?? 0;
        $roleName = $role === 1 ? 'Администратор' : 'Техник';

        $successMessage = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['add_machine'])) {
                $machine = new Machine($this->pdo);
                $machine->type_machine   = trim($_POST['type_machine']);
                $machine->number_machine = trim($_POST['number_machine']);
                $machine->status         = (int)$_POST['status'];
                $machine->save();
                $successMessage = 'Машина успешно добавлена!';
            }

            if (isset($_POST['edit_machine'])) {
                $machine = new Machine($this->pdo);
                $machine->load((int)$_POST['id']);
                $machine->type_machine   = trim($_POST['type_machine']);
                $machine->number_machine = trim($_POST['number_machine']);
                $machine->status         = (int)$_POST['status'];
                $machine->save();
                $successMessage = 'Машина успешно обновлена!';
            }

            if (isset($_POST['delete_id'])) {
                $machine = new Machine($this->pdo);
                $machine->load((int)$_POST['delete_id']);
                $machine->delete();
                $successMessage = 'Машина успешно удалена!';
            }

            if (isset($_POST['toggle_id'])) {
                $machine = new Machine($this->pdo);
                $machine->load((int)$_POST['toggle_id']);
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
                $editMachine = [
                    'id'             => $editMachineObj->id,
                    'type_machine'   => $editMachineObj->type_machine,
                    'number_machine' => $editMachineObj->number_machine,
                    'status'         => $editMachineObj->status
                ];
            }
        }

        $machines = Machine::getAll($this->pdo);

        $this->render('machines', [
            'machines'     => $machines,
            'editMachine'  => $editMachine,
            'roleName'     => $roleName,
            'success'      => $_GET['success'] ?? null
        ]);
    }
}