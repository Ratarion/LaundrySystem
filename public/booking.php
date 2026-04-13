<?php
// booking.php — главная страница (3 роли)
session_start();
$root = dirname(__DIR__);
require_once $root . '/config/logger.php';

require_once $root . '/config/db_connect.php';
if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
    $log->critical('PDO объект не найден в $GLOBALS после подключения');
    die('Критическая ошибка подключения к базе. Смотри logs/error.log');
}

use Models\Booking;

$pdo = $GLOBALS['pdo'];

$log->info('Открыта главная страница', ['ip' => $_SERVER['REMOTE_ADDR']]);

// ====================== ОПРЕДЕЛЕНИЕ РОЛИ ======================
$role = $_SESSION['role'] ?? 0;
$isLoggedIn   = isset($_SESSION['admin_id']);
$isAdmin      = $role === 1;
$isTechnician = $role === 2;

$roleName = $role === 1 ? 'Администратор' : ($role === 2 ? 'Техник' : 'Житель');

// ====================== ДЕФОЛТНЫЙ ФИЛЬТР ======================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_POST['date_from'] = date('Y-m-d');
    $_POST['date_to']   = date('Y-m-d');
}

// ====================== ОБРАБОТКА ДЕЙСТВИЙ ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isLoggedIn) {

    // Массовая отмена (техник + админ)
    if (isset($_POST['mass_cancel'])) {
        $date = $_POST['cancel_date'];
        $type = $_POST['type_machine'];

        $result = Booking::massCancel($pdo, $date, $type);

        if ($result) {
            $log->info('Массовая отмена', ['date' => $date, 'type' => $type, 'role' => $roleName]);
            header('Location: /booking?success=Массовая отмена выполнена!');
            exit;
        } else {
            $log->error('Ошибка массовой отмены');
            die('Ошибка при массовой отмене. Смотри логи.');
        }
    }

    // Отмена одной записи — только админ
    if ($isAdmin && isset($_POST['cancel_id'])) {
        $id = (int)$_POST['cancel_id'];
        $result = Booking::cancelOne($pdo, $id);

        if ($result) {
            $log->info('Отменена запись', ['booking_id' => $id, 'role' => $roleName]);
            header('Location: /booking?success=Запись отменена');
            exit;
        } else {
            $log->error('Ошибка отмены записи');
        }
    }
}

// ====================== ЗАПРОС ЗАПИСЕЙ ======================
$date_from = $_POST['date_from'] ?? date('Y-m-d');
$date_to   = $_POST['date_to']   ?? date('Y-m-d');
$status    = $_POST['status'] ?? '';

$bookings = Booking::getAll($pdo, $date_from, $date_to, $status);
?>
<!--=== ВСЁ, что было после логики бронирований === -->
<?php 
$root = dirname(__DIR__); 

require_once $root . '/templates/header.php'; 

if ($isLoggedIn) {
    require_once $root . '/templates/navbar.php';
}
?>

