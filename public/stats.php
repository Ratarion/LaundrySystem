<?php
// stats.php — Статистика (ТОЛЬКО для Администратора)
session_start();
$root = dirname(__DIR__);
require_once $root . '/config/logger.php';

require_once $root . '/config/db_connect.php';
if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
    die('Критическая ошибка подключения к базе.');
}

use Models\Booking;

$pdo = $GLOBALS['pdo'];

if (($_SESSION['role'] ?? 0) !== 1) {
    header('Location: /booking');
    exit;
}

$log->info('Открыта страница Статистика (Админ)', ['ip' => $_SERVER['REMOTE_ADDR']]);

$from = $_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_POST['date_to']   ?? date('Y-m-d');

// Получаем все бронирования за период через модель
$bookingsData = Booking::getAll($pdo, $from, $to);

// ==================== РЕАЛЬНАЯ СТАТИСТИКА ====================

$totalBookings = count($bookingsData);

$cancelledCount = 0;
$activeCount    = 0;
$byType         = [];
$dailyData      = [];
$topMachines    = [];

foreach ($bookingsData as $b) {
    $status = $b['status'];
    if (in_array($status, ['cancelled', 'Отменено'])) {
        $cancelledCount++;
    } else {
        $activeCount++;
    }

    // По типу машин
    $type = $b['type_machine'];
    $byType[$type] = ($byType[$type] ?? 0) + 1;

    // По дням
    $day = substr($b['start_time'], 0, 10); // YYYY-MM-DD
    $dailyData[$day] = ($dailyData[$day] ?? 0) + 1;

    // Топ-5 машин
    $machineKey = $b['type_machine'] . ' #' . $b['number_machine'];
    $topMachines[$machineKey] = ($topMachines[$machineKey] ?? 0) + 1;
}

// Сортируем топ-5
arsort($topMachines);
$topMachines = array_slice($topMachines, 0, 5, true);

// Преобразуем для графика
$dailyLabels = array_keys($dailyData);
$dailyCounts = array_values($dailyData);
?>

<?php require_once $root . '/templates/header.php'; ?>
<?php require_once $root . '/templates/navbar.php'; ?>

<div style="flex: 1; padding: 20px;">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
        <h1>📊 Статистика</h1>
        <span style="color:#4caf50; font-weight:600;">👤 <?= htmlspecialchars($_SESSION['username']) ?> <small>(Администратор)</small></span>
    </div>

    <!-- Фильтр -->
    <form method="POST" style="background:#1f1f1f;padding:20px;border-radius:12px;margin-bottom:30px;display:flex;gap:15px;align-items:end;flex-wrap:wrap;">
        <label>Дата с: <input type="date" name="date_from" value="<?= htmlspecialchars($from) ?>"></label>
        <label>Дата по: <input type="date" name="date_to" value="<?= htmlspecialchars($to) ?>"></label>
        <button type="submit" class="btn btn-primary">Показать</button>
    </form>

    <!-- Карточки -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-bottom:30px;">
        <div style="background:#1f1f1f;padding:25px;border-radius:12px;text-align:center;">
            <h4>Всего бронирований</h4>
            <h2 style="font-size:52px;color:#4caf50;margin:10px 0;"><?= $totalBookings ?></h2>
        </div>
        <div style="background:#1f1f1f;padding:25px;border-radius:12px;text-align:center;">
            <h4>Активных</h4>
            <h2 style="font-size:52px;color:#00bcd4;margin:10px 0;"><?= $activeCount ?></h2>
        </div>
        <div style="background:#1f1f1f;padding:25px;border-radius:12px;text-align:center;">
            <h4>Отменено</h4>
            <h2 style="font-size:52px;color:#f44336;margin:10px 0;"><?= $cancelledCount ?></h2>
            <small style="color:#f44336;">(<?= $totalBookings ? round($cancelledCount/$totalBookings*100) : 0 ?>%)</small>
        </div>
    </div>

    <!-- График -->
    <div style="background:#1f1f1f;padding:25px;border-radius:12px;margin-bottom:30px;">
        <h3>Загрузка по дням</h3>
        <canvas id="dailyChart" style="max-height:420px;"></canvas>
    </div>

    <!-- Топ машин -->
    <div style="background:#1f1f1f;padding:25px;border-radius:12px;">
        <h3>Топ-5 самых загруженных машин</h3>
        <table style="width:100%;margin-top:15px;">
            <thead><tr><th>Машина</th><th>Бронирований</th></tr></thead>
            <tbody>
                <?php foreach ($topMachines as $machine => $cnt): ?>
                <tr>
                    <td><?= htmlspecialchars($machine) ?></td>
                    <td style="font-weight:600;"><?= $cnt ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once $root . '/templates/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    new Chart(document.getElementById('dailyChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($dailyLabels) ?>,
            datasets: [{
                label: 'Бронирований',
                data: <?= json_encode($dailyCounts) ?>,
                backgroundColor: '#00bcd4'
            }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
</script>