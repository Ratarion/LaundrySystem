<?php
// stats.php — Статистика (ТОЛЬКО для Администратора)
session_start();
require_once __DIR__ . '/logger.php';

require_once __DIR__ . '/db_connect.php';
if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
    die('Критическая ошибка подключения к базе.');
}
$pdo = $GLOBALS['pdo'];

if (($_SESSION['role'] ?? 0) !== 1) {
    header('Location: /booking');
    exit;
}

$log->info('Открыта страница Статистика (Админ)', ['ip' => $_SERVER['REMOTE_ADDR']]);

$from = $_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$to   = $_POST['date_to']   ?? date('Y-m-d');

// ==================== РЕАЛЬНАЯ СТАТИСТИКА ====================

// 1. Общие цифры
$total = $pdo->prepare("SELECT COUNT(*) FROM booking WHERE DATE(start_time) BETWEEN ? AND ?");
$total->execute([$from, $to]);
$totalBookings = $total->fetchColumn();

$cancelled = $pdo->prepare("SELECT COUNT(*) FROM booking WHERE DATE(start_time) BETWEEN ? AND ? AND status = 'cancelled'");
$cancelled->execute([$from, $to]);
$cancelledCount = $cancelled->fetchColumn();

$active = $pdo->prepare("SELECT COUNT(*) FROM booking WHERE DATE(start_time) BETWEEN ? AND ? AND status != 'cancelled'");
$active->execute([$from, $to]);
$activeCount = $active->fetchColumn();

// 2. По типу машин
$byTypeStmt = $pdo->prepare("
    SELECT m.type_machine, COUNT(*) as cnt 
    FROM booking b 
    JOIN machines m ON b.inidmachine = m.id 
    WHERE DATE(b.start_time) BETWEEN ? AND ?
    GROUP BY m.type_machine
");
$byTypeStmt->execute([$from, $to]);
$byType = $byTypeStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. График по дням
$dailyStmt = $pdo->prepare("
    SELECT DATE(start_time) as day, COUNT(*) as cnt 
    FROM booking 
    WHERE DATE(start_time) BETWEEN ? AND ?
    GROUP BY day 
    ORDER BY day
");
$dailyStmt->execute([$from, $to]);
$dailyData = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Топ-5 машин
$topStmt = $pdo->prepare("
    SELECT m.type_machine, m.number_machine, COUNT(*) as cnt 
    FROM booking b 
    JOIN machines m ON b.inidmachine = m.id 
    WHERE DATE(b.start_time) BETWEEN ? AND ?
    GROUP BY m.id, m.type_machine, m.number_machine 
    ORDER BY cnt DESC LIMIT 5
");
$topStmt->execute([$from, $to]);
$topMachines = $topStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once __DIR__ . '/templates/header.php'; ?>
<?php require_once __DIR__ . '/templates/navbar.php'; ?>

<div style="flex: 1; padding: 20px;">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
        <h1>📊 Статистика</h1>
        <span style="color:#4caf50; font-weight:600;">👤 <?= htmlspecialchars($_SESSION['username']) ?> <small>(Администратор)</small></span>
    </div>

    <!-- Фильтр -->
    <form method="POST" style="background:#1f1f1f;padding:20px;border-radius:12px;margin-bottom:30px;display:flex;gap:15px;align-items:end;flex-wrap:wrap;">
        <label>Дата с: <input type="date" name="date_from" value="<?= $from ?>"></label>
        <label>Дата по: <input type="date" name="date_to" value="<?= $to ?>"></label>
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
                <?php foreach ($topMachines as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['type_machine']) ?> #<?= htmlspecialchars($m['number_machine']) ?></td>
                    <td style="font-weight:600;"><?= $m['cnt'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/templates/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    new Chart(document.getElementById('dailyChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($dailyData, 'day')) ?>,
            datasets: [{
                label: 'Бронирований',
                data: <?= json_encode(array_column($dailyData, 'cnt')) ?>,
                backgroundColor: '#00bcd4'
            }]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
</script>