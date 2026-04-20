<?php
// views/stats.php
?>
<div style="flex: 1; padding: 20px;">

    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px;">
        <h1>📊 Статистика</h1>
        <span style="color:#4caf50; font-weight:600;">👤 <?= e($_SESSION['username']) ?> <small>(Администратор)</small></span>
    </div>

    <!-- Фильтр -->
    <form method="POST" style="background:#1f1f1f;padding:20px;border-radius:12px;margin-bottom:30px;display:flex;gap:15px;align-items:end;flex-wrap:wrap;">
        <label>Дата с: <input type="date" name="date_from" value="<?= e($from) ?>"></label>
        <label>Дата по: <input type="date" name="date_to" value="<?= e($to) ?>"></label>
        <button type="submit" class="btn btn-primary">Показать</button>
        <a href="/stats/export/xlsx?from=<?= e($from) ?>&to=<?= e($to) ?>" 
           class="btn btn-primary" style="background:#4caf50; margin-left:10px;">
            📊 Экспорт в Excel
        </a>
        <a href="/stats/export/docx?from=<?= e($from) ?>&to=<?= e($to) ?>" 
           class="btn btn-primary" style="background:#1976d2; margin-left:10px;">
            📄 Экспорт в Word
        </a>
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
                    <td><?= e($machine) ?></td>
                    <td style="font-weight:600;"><?= $cnt ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

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