<?php
// notifications.php — Уведомления жителям
session_start();
$root = dirname(__DIR__);
require_once $root . '/config/logger.php';

require_once $root . '/config/db_connect.php';
if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
    die('Критическая ошибка подключения к базе.');
}

use Models\Notification;

$pdo = $GLOBALS['pdo'];

$log->info('Открыта страница Уведомления', ['ip' => $_SERVER['REMOTE_ADDR']]);

if (!isset($_SESSION['admin_id'])) {
    header('Location: /booking');
    exit;
}

$roleName = ($_SESSION['role'] ?? 0) === 1 ? 'Администратор' : 'Техник';

// ==================== ОТПРАВКА УВЕДОМЛЕНИЯ ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    $notification = new Notification($pdo);
    $notification->id_residents = (int)$_POST['resident_id'];
    $notification->description  = trim($_POST['description']);
    $notification->save();

    $log->info('Отправлено уведомление', ['resident_id' => $notification->id_residents, 'role' => $roleName]);

    header("Location: /notifications?success=Уведомление успешно отправлено!");
    exit;
}

// ==================== ЗАГРУЗКА ДАННЫХ ====================
$notifications = Notification::getAll($pdo);

// Все жители для формы (используем статический метод из модели)
$residents = Notification::getAllResidents($pdo);
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
        <h1>🛎️ Уведомления</h1>
        <span style="color: #4caf50; font-weight: 600;">
            👤 <?= htmlspecialchars($_SESSION['username']) ?> <small>(<?= $roleName ?>)</small>
        </span>
    </div>

    <!-- Форма отправки -->
    <div style="background: #1f1f1f; padding: 25px; border-radius: 12px; margin-bottom: 30px;">
        <h3>Отправить новое уведомление</h3>
        <form method="POST" style="display:flex; gap:15px; align-items:end; flex-wrap:wrap;">
            <input type="hidden" name="send_notification" value="1">

            <label style="flex:1;">
                Житель<br>
                <select name="resident_id" required style="width:100%; padding:12px; border-radius:8px; background:#2a2a2a; color:#fff; border:none;">
                    <option value="">Выберите жителя...</option>
                    <?php foreach ($residents as $r): ?>
                    <option value="<?= $r['id'] ?>">
                        <?= htmlspecialchars($r['last_name'] . ' ' . $r['first_name']) ?> (комн. <?= $r['inidroom'] ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label style="flex:2;">
                Текст уведомления<br>
                <input type="text" name="description" placeholder="Например: Стиральная машина не включается" required
                       style="width:100%; padding:12px; border-radius:8px; background:#2a2a2a; color:#fff; border:none;">
            </label>

            <button type="submit" class="btn btn-primary" style="padding:12px 32px;">
                Отправить
            </button>
        </form>
    </div>

    <!-- ТАБЛИЦА УВЕДОМЛЕНИЙ -->
    <div style="max-height: 60vh; overflow-y: auto; border: 2px solid #333; border-radius: 12px; background: #1a1a1a;">
        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
            <thead style="position: sticky; top: 0; z-index: 10; background: #1f1f1f;">
                <tr>
                    <th style="padding:14px 12px; text-align:left;">Дата</th>
                    <th style="padding:14px 12px; text-align:left;">Житель</th>
                    <th style="padding:14px 12px; text-align:left;">Сообщение</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $n): ?>
                <tr>
                    <td style="padding:12px;"><?= $n->create_date ?></td>
                    <td style="padding:12px;"><?= htmlspecialchars($n->resident_name) ?> (<?= $n->inidroom ?>)</td>
                    <td style="padding:12px;"><?= htmlspecialchars($n->description) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once $root . '/templates/footer.php'; ?>