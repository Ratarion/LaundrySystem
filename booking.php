<?php
// booking.php — главная страница бронирований (GET/POST /booking)
session_start();
require_once __DIR__ . '/logger.php';

// === ЗАЩИТА ОШИБКИ PDO ===
$pdo = require_once __DIR__ . '/db_connect.php';
if (!($pdo instanceof PDO)) {
    $log->critical('db_connect.php не вернул PDO объект!');
    die('Критическая ошибка подключения к базе. Обратитесь к разработчику.');
}

$log->info('Открыта главная страница (booking)', ['ip' => $_SERVER['REMOTE_ADDR']]);

$isAdmin     = isset($_SESSION['admin_id']) && $_SESSION['role'] === 1;
$isLoggedIn  = isset($_SESSION['admin_id']);

// ==================== ДЕФОЛТНЫЙ ФИЛЬТР НА СЕГОДНЯ ====================
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_POST['date_from'] = date('Y-m-d');
    $_POST['date_to']   = date('Y-m-d');
}

// ==================== ОБРАБОТКА ДЕЙСТВИЙ (POST) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Массовая отмена (только админ)
    if ($isAdmin && isset($_POST['mass_cancel'])) {
        $date = $_POST['cancel_date'];
        $type = $_POST['type_machine'];

        $stmt = $pdo->prepare("
            UPDATE booking b
            SET status = 'cancelled'
            FROM machines m
            WHERE b.inidmachine = m.id
                AND DATE(b.start_time) = ?
                AND m.type_machine = ?
        ");
        $stmt->execute([$date, $type]);

        $affected = $stmt->rowCount();
        $log->info('Массовая отмена выполнена', ['date' => $date, 'type' => $type, 'affected' => $affected]);
        header('Location: /booking?success=Массовая отмена выполнена!');
        exit;
    }

    // Отмена одной записи
    if ($isAdmin && isset($_POST['cancel_id'])) {
        $id = (int)$_POST['cancel_id'];
        $stmt = $pdo->prepare("UPDATE booking SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$id]);
        $log->info('Отменена запись', ['booking_id' => $id]);
        header('Location: /booking?success=Запись отменена');
        exit;
    }
}

// ==================== ЗАПРОС С ФИЛЬТРАМИ ====================
$where = ["DATE(b.start_time) BETWEEN ? AND ?"];
$params = [$_POST['date_from'] ?? date('Y-m-d'), $_POST['date_to'] ?? date('Y-m-d')];

if (!empty($_POST['status'])) {
    $where[] = "b.status = ?";
    $params[] = $_POST['status'];
}

$sql = "
    SELECT b.id, b.start_time, b.end_time, b.status,
           r.last_name, r.first_name, r.inidroom,
           m.type_machine, m.number_machine
    FROM booking b
    JOIN residents r ON b.inidresidents = r.id
    JOIN machines m ON b.inidmachine = m.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY b.start_time DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();
?>

<?php require_once __DIR__ . '/templates/header.php'; ?>

<!-- ←←← ВОТ ИСПРАВЛЕНИЕ: navbar только для админов →→→ -->
<?php if ($isLoggedIn): ?>
    <?php require_once __DIR__ . '/templates/navbar.php'; ?>
<?php endif; ?>

<div style="flex: 1; padding: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>📅 Бронирования на сегодня (<?= date('d.m.Y') ?>)</h1>
        
        <?php if (!$isLoggedIn): ?>
            <a href="/login.php" class="btn btn-primary" style="font-size: 18px; padding: 12px 30px;">
                🔑 Вход в админ-панель
            </a>
        <?php else: ?>
            <span style="color: #4caf50;">👤 <?= htmlspecialchars($_SESSION['username'] ?? 'Админ') ?> (админ)</span>
        <?php endif; ?>
    </div>

    <!-- ФОРМА ФИЛЬТРОВ -->
    <form method="POST" class="filter-form">
        <label>Статус: 
            <select name="status">
                <option value="">Все</option>
                <option value="Ожидание">Ожидание</option>
                <option value="Отмена">Отмена</option>
                <option value="Подверженная">Подверженная</option>
                <option value="cancelled">Отменено</option>
            </select>
        </label>
        <label>Дата с: <input type="date" name="date_from" value="<?= $_POST['date_from'] ?? '' ?>"></label>
        <label>Дата по: <input type="date" name="date_to" value="<?= $_POST['date_to'] ?? '' ?>"></label>
        <button type="submit" class="btn btn-primary">Применить фильтры</button>
    </form>

    <!-- ТАБЛИЦА -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Житель</th>
                <th>Комната</th>
                <th>Машина</th>
                <th>Начало</th>
                <th>Конец</th>
                <th>Статус</th>
                <?php if ($isAdmin): ?><th>Действие</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($bookings as $b): ?>
            <tr>
                <td><?= $b['id'] ?></td>
                <td><?= htmlspecialchars($b['last_name'] . ' ' . $b['first_name']) ?></td>
                <td><?= $b['inidroom'] ?></td>
                <td><?= htmlspecialchars($b['type_machine']) ?> #<?= $b['number_machine'] ?></td>
                <td><?= $b['start_time'] ?></td>
                <td><?= $b['end_time'] ?></td>
                <td><?= htmlspecialchars($b['status']) ?></td>
                <?php if ($isAdmin): ?>
                <td>
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

    <?php if (empty($bookings)): ?>
        <p style="text-align:center; padding:40px;">Сегодня записей нет ✅</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>