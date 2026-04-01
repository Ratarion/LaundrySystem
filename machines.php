<?php
// machines.php — Управление техникой (полные права у Админа и Техника)
session_start();
require_once __DIR__ . '/logger.php';

require_once __DIR__ . '/db_connect.php';
if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
    die('Критическая ошибка подключения к базе.');
}
$pdo = $GLOBALS['pdo'];

$log->info('Открыта страница Техника', ['ip' => $_SERVER['REMOTE_ADDR']]);

$role = $_SESSION['role'] ?? 0;
$isLoggedIn = isset($_SESSION['admin_id']);

if (!$isLoggedIn) {
    header('Location: /booking');
    exit;
}

$roleName = $role === 1 ? 'Администратор' : 'Техник';

// ==================== ОБРАБОТКА ДЕЙСТВИЙ ====================
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Добавление
    if (isset($_POST['add_machine'])) {
        $type   = trim($_POST['type_machine']);
        $number = trim($_POST['number_machine']);
        $status = (int)$_POST['status'];

        $stmt = $pdo->prepare("INSERT INTO machines (type_machine, number_machine, status) VALUES (?, ?, ?)");
        $stmt->execute([$type, $number, $status]);
        $successMessage = 'Машина успешно добавлена!';
    }

    // Редактирование
    if (isset($_POST['edit_machine'])) {
        $id     = (int)$_POST['id'];
        $type   = trim($_POST['type_machine']);
        $number = trim($_POST['number_machine']);
        $status = (int)$_POST['status'];

        $stmt = $pdo->prepare("UPDATE machines SET type_machine = ?, number_machine = ?, status = ? WHERE id = ?");
        $stmt->execute([$type, $number, $status, $id]);
        $successMessage = 'Машина успешно обновлена!';
    }

    // Удаление
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $stmt = $pdo->prepare("DELETE FROM machines WHERE id = ?");
        $stmt->execute([$id]);
        $successMessage = 'Машина успешно удалена!';
    }

    // Быстрое переключение статуса (Вкл ↔ Выкл)
    if (isset($_POST['toggle_id'])) {
        $id = (int)$_POST['toggle_id'];
        $stmt = $pdo->prepare("UPDATE machines SET status = 1 - status WHERE id = ?");
        $stmt->execute([$id]);
        $successMessage = 'Статус машины изменён!';
    }

    if ($successMessage) {
        header("Location: /machines?success=" . urlencode($successMessage));
        exit;
    }
}

// Загрузка данных
$editMachine = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM machines WHERE id = ?");
    $stmt->execute([$editId]);
    $editMachine = $stmt->fetch(PDO::FETCH_ASSOC);
}

$stmt = $pdo->query("SELECT * FROM machines ORDER BY type_machine, number_machine");
$machines = $stmt->fetchAll();
?>

<?php require_once __DIR__ . '/templates/header.php'; ?>
<?php require_once __DIR__ . '/templates/navbar.php'; ?>

<div style="flex: 1; padding: 20px;">

    <?php if (isset($_GET['success'])): ?>
        <div id="success-toast" class="toast-notification">
            <div class="toast-content">✅ <?= htmlspecialchars($_GET['success']) ?></div>
            <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>🛠️ Техника</h1>
        <span style="color: #4caf50; font-weight: 600;">
            👤 <?= htmlspecialchars($_SESSION['username']) ?> <small>(<?= $roleName ?>)</small>
        </span>
    </div>

    <!-- Форма добавления / редактирования -->
    <div style="background: #1f1f1f; padding: 25px; border-radius: 12px; margin-bottom: 30px;">
        <h3><?= $editMachine ? 'Редактировать машину' : 'Добавить новую машину' ?></h3>
        <form method="POST" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
            <?php if ($editMachine): ?>
                <input type="hidden" name="id" value="<?= $editMachine['id'] ?>">
                <input type="hidden" name="edit_machine" value="1">
            <?php else: ?>
                <input type="hidden" name="add_machine" value="1">
            <?php endif; ?>

            <label style="flex:1;">Тип машины<br>
                <select name="type_machine" required style="width:100%;padding:12px;border-radius:8px;background:#2a2a2a;color:#fff;border:none;">
                    <option value="Стиральная" <?= ($editMachine && $editMachine['type_machine']==='Стиральная')?'selected':'' ?>>Стиральная</option>
                    <option value="Сушильная"  <?= ($editMachine && $editMachine['type_machine']==='Сушильная') ?'selected':'' ?>>Сушильная</option>
                </select>
            </label>

            <label style="flex:1;">Номер / Название<br>
                <input type="text" name="number_machine" value="<?= htmlspecialchars($editMachine['number_machine'] ?? '') ?>" 
                       placeholder="Например: #5 или 3 этаж" required
                       style="width:100%;padding:12px;border-radius:8px;background:#2a2a2a;color:#fff;border:none;">
            </label>

            <label style="flex:1;">Статус<br>
                <select name="status" required style="width:100%;padding:12px;border-radius:8px;background:#2a2a2a;color:#fff;border:none;">
                    <option value="1" <?= ($editMachine && $editMachine['status']==1)?'selected':'' ?>>Работает</option>
                    <option value="0" <?= ($editMachine && $editMachine['status']==0)?'selected':'' ?>>Отключена</option>
                </select>
            </label>

            <button type="submit" class="btn btn-primary" style="padding:12px 30px;">
                <?= $editMachine ? 'Сохранить' : 'Добавить машину' ?>
            </button>

            <?php if ($editMachine): ?>
                <a href="/machines" class="btn" style="background:#666;color:#fff;padding:12px 20px;text-decoration:none;border-radius:8px;">Отмена</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ТАБЛИЦА -->
    <div style="max-height: 60vh; overflow-y: auto; border: 2px solid #333; border-radius: 12px; background: #1a1a1a;">
        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
            <thead style="position: sticky; top: 0; z-index: 10; background: #1f1f1f;">
                <tr>
                    <th style="padding:14px 12px; text-align:left; width:70px;">ID</th>
                    <th style="padding:14px 12px; text-align:left;">Тип машины</th>
                    <th style="padding:14px 12px; text-align:left;">Номер / Название</th>
                    <th style="padding:14px 12px; text-align:center; width:140px;">Статус</th>
                    <th style="padding:14px 12px; text-align:center; width:260px;">Действия</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($machines as $m): 
                    $isActive = $m['status'] == 1;
                ?>
                <tr>
                    <td style="padding:12px;"><?= $m['id'] ?></td>
                    <td style="padding:12px;"><?= htmlspecialchars($m['type_machine']) ?></td>
                    <td style="padding:12px;"><?= htmlspecialchars($m['number_machine']) ?></td>
                    
                    <!-- Красивый тоггл -->
                    <td style="padding:12px; text-align:center;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="toggle_id" value="<?= $m['id'] ?>">
                            <label class="switch">
                                <input type="checkbox" <?= $isActive ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span class="slider"></span>
                            </label>
                        </form>
                    </td>

                    <td style="padding:12px; text-align:center;">
                        <a href="/machines?edit=<?= $m['id'] ?>" class="btn" style="background:#1976d2;color:#fff;padding:6px 14px;font-size:14px;text-decoration:none;border-radius:6px;margin-right:6px;">Редактировать</a>
                        
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить машину?')">
                            <input type="hidden" name="delete_id" value="<?= $m['id'] ?>">
                            <button type="submit" class="btn btn-danger" style="padding:6px 14px;font-size:14px;">Удалить</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>