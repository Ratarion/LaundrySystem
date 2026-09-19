<?php
// views/stats.php — Расширенная статистика и аналитика прачечной
?>
<div class="content-area">

    <div class="page-header">
        <h1 class="page-title"><i class="fa-solid fa-chart-pie"></i> Статистика и аналитика</h1>
        <div class="user-badge">
            <i class="fa-solid fa-user"></i> <span class="user-badge-name"><?= e($_SESSION['username']) ?></span> <small>(<?= e($roleName) ?>)</small>
        </div>
    </div>

    <!-- Фильтры отчёта -->
    <div class="glass-card">
        <form method="GET" action="/stats" class="form-grid">
            <div class="form-group" style="min-width: 170px;">
                <label class="form-label"><i class="fa-regular fa-calendar"></i> Дата с</label>
                <input type="date" name="date_from" value="<?= e($from) ?>" class="form-control">
            </div>
            
            <div class="form-group" style="min-width: 170px;">
                <label class="form-label"><i class="fa-regular fa-calendar-check"></i> Дата по</label>
                <input type="date" name="date_to" value="<?= e($to) ?>" class="form-control">
            </div>

            <div class="form-group" style="min-width: 220px;">
                <label class="form-label"><i class="fa-solid fa-building"></i> Общежитие</label>
                <?php if ($sessionDormId !== null): ?>
                    <input type="hidden" name="dormitory_id" value="<?= $sessionDormId ?>">
                    <div class="form-control" style="background: rgba(255,255,255,0.05); color: #0369a1; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-lock" style="font-size: 12px; opacity: 0.7;"></i>
                        <?= e($_SESSION['dormitory_name'] ?? ('Общежитие №' . $sessionDormId)) ?>
                    </div>
                <?php else: ?>
                    <select name="dormitory_id" class="form-control">
                        <option value="">Все общежития</option>
                        <?php foreach ($dormitories as $d): ?>
                            <option value="<?= $d->id ?>" <?= ($dormitory_id == $d->id) ? 'selected' : '' ?>>
                                <?= e($d->name ?: ('Общежитие №' . $d->number)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div style="display: flex; gap: 10px; align-items: flex-end;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 22px;">
                    <i class="fa-solid fa-filter"></i> Показать
                </button>
            </div>
            
            <div style="display: flex; gap: 10px; margin-left: auto; flex-wrap: wrap; align-items: flex-end;">
                <a href="/stats/export/xlsx?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&dormitory_id=<?= urlencode($dormitory_id) ?>" 
                   class="btn" style="border: 1px solid #10b981; color: #059669; background: #ecfdf5; font-weight: 600; padding: 10px 18px; display: inline-flex; align-items: center; gap: 8px;"
                   title="Выгрузить расширенный отчёт в Excel с графиками и статистикой по комнатам">
                    <i class="fa-solid fa-file-excel" style="font-size: 16px;"></i> Экспорт в Excel
                </a>
                <a href="/stats/export/docx?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&dormitory_id=<?= urlencode($dormitory_id) ?>" 
                   class="btn" style="border: 1px solid #3b82f6; color: #2563eb; background: #eff6ff; font-weight: 600; padding: 10px 18px; display: inline-flex; align-items: center; gap: 8px;"
                   title="Выгрузить отчёт в Word с ключевыми таблицами">
                    <i class="fa-solid fa-file-word" style="font-size: 16px;"></i> Экспорт в Word
                </a>
            </div>
        </form>
    </div>

    <!-- Карточки ключевых показателей -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
        <div class="stat-card">
            <h4 class="stat-card-title"><i class="fa-solid fa-calendar-check" style="color: var(--primary);"></i> Всего бронирований</h4>
            <h2 class="stat-card-value" style="color: var(--primary);"><?= $totalBookings ?></h2>
            <small style="color: var(--text-muted); font-size: 12px;">за выбранный период</small>
        </div>

        <div class="stat-card">
            <h4 class="stat-card-title"><i class="fa-solid fa-circle-check" style="color: var(--success);"></i> Активных / Состоявшихся</h4>
            <h2 class="stat-card-value" style="color: var(--success);"><?= $activeCount ?></h2>
            <small style="color: var(--success); font-weight: 600;"><?= $totalBookings ? round(($activeCount / $totalBookings) * 100) : 0 ?>% от общего числа</small>
        </div>

        <div class="stat-card">
            <h4 class="stat-card-title"><i class="fa-solid fa-circle-xmark" style="color: var(--danger);"></i> Отменено</h4>
            <h2 class="stat-card-value" style="color: var(--danger);"><?= $cancelledCount ?></h2>
            <small style="color: var(--danger); font-weight: 600;"><?= $cancelledPercent ?>% отмен</small>
        </div>

        <div class="stat-card">
            <h4 class="stat-card-title"><i class="fa-solid fa-door-closed" style="color: #0284c7;"></i> Активных комнат</h4>
            <h2 class="stat-card-value" style="color: #0284c7;"><?= $uniqueRoomsCount ?></h2>
            <small style="color: var(--text-muted); font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;" title="Самая активная: <?= e($topRoomName) ?> (<?= $topRoomCount ?>)">
                Топ: <?= e($topRoomName) ?> (<?= $topRoomCount ?>)
            </small>
        </div>
    </div>

    <!-- Графики -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
        <div class="glass-card" style="margin-bottom: 0; min-height: 420px; display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 class="card-title" style="margin-bottom: 0;"><i class="fa-solid fa-chart-column"></i> Загрузка по дням</h3>
                <span class="badge" style="background: #e0e7ff; color: #4338ca; font-weight: 600; padding: 4px 10px;">
                    <?= count($dailyLabels) ?> дней с активностью
                </span>
            </div>
            <div style="flex: 1; position: relative;">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>

        <div class="glass-card" style="margin-bottom: 0; min-height: 420px; display: flex; flex-direction: column;">
            <h3 class="card-title" style="margin-bottom: 16px;"><i class="fa-solid fa-chart-pie"></i> Оборудование и статусы</h3>
            <div style="flex: 1; position: relative; max-height: 280px; display: flex; align-items: center; justify-content: center;">
                <canvas id="typeChart"></canvas>
            </div>
            <div style="display: flex; justify-content: space-around; margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border); font-size: 13px;">
                <div>
                    <span style="display:inline-block; width:10px; height:10px; background:#6366f1; border-radius:50%; margin-right:4px;"></span>
                    Стиральные: <strong><?= $typeStats['washing'] ?></strong>
                </div>
                <div>
                    <span style="display:inline-block; width:10px; height:10px; background:#06b6d4; border-radius:50%; margin-right:4px;"></span>
                    Сушильные: <strong><?= $typeStats['drying'] ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Таблицы детальной аналитики: По комнатам и По оборудованию -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        
        <!-- СТАТИСТИКА ПО КОМНАТАМ -->
        <div class="glass-card" style="margin-bottom: 0; display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 class="card-title" style="margin-bottom: 0;">
                    <i class="fa-solid fa-door-open"></i> Статистика по комнатам
                </h3>
                <span class="badge badge-info" style="font-weight: 600;">
                    Комнат: <?= count($roomStats) ?>
                </span>
            </div>

            <div class="table-container" style="box-shadow: none; margin-bottom: 0; border-radius: 8px;">
                <div class="table-scroll" style="max-height: 440px;">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th style="width: 50px; text-align: center;">№</th>
                                <th>Комната</th>
                                <?php if (empty($dormitory_id)): ?>
                                    <th>Общежитие</th>
                                <?php endif; ?>
                                <th style="text-align: center; width: 85px;">Всего</th>
                                <th style="text-align: center; width: 85px;">Активных</th>
                                <th style="text-align: center; width: 85px;">Отмен</th>
                                <th style="width: 110px; text-align: center;">Доля</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($roomStats)): ?>
                                <tr>
                                    <td colspan="<?= empty($dormitory_id) ? 7 : 6 ?>" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                        Нет записей за выбранный период
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $rIdx = 1;
                                foreach ($roomStats as $rItem): 
                                    $pct = $totalBookings ? round(($rItem['total'] / $totalBookings) * 100, 1) : 0;
                                ?>
                                <tr>
                                    <td style="text-align: center; color: var(--text-muted); font-size: 12px;"><?= $rIdx++ ?></td>
                                    <td style="font-weight: 700; white-space: nowrap;">
                                        <span class="badge" style="background: #f1f5f9; color: #1e293b; border: 1px solid #e2e8f0; font-size: 13px; font-weight: 700;">
                                            <i class="fa-solid fa-door-closed" style="color: #64748b; font-size: 11px;"></i> <?= e($rItem['room']) ?>
                                        </span>
                                    </td>
                                    <?php if (empty($dormitory_id)): ?>
                                        <td style="font-size: 12px; color: #0369a1; white-space: nowrap;"><?= e($rItem['dormitory']) ?></td>
                                    <?php endif; ?>
                                    <td style="text-align: center; font-weight: 700; color: var(--primary); font-size: 14px;"><?= $rItem['total'] ?></td>
                                    <td style="text-align: center; font-weight: 600; color: var(--success); font-size: 13px;"><?= $rItem['active'] ?></td>
                                    <td style="text-align: center; font-weight: 600; color: var(--danger); font-size: 13px;"><?= $rItem['cancelled'] ?></td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <div style="flex: 1; background: #e2e8f0; height: 6px; border-radius: 999px; overflow: hidden;">
                                                <div style="width: <?= min(100, $pct * 3) ?>%; background: #6366f1; height: 100%; border-radius: 999px;"></div>
                                            </div>
                                            <span style="font-size: 11px; font-weight: 600; color: #64748b; min-width: 32px; text-align: right;"><?= $pct ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ЗАГРУЗКА ОБОРУДОВАНИЯ -->
        <div class="glass-card" style="margin-bottom: 0; display: flex; flex-direction: column;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 class="card-title" style="margin-bottom: 0;">
                    <i class="fa-solid fa-soap"></i> Загрузка оборудования
                </h3>
                <span class="badge badge-info" style="font-weight: 600;">
                    Единиц: <?= count($machineStats) ?>
                </span>
            </div>

            <div class="table-container" style="box-shadow: none; margin-bottom: 0; border-radius: 8px;">
                <div class="table-scroll" style="max-height: 440px;">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th style="width: 50px; text-align: center;">№</th>
                                <th>Оборудование</th>
                                <th style="width: 110px; text-align: center;">Тип</th>
                                <?php if (empty($dormitory_id)): ?>
                                    <th>Общежитие</th>
                                <?php endif; ?>
                                <th style="text-align: center; width: 90px;">Стирок</th>
                                <th style="width: 110px; text-align: center;">Доля</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($machineStats)): ?>
                                <tr>
                                    <td colspan="<?= empty($dormitory_id) ? 6 : 5 ?>" style="text-align: center; padding: 24px; color: var(--text-muted);">
                                        Нет записей за выбранный период
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $mIdx = 1;
                                foreach ($machineStats as $mItem): 
                                    $mPct = $totalBookings ? round(($mItem['total'] / $totalBookings) * 100, 1) : 0;
                                    $isDryer = str_contains(mb_strtolower($mItem['type']), 'сушил');
                                ?>
                                <tr>
                                    <td style="text-align: center; color: var(--text-muted); font-size: 12px;"><?= $mIdx++ ?></td>
                                    <td style="font-weight: 700;"><?= e($mItem['name']) ?></td>
                                    <td style="text-align: center;">
                                        <?php if ($isDryer): ?>
                                            <span class="badge" style="background: #cffafe; color: #0e7490; border: 1px solid #a5f3fc; font-size: 11px;">
                                                Сушилка
                                            </span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; font-size: 11px;">
                                                Стиралка
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if (empty($dormitory_id)): ?>
                                        <td style="font-size: 12px; color: #0369a1; white-space: nowrap;"><?= e($mItem['dormitory']) ?></td>
                                    <?php endif; ?>
                                    <td style="text-align: center; font-weight: 700; color: var(--primary); font-size: 14px;"><?= $mItem['total'] ?></td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <div style="flex: 1; background: #e2e8f0; height: 6px; border-radius: 999px; overflow: hidden;">
                                                <div style="width: <?= min(100, $mPct * 3) ?>%; background: <?= $isDryer ? '#06b6d4' : '#6366f1' ?>; height: 100%; border-radius: 999px;"></div>
                                            </div>
                                            <span style="font-size: 11px; font-weight: 600; color: #64748b; min-width: 32px; text-align: right;"><?= $mPct ?>%</span>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // 1. График загрузки по дням
    const dailyCtx = document.getElementById('dailyChart');
    if (dailyCtx) {
        new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($dailyLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                datasets: [{
                    label: 'Бронирований',
                    data: <?= json_encode($dailyCounts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                    backgroundColor: 'rgba(99, 102, 241, 0.8)',
                    borderColor: '#6366f1',
                    borderWidth: 1,
                    borderRadius: 6,
                    hoverBackgroundColor: '#4f46e5'
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: { 
                    y: { 
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            precision: 0,
                            font: { family: "'Inter', sans-serif" }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: { family: "'Inter', sans-serif" }
                        }
                    }
                } 
            }
        });
    }

    // 2. Диаграмма распределения типов оборудования
    const typeCtx = document.getElementById('typeChart');
    if (typeCtx) {
        new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: ['Стиральные машины', 'Сушильные машины'],
                datasets: [{
                    data: [<?= (int)$typeStats['washing'] ?>, <?= (int)$typeStats['drying'] ?>],
                    backgroundColor: ['#6366f1', '#06b6d4'],
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { family: "'Inter', sans-serif" },
                            padding: 14
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }
</script>