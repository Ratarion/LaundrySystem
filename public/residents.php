<?php
// residents.php — Управление жителями (Админ + Техник)
session_start();
$root = dirname(__DIR__);
require_once $root . '/config/logger.php';

require_once $root . '/config/db_connect.php';
if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
    die('Критическая ошибка подключения к базе.');
}

use Models\Resident;

$pdo = $GLOBALS['pdo'];

$log->info('Открыта страница Пользователи', ['ip' => $_SERVER['REMOTE_ADDR'], 'role' => $_SESSION['role'] ?? 0]);

// Разрешаем доступ и Админу, и Технику
if (!isset($_SESSION['admin_id'])) {
    header('Location: /booking');
    exit;
}

$role = $_SESSION['role'] ?? 0;
$roleName = $role === 1 ? 'Администратор' : 'Техник';

// ==================== ОБРАБОТКА ДЕЙСТВИЙ ====================
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_resident'])) {
        $resident = new Resident($pdo);
        $resident->last_name  = trim($_POST['last_name']);
        $resident->first_name = trim($_POST['first_name']);
        $resident->inidroom   = trim($_POST['inidroom']);
        $resident->save();
        $log->info('Добавлен новый житель', ['room' => $resident->inidroom, 'role' => $roleName]);
        $successMessage = 'Житель успешно добавлен!';
    }

    if (isset($_POST['edit_resident'])) {
        $resident = new Resident($pdo);
        $resident->load((int)$_POST['id']);
        $resident->last_name  = trim($_POST['last_name']);
        $resident->first_name = trim($_POST['first_name']);
        $resident->inidroom   = trim($_POST['inidroom']);
        $resident->save();
        $log->info('Отредактирован житель', ['id' => $resident->id, 'role' => $roleName]);
        $successMessage = 'Данные жителя обновлены!';
    }

    if (isset($_POST['delete_id'])) {
        $resident = new Resident($pdo);
        $resident->load((int)$_POST['delete_id']);
        $resident->delete();
        $log->info('Удалён житель', ['id' => $_POST['delete_id'], 'role' => $roleName]);
        $successMessage = 'Житель успешно удалён!';
    }

    if ($successMessage) {
        header("Location: /residents?success=" . urlencode($successMessage));
        exit;
    }
}

// ==================== ЗАГРУЗКА ДАННЫХ ====================
$editResident = null;
if (isset($_GET['edit'])) {
    $editResidentObj = new Resident($pdo);
    if ($editResidentObj->load((int)$_GET['edit'])) {
        $editResident = [
            'id'         => $editResidentObj->id,
            'last_name'  => $editResidentObj->last_name,
            'first_name' => $editResidentObj->first_name,
            'inidroom'   => $editResidentObj->inidroom
        ];
    }
}

$residents = Resident::getAll($pdo);
?>

<?php require_once $root . '/templates/header.php'; ?>
<?php require_once $root . '/templates/navbar.php'; ?>

<div style="flex: 1; padding: 20px;">

    <?php if (isset($_GET['success'])): ?>
        <div id="success-toast" class="toast-notification">
            <div class="toast-content">✅ <?= htmlspecialchars($_GET['success']) ?></div>
            <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>👨‍🎓 Пользователи (Жители)</h1>
        <span style="color: #4caf50; font-weight: 600;">
            👤 <?= htmlspecialchars($_SESSION['username']) ?> <small>(<?= $roleName ?>)</small>
        </span>
    </div>

    <!-- Форма добавления / редактирования -->
    <div style="background: #1f1f1f; padding: 25px; border-radius: 12px; margin-bottom: 30px;">
        <h3><?= $editResident ? 'Редактировать жителя' : 'Добавить нового жителя' ?></h3>
        <form method="POST" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
            <?php if ($editResident): ?>
                <input type="hidden" name="id" value="<?= $editResident['id'] ?>">
                <input type="hidden" name="edit_resident" value="1">
            <?php else: ?>
                <input type="hidden" name="add_resident" value="1">
            <?php endif; ?>

            <label style="flex: 1;">Фамилия<br>
                <input type="text" name="last_name" value="<?= htmlspecialchars($editResident['last_name'] ?? '') ?>" required
                       style="width:100%; padding:12px; border-radius:8px; background:#2a2a2a; color:#fff; border:none;">
            </label>

            <label style="flex: 1;">Имя<br>
                <input type="text" name="first_name" value="<?= htmlspecialchars($editResident['first_name'] ?? '') ?>" required
                       style="width:100%; padding:12px; border-radius:8px; background:#2a2a2a; color:#fff; border:none;">
            </label>

            <label style="flex: 1;">Комната<br>
                <input type="text" name="inidroom" value="<?= htmlspecialchars($editResident['inidroom'] ?? '') ?>" required
                       style="width:100%; padding:12px; border-radius:8px; background:#2a2a2a; color:#fff; border:none;">
            </label>

            <button type="submit" class="btn btn-primary" style="padding:12px 30px;">
                <?= $editResident ? 'Сохранить изменения' : 'Добавить жителя' ?>
            </button>

            <?php if ($editResident): ?>
                <a href="/residents" class="btn" style="background:#666; color:#fff; padding:12px 20px; text-decoration:none; border-radius:8px;">Отмена</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ТАБЛИЦА -->
    <div style="max-height: 60vh; overflow-y: auto; border: 2px solid #333; border-radius: 12px; background: #1a1a1a;">
        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
            <thead style="position: sticky; top: 0; z-index: 10; background: #1f1f1f;">
                <tr>
                    <th style="padding:14px 12px; text-align:left; width:80px;">ID</th>
                    <th style="padding:14px 12px; text-align:left;">Фамилия</th>
                    <th style="padding:14px 12px; text-align:left;">Имя</th>
                    <th style="padding:14px 12px; text-align:center; width:120px;">Комната</th>
                    <th style="padding:14px 12px; text-align:center; width:220px;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($residents as $r): ?>
                <tr>
                    <td style="padding:12px;"><?= $r->id ?></td>
                    <td style="padding:12px;"><?= htmlspecialchars($r->last_name) ?></td>
                    <td style="padding:12px;"><?= htmlspecialchars($r->first_name) ?></td>
                    <td style="padding:12px; text-align:center; font-weight:600;"><?= htmlspecialchars($r->inidroom) ?></td>
                    <td style="padding:12px; text-align:center;">
                        <a href="/residents?edit=<?= $r->id ?>" class="btn" style="background:#1976d2;color:#fff;padding:6px 14px;font-size:14px;text-decoration:none;border-radius:6px;margin-right:6px;">Редактировать</a>
                        
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить жителя?')">
                            <input type="hidden" name="delete_id" value="<?= $r->id ?>">
                            <button type="submit" class="btn btn-danger" style="padding:6px 14px;font-size:14px;">Удалить</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once $root . '/templates/footer.php'; ?>