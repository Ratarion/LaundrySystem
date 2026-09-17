<?php
namespace App\Controllers;

use Models\Dormitory;

class DormitoryController extends BaseController
{
    public function index()
    {
        $this->log->info('Открыта страница Общежития', ['ip' => $_SERVER['REMOTE_ADDR']]);

        if (!isset($_SESSION['admin_id'])) {
            $this->redirect('/login?error=' . urlencode('Доступ запрещён. Пожалуйста, войдите в систему.'));
        }

        // Только Председатель студгородка (главный админ без привязки к конкретному общежитию) может управлять общежитиями
        if (($_SESSION['role'] ?? 0) !== 1 || !empty($_SESSION['dormitory_id'])) {
            $this->redirect('/booking?error=' . urlencode('Недостаточно прав для управления общежитиями.'));
        }

        $role = $_SESSION['role'] ?? 0;
        $roleName = getUserRoleTitle($role, null, null);
        $successMessage = '';
        $errorMessage = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['add_dormitory'])) {
                $dorm = new Dormitory($this->pdo);
                $dorm->number  = (int)($_POST['number'] ?? 0);
                $dorm->name    = trim($_POST['name'] ?? '');
                $dorm->address = trim($_POST['address'] ?? '');

                if ($dorm->number > 0 && !empty($dorm->name)) {
                    $dorm->save();
                    $this->log->info('Добавлено новое общежитие', ['number' => $dorm->number, 'name' => $dorm->name]);
                    $successMessage = 'Общежитие успешно добавлено!';
                } else {
                    $errorMessage = 'Пожалуйста, укажите номер и название общежития.';
                }
            }

            if (isset($_POST['edit_dormitory'])) {
                $dorm = new Dormitory($this->pdo);
                $dorm->load((int)$_POST['id']);
                $dorm->number  = (int)($_POST['number'] ?? 0);
                $dorm->name    = trim($_POST['name'] ?? '');
                $dorm->address = trim($_POST['address'] ?? '');

                if ($dorm->number > 0 && !empty($dorm->name)) {
                    $dorm->save();
                    $this->log->info('Отредактировано общежитие', ['id' => $dorm->id]);
                    $successMessage = 'Данные общежития обновлены!';
                } else {
                    $errorMessage = 'Пожалуйста, укажите корректные данные.';
                }
            }

            if (isset($_POST['delete_id'])) {
                $dorm = new Dormitory($this->pdo);
                $dorm->load((int)$_POST['delete_id']);
                $dorm->delete();
                $this->log->info('Удалено общежитие', ['id' => $_POST['delete_id']]);
                $successMessage = 'Общежитие удалено!';
            }

            if ($successMessage) {
                $this->redirect("/dormitories?success=" . urlencode($successMessage));
            }
            if ($errorMessage) {
                $this->redirect("/dormitories?error=" . urlencode($errorMessage));
            }
        }

        $editDorm = null;
        if (isset($_GET['edit'])) {
            $editObj = new Dormitory($this->pdo);
            if ($editObj->load((int)$_GET['edit'])) {
                $editDorm = [
                    'id'      => $editObj->id,
                    'number'  => $editObj->number,
                    'name'    => $editObj->name,
                    'address' => $editObj->address,
                ];
            }
        }

        $dormitories = Dormitory::getAll($this->pdo);

        $this->render('dormitories', [
            'dormitories' => $dormitories,
            'editDorm'    => $editDorm,
            'roleName'    => $roleName,
            'success'     => $_GET['success'] ?? null,
            'error'       => $_GET['error'] ?? null,
        ]);
    }
}
