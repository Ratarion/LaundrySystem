<?php
// views/stats.php — Редизайн страницы статистики и графиков
?>
<div class="content-area">

    <div class="page-header">
        <h1 class="page-title"><i class="fa-solid fa-chart-simple"></i> Статистика</h1>
        <div class="user-badge">
            <i class="fa-solid fa-user"></i> <span class="user-badge-name"><?= e($_SESSION['username']) ?></span> <small>(Администратор)</small>
        </div>
    </div>

    <!-- Фильтр -->
    <div class="glass-card">
        <form method="POST" class="form-grid">
            <div class="form-group">
                <label class="form-label">Дата с</label>
                <input type="date" name="date_from" value="<?= e($from) ?>" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Дата по</label>
                <input type="date" name="date_to" value="<?= e($to) ?>" class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Показать</button>
            
            <div style="display: flex; gap: 10px; margin-left: auto; flex-wrap: wrap;">
                <a href="/stats/export/xlsx?from=<?= e($from) ?>&to=<?= e($to) ?>" 
                   class="btn btn-secondary" style="border: 1px solid var(--success); color: var(--success); background: transparent;">
                    <i class="fa-solid fa-file-excel"></i> Экспорт в Excel
                </a>
                <a href="/stats/export/docx?from=<?= e($from) ?>&to=<?= e($to) ?>" 
                   class="btn btn-secondary" style="border: 1px solid var(--primary); color: var(--primary); background: transparent;">
                    <i class="fa-solid fa-file-word"></i> Экспорт в Word
                </a>
            </div>
        </form>
    </div>

    <!-- Карточки -->
    <div class="stats-grid">
        <div class="stat-card">
            <h4 class="stat-card-title">Всего бронирований</h4>
            <h2 class="stat-card-value" style="color: var(--primary);"><?= $totalBookings ?></h2>
        </div>
        <div class="stat-card">
            <h4 class="stat-card-title">Активных</h4>
            <h2 class="stat-card-value" style="color: var(--success);"><?= $activeCount ?></h2>
        </div>
        <div class="stat-card">
            <h4 class="stat-card-title">Отменено</h4>
            <h2 class="stat-card-value" style="color: var(--danger);"><?= $cancelledCount ?></h2>
            <small style="color: var(--danger); font-weight: 600;">(<?= $totalBookings ? round($cancelledCount/$totalBookings*100) : 0 ?>%)</small>
        </div>
    </div>

    <!-- График -->
    <div class="glass-card" style="min-height: 480px; display: flex; flex-direction: column;">
        <h3 class="card-title">Загрузка по дням</h3>
        <div style="flex: 1; position: relative;">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>

    <!-- Топ машин -->
    <div class="glass-card">
        <h3 class="card-title">Топ-5 самых загруженных машин</h3>
        <div class="table-container" style="margin-top: 15px; margin-bottom: 0;">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Машина</th>
                        <th style="width: 200px; text-align: center;">Бронирований</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topMachines as $machine => $cnt): ?>
                    <tr>
                        <td><?= e($machine) ?></td>
                        <td style="font-weight: 700; text-align: center; color: var(--primary);"><?= $cnt ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    new Chart(document.getElementById('dailyChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($dailyLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
            datasets: [{
                label: 'Бронирований',
                data: <?= json_encode($dailyCounts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                backgroundColor: 'rgba(99, 102, 241, 0.75)',
                borderColor: '#6366f1',
                borderWidth: 1,
                borderRadius: 6,
                hoverBackgroundColor: '#6366f1'
            }]
        },
        options: { 
            responsive: true, 
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: '#9ca3af',
                        font: {
                            family: "'Inter', sans-serif"
                        }
                    }
                }
            },
            scales: { 
                y: { 
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)'
                    },
                    ticks: {
                        color: '#9ca3af',
                        font: {
                            family: "'Inter', sans-serif"
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.05)'
                    },
                    ticks: {
                        color: '#9ca3af',
                        font: {
                            family: "'Inter', sans-serif"
                        }
                    }
                }
            } 
        }
    });
</script>