<div style="flex: 1; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>📅 Бронирования (<?= $roleName ?>)</h1>
        
        <?php if (!$isLoggedIn): ?>
            <a href="/login" class="btn btn-primary" style="font-size: 18px; padding: 12px 30px;">
                🔑 Вход в админ-панель
            </a>
        <?php else: ?>
            <span style="color: #4caf50; font-weight: 600;">
                👤 <?= htmlspecialchars($_SESSION['username']) ?> 
                <small>(<?= $roleName ?>)</small>
            </span>
        <?php endif; ?>
    </div>

    <!-- ФОРМА ФИЛЬТРОВ -->
    <form method="POST" style="background:#1f1f1f; padding:25px; border-radius:12px; margin-bottom:30px; display:flex; gap:15px; align-items:end; flex-wrap:wrap;">
        <label style="flex:1;">
            Статус<br>
            <select name="status" style="width:100%; padding:12px; border-radius:8px; background:#2a2a2a; color:#fff; border:none;">
                <option value="">Все</option>
                <option value="Ожидание" <?= $status==='Ожидание'?'selected':'' ?>>Ожидание</option>
                <option value="Подверженная" <?= $status==='Подверженная'?'selected':'' ?>>Подтверждено</option>
                <option value="Отмена" <?= $status==='Отмена'?'selected':'' ?>>Отмена</option>
                <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>Отменено</option>
            </select>
        </label>
            
        <label style="flex:1;">
            Дата с<br>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" 
                   style="width:100%; padding:12px; border-radius:8px; background:#2a2a2a; color:#fff; border:none;">
        </label>
            
        <label style="flex:1;">
            Дата по<br>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" 
                   style="width:100%; padding:12px; border-radius:8px; background:#2a2a2a; color:#fff; border:none;">
        </label>
            
        <button type="submit" class="btn btn-primary" style="padding:12px 32px;">
            Применить фильтры
        </button>
    </form>

    <!-- МАССОВАЯ ОТМЕНА -->
    <?php if ($isLoggedIn): ?>
    <div style="background: #1f1f1f; padding: 25px; border-radius: 12px; margin-bottom: 30px;">
        <h3 style="margin-top:0; margin-bottom:20px;">🗑 Массовая отмена</h3>
        <form method="POST" style="display:flex; gap:15px; align-items:end; flex-wrap:wrap;">
            <label style="flex:1;">
                Дата<br>
                <input type="date" name="cancel_date" value="<?= date('Y-m-d') ?>" required
                       style="width:100%; padding:12px; border-radius:8px; background:#2a2a2a; color:#fff; border:none;">
            </label>

            <label style="flex:1;">
                Тип машины<br>
                <select name="type_machine" required
                        style="width:100%; padding:12px; border-radius:8px; background:#2a2a2a; color:#fff; border:none;">
                    <option value="Стиральная">Стиральная</option>
                    <option value="Сушильная">Сушильная</option>
                </select>
            </label>

            <button type="submit" name="mass_cancel" class="btn btn-warning" 
                    onclick="return confirm('Отменить ВСЕ записи на выбранную дату и тип машины?')"
                    style="padding:12px 32px; font-size:16px;">
                Отменить все
            </button>
        </form>
    </div>
    <?php endif; ?>

    <!-- ТАБЛИЦА -->
    <div style="max-height: 60vh; overflow-y: auto; border: 2px solid #333; border-radius: 12px; background: #1a1a1a;">
        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
            <thead style="position: sticky; top: 0; z-index: 10; background: #1f1f1f;">
                <tr>
                    <th style="padding:14px 12px; text-align:left;">ID</th>
                    <th style="padding:14px 12px; text-align:left;">Житель</th>
                    <th style="padding:14px 12px; text-align:left;">Комната</th>
                    <th style="padding:14px 12px; text-align:left;">Машина</th>
                    <th style="padding:14px 12px; text-align:left;">Начало</th>
                    <th style="padding:14px 12px; text-align:left;">Конец</th>
                    <th style="padding:14px 12px; text-align:center;">Статус</th>
                    <?php if ($isAdmin): ?>
                        <th style="padding:14px 12px; text-align:center; width:130px;">Действие</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): 
                    $status = $b['status'];
                    $statusColor = match($status) {
                        'Ожидание' => '#ff9800',
                        'Подверженная', 'Подтверждено' => '#4caf50',
                        'cancelled', 'Отменено' => '#f44336',
                        default => '#666'
                    };
                ?>
                <tr>
                    <td style="padding:12px;"><?= $b['id'] ?></td>
                    <td style="padding:12px;"><?= htmlspecialchars($b['last_name'] . ' ' . $b['first_name']) ?></td>
                    <td style="padding:12px;"><?= $b['inidroom'] ?></td>
                    <td style="padding:12px;"><?= htmlspecialchars($b['type_machine']) ?> #<?= $b['number_machine'] ?></td>
                    <td style="padding:12px;"><?= $b['start_time'] ?></td>
                    <td style="padding:12px;"><?= $b['end_time'] ?></td>
                    <td style="padding:12px; text-align:center;">
                        <span style="padding:4px 14px; border-radius:9999px; font-size:14px; background:<?= $statusColor ?>; color:#fff;">
                            <?= htmlspecialchars($status) ?>
                        </span>
                    </td>
                    <?php if ($isAdmin): ?>
                    <td style="padding:12px; text-align:center;">
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="cancel_id" value="<?= $b['id'] ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Отменить эту запись?')">Отменить</button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once $root . '/templates/footer.php'; ?>