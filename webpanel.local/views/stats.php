<?php
// views/stats.php — Расширенная статистика и аналитика прачечной

if (!function_exists('shortDormName')) {
    function shortDormName($name) {
        if (empty($name)) return '—';
        if (preg_match('/№\s*(\d+)/u', $name, $m)) {
            return '№' . $m[1];
        }
        if (preg_match('/(\d+)/u', $name, $m)) {
            return '№' . $m[1];
        }
        return mb_substr($name, 0, 8);
    }
}
?>
<style>
.stats-tables-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}
@media (min-width: 1024px) {
    .stats-tables-grid {
        grid-template-columns: 1fr 1fr;
    }
}
.stats-table-card {
    min-width: 0 !important;
    margin-bottom: 0 !important;
    padding: 18px 18px !important;
    display: flex;
    flex-direction: column;
}
.stats-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    table-layout: auto;
}
.stats-table th {
    background-color: #f8fafc;
    padding: 8px 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 2;
}
.stats-table th.sortable {
    cursor: pointer;
    user-select: none;
    transition: background-color 0.15s ease, color 0.15s ease;
}
.stats-table th.sortable:hover {
    background-color: #f1f5f9;
    color: var(--primary);
}
.stats-table th.sortable .th-content {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.stats-table th.sortable .sort-icon {
    font-size: 10px;
    opacity: 0.35;
    transition: opacity 0.15s ease, color 0.15s ease;
    display: inline-flex;
    align-items: center;
}
.stats-table th.sortable:hover .sort-icon {
    opacity: 0.85;
    color: var(--primary);
}
.stats-table th.sortable.sorted-asc,
.stats-table th.sortable.sorted-desc {
    background-color: #eff6ff;
    color: var(--primary);
}
.stats-table th.sortable.sorted-asc .sort-icon,
.stats-table th.sortable.sorted-desc .sort-icon {
    opacity: 1;
    color: var(--primary);
}
.stats-table td {
    padding: 7px 6px;
    font-size: 13px;
    border-bottom: 1px solid var(--border);
    color: var(--text-main);
    vertical-align: middle;
    white-space: nowrap;
}
.stats-table tbody tr:hover {
    background-color: #f8fafc;
}
.stats-table tbody tr:last-child td {
    border-bottom: none;
}
.stats-charts-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 16px;
    margin-bottom: 20px;
}
@media (max-width: 900px) {
    .stats-charts-grid {
        grid-template-columns: 1fr;
    }
}
.stats-chart-card {
    min-width: 0 !important;
    margin-bottom: 0 !important;
    padding: 20px !important;
    min-height: 380px;
    display: flex;
    flex-direction: column;
}
</style>

<div class="content-area">

    <div class="page-header">
        <h1 class="page-title"><i class="fa-solid fa-chart-pie"></i> Статистика и аналитика</h1>
        <div class="user-badge">
            <i class="fa-solid fa-user"></i> <span class="user-badge-name"><?= e($_SESSION['username']) ?></span> <small>(<?= e($roleName) ?>)</small>
        </div>
    </div>

    <!-- Фильтры отчёта -->
    <div class="glass-card" style="padding: 18px 20px;">
        <form method="GET" action="/stats" class="form-grid">
            <div class="form-group" style="min-width: 150px; flex: 1;">
                <label class="form-label"><i class="fa-regular fa-calendar"></i> Дата с</label>
                <input type="date" name="date_from" value="<?= e($from) ?>" class="form-control">
            </div>
            
            <div class="form-group" style="min-width: 150px; flex: 1;">
                <label class="form-label"><i class="fa-regular fa-calendar-check"></i> Дата по</label>
                <input type="date" name="date_to" value="<?= e($to) ?>" class="form-control">
            </div>

            <div class="form-group" style="min-width: 180px; flex: 1.2;">
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

            <div style="display: flex; gap: 8px; align-items: flex-end;">
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">
                    <i class="fa-solid fa-filter"></i> Показать
                </button>
            </div>
            
            <div style="display: flex; gap: 8px; margin-left: auto; flex-wrap: wrap; align-items: flex-end;">
                <a href="/stats/export/xlsx?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&dormitory_id=<?= urlencode($dormitory_id) ?>" 
                   class="btn" style="border: 1px solid #10b981; color: #059669; background: #ecfdf5; font-weight: 600; padding: 10px 14px; display: inline-flex; align-items: center; gap: 6px; font-size: 13px;"
                   title="Выгрузить расширенный отчёт в Excel">
                    <i class="fa-solid fa-file-excel" style="font-size: 15px;"></i> Экспорт в Excel
                </a>
                <a href="/stats/export/docx?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>&dormitory_id=<?= urlencode($dormitory_id) ?>" 
                   class="btn" style="border: 1px solid #3b82f6; color: #2563eb; background: #eff6ff; font-weight: 600; padding: 10px 14px; display: inline-flex; align-items: center; gap: 6px; font-size: 13px;"
                   title="Выгрузить отчёт в Word">
                    <i class="fa-solid fa-file-word" style="font-size: 15px;"></i> Экспорт в Word
                </a>
            </div>
        </form>
    </div>

    <!-- Карточки ключевых показателей -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
        <div class="stat-card" style="padding: 18px 16px;">
            <h4 class="stat-card-title"><i class="fa-solid fa-calendar-check" style="color: var(--primary);"></i> Всего бронирований</h4>
            <h2 class="stat-card-value" style="color: var(--primary); font-size: 34px;"><?= $totalBookings ?></h2>
            <small style="color: var(--text-muted); font-size: 11px;">за выбранный период</small>
        </div>

        <div class="stat-card" style="padding: 18px 16px;">
            <h4 class="stat-card-title"><i class="fa-solid fa-circle-check" style="color: var(--success);"></i> Активных / Состоялось</h4>
            <h2 class="stat-card-value" style="color: var(--success); font-size: 34px;"><?= $activeCount ?></h2>
            <small style="color: var(--success); font-weight: 600; font-size: 11px;"><?= $totalBookings ? round(($activeCount / $totalBookings) * 100) : 0 ?>% от общего числа</small>
        </div>

        <div class="stat-card" style="padding: 18px 16px;">
            <h4 class="stat-card-title"><i class="fa-solid fa-circle-xmark" style="color: var(--danger);"></i> Отменено</h4>
            <h2 class="stat-card-value" style="color: var(--danger); font-size: 34px;"><?= $cancelledCount ?></h2>
            <small style="color: var(--danger); font-weight: 600; font-size: 11px;"><?= $cancelledPercent ?>% отмен</small>
        </div>

        <div class="stat-card" style="padding: 18px 16px;">
            <h4 class="stat-card-title"><i class="fa-solid fa-door-closed" style="color: #0284c7;"></i> Активных комнат</h4>
            <h2 class="stat-card-value" style="color: #0284c7; font-size: 34px;"><?= $uniqueRoomsCount ?></h2>
            <small style="color: var(--text-muted); font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;" title="Самая активная: <?= e($topRoomName) ?> (<?= $topRoomCount ?>)">
                Топ: <?= e($topRoomName) ?> (<?= $topRoomCount ?>)
            </small>
        </div>
    </div>

    <!-- Графики -->
    <div class="stats-charts-grid">
        <div class="glass-card stats-chart-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 class="card-title" style="margin-bottom: 0; font-size: 15px;"><i class="fa-solid fa-chart-column"></i> Загрузка по дням</h3>
                <span class="badge" style="background: #e0e7ff; color: #4338ca; font-weight: 600; padding: 3px 8px; font-size: 11px;">
                    <?= count($dailyLabels) ?> дней с активностью
                </span>
            </div>
            <div style="flex: 1; position: relative; min-height: 280px;">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>

        <div class="glass-card stats-chart-card">
            <h3 class="card-title" style="margin-bottom: 12px; font-size: 15px;"><i class="fa-solid fa-chart-pie"></i> Оборудование и статусы</h3>
            <div style="flex: 1; position: relative; max-height: 220px; display: flex; align-items: center; justify-content: center;">
                <canvas id="typeChart"></canvas>
            </div>
            <div style="display: flex; justify-content: space-around; margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border); font-size: 12px;">
                <div>
                    <span style="display:inline-block; width:8px; height:8px; background:#6366f1; border-radius:50%; margin-right:3px;"></span>
                    Стиральные: <strong><?= $typeStats['washing'] ?></strong>
                </div>
                <div>
                    <span style="display:inline-block; width:8px; height:8px; background:#06b6d4; border-radius:50%; margin-right:3px;"></span>
                    Сушильные: <strong><?= $typeStats['drying'] ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- СВОДНАЯ СТАТИСТИКА ПО ОБЩЕЖИТИЯМ (КОРПУСАМ) -->
    <?php if (!empty($dormitoryStats)): ?>
    <div class="glass-card stats-table-card" style="margin-bottom: 20px !important;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h3 class="card-title" style="margin-bottom: 0; font-size: 15px;">
                <i class="fa-solid fa-building"></i> Статистика по общежитиям (корпусам)
            </h3>
            <span class="badge badge-info" style="font-weight: 600; font-size: 11px; padding: 2px 8px;">
                Корпусов: <?= count($dormitoryStats) ?>
            </span>
        </div>

        <div class="table-container" style="box-shadow: none; margin-bottom: 0; border-radius: 6px; border: 1px solid #e2e8f0; max-width: 100%; overflow: hidden;">
            <div class="table-scroll" style="width: 100%; overflow-x: auto;">
                <table class="stats-table" id="dormStatsTable">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="index" data-type="number" style="width: 36px; text-align: center;" title="Нажмите для сортировки по порядку">
                                <div class="th-content" style="justify-content: center; width: 100%;">№ <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                            </th>
                            <th class="sortable sorted-asc" data-sort="dorm" data-type="text" title="Нажмите для сортировки по названию общежития">
                                <div class="th-content">Общежитие <span class="sort-icon"><i class="fa-solid fa-sort-up"></i></span></div>
                            </th>
                            <th class="sortable" data-sort="rooms" data-type="number" style="text-align: center; width: 90px;" title="Нажмите для сортировки по числу активных комнат">
                                <div class="th-content" style="justify-content: center; width: 100%;">Комнат <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                            </th>
                            <th class="sortable" data-sort="total" data-type="number" style="text-align: center; width: 80px;" title="Нажмите для сортировки по общему числу стирок">
                                <div class="th-content" style="justify-content: center; width: 100%;">Всего <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                            </th>
                            <th class="sortable" data-sort="active" data-type="number" style="text-align: center; width: 80px;" title="Нажмите для сортировки по активным стиркам">
                                <div class="th-content" style="justify-content: center; width: 100%;">Акт. <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                            </th>
                            <th class="sortable" data-sort="cancelled" data-type="number" style="text-align: center; width: 80px;" title="Нажмите для сортировки по отменённым стиркам">
                                <div class="th-content" style="justify-content: center; width: 100%;">Отм. <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                            </th>
                            <th class="sortable" data-sort="pct" data-type="number" style="width: 100px; text-align: center;" title="Нажмите для сортировки по доле от всех стирок">
                                <div class="th-content" style="justify-content: center; width: 100%;">Доля <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $dIdx = 1;
                        foreach ($dormitoryStats as $dItem): 
                            $dPct = $totalBookings ? round(($dItem['total'] / $totalBookings) * 100, 1) : 0;
                            $roomsInDorm = count($dItem['rooms'] ?? []);
                        ?>
                        <tr data-orig-idx="<?= $dIdx ?>">
                            <td class="col-idx" style="text-align: center; color: var(--text-muted); font-size: 11px;" data-col="index" data-sort-value="<?= $dIdx ?>"><?= $dIdx++ ?></td>
                            <td style="font-weight: 700;" data-col="dorm" data-sort-value="<?= e($dItem['name']) ?>">
                                <span class="badge badge-dormitory" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 700; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px;">
                                    <i class="fa-solid fa-building"></i>
                                    <?= e($dItem['name']) ?>
                                </span>
                            </td>
                            <td style="text-align: center; font-weight: 600; color: #0284c7; font-size: 12px;" data-col="rooms" data-sort-value="<?= $roomsInDorm ?>"><?= $roomsInDorm ?></td>
                            <td style="text-align: center; font-weight: 700; color: var(--primary); font-size: 13px;" data-col="total" data-sort-value="<?= (int)$dItem['total'] ?>"><?= $dItem['total'] ?></td>
                            <td style="text-align: center; font-weight: 600; color: var(--success); font-size: 12px;" data-col="active" data-sort-value="<?= (int)$dItem['active'] ?>"><?= $dItem['active'] ?></td>
                            <td style="text-align: center; font-weight: 600; color: var(--danger); font-size: 12px;" data-col="cancelled" data-sort-value="<?= (int)$dItem['cancelled'] ?>"><?= $dItem['cancelled'] ?></td>
                            <td style="text-align: center;" data-col="pct" data-sort-value="<?= $dPct ?>">
                                <div style="display: flex; align-items: center; gap: 4px; justify-content: center;">
                                    <div style="width: 48px; background: #e2e8f0; height: 6px; border-radius: 999px; overflow: hidden;">
                                        <div style="width: <?= min(100, $dPct) ?>%; background: #0284c7; height: 100%; border-radius: 999px;"></div>
                                    </div>
                                    <span style="font-size: 11px; font-weight: 600; color: #64748b; min-width: 32px; text-align: right;"><?= $dPct ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Таблицы детальной аналитики: По комнатам и По оборудованию (КОМПАКТНЫЙ ВАРИАНТ) -->
    <div class="stats-tables-grid">
        
        <!-- СТАТИСТИКА ПО КОМНАТАМ -->
        <div class="glass-card stats-table-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 class="card-title" style="margin-bottom: 0; font-size: 15px;">
                    <i class="fa-solid fa-door-open"></i> Статистика по комнатам
                </h3>
                <span class="badge badge-info" style="font-weight: 600; font-size: 11px; padding: 2px 8px;">
                    Комнат: <?= count($roomStats) ?>
                </span>
            </div>

            <div class="table-container" style="box-shadow: none; margin-bottom: 0; border-radius: 6px; border: 1px solid #e2e8f0; max-width: 100%; overflow: hidden;">
                <div class="table-scroll" style="max-height: 420px; width: 100%; overflow-x: auto;">
                    <table class="stats-table" id="roomStatsTable">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="index" data-type="number" style="width: 36px; text-align: center;" title="Нажмите для сортировки по порядку">
                                    <div class="th-content" style="justify-content: center; width: 100%;">№ <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                                </th>
                                <th class="sortable" data-sort="room" data-type="text" title="Нажмите для сортировки по номеру комнаты">
                                    <div class="th-content">Комната <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                                </th>
                                <th class="sortable" data-sort="dorm" data-type="text" style="text-align: center; width: 68px;" title="Нажмите для сортировки по общежитию">
                                    <div class="th-content" style="justify-content: center; width: 100%;">Общ. <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                                </th>
                                <th class="sortable sorted-desc" data-sort="total" data-type="number" style="text-align: center; width: 56px;" title="Нажмите для сортировки по общему количеству">
                                    <div class="th-content" style="justify-content: center; width: 100%;">Всего <span class="sort-icon"><i class="fa-solid fa-sort-down"></i></span></div>
                                </th>
                                <th class="sortable" data-sort="active" data-type="number" style="text-align: center; width: 50px;" title="Нажмите для сортировки по активным записям">
                                    <div class="th-content" style="justify-content: center; width: 100%;">Акт. <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                                </th>
                                <th class="sortable" data-sort="cancelled" data-type="number" style="text-align: center; width: 50px;" title="Нажмите для сортировки по отменённым записям">
                                    <div class="th-content" style="justify-content: center; width: 100%;">Отм. <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                                </th>
                                <th class="sortable" data-sort="pct" data-type="number" style="width: 82px; text-align: center;" title="Нажмите для сортировки по доле">
                                    <div class="th-content" style="justify-content: center; width: 100%;">Доля <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($roomStats)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 12px;">
                                        Нет записей за выбранный период
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $rIdx = 1;
                                foreach ($roomStats as $rItem): 
                                    $pct = $totalBookings ? round(($rItem['total'] / $totalBookings) * 100, 1) : 0;
                                ?>
                                <tr data-orig-idx="<?= $rIdx ?>">
                                    <td class="col-idx" style="text-align: center; color: var(--text-muted); font-size: 11px;" data-col="index" data-sort-value="<?= $rIdx ?>"><?= $rIdx++ ?></td>
                                    <td style="font-weight: 700;" data-col="room" data-sort-value="<?= e($rItem['room']) ?>">
                                        <span class="badge" style="background: #f1f5f9; color: #1e293b; border: 1px solid #e2e8f0; font-size: 12px; font-weight: 700; padding: 2px 6px;">
                                            <i class="fa-solid fa-door-closed" style="color: #64748b; font-size: 10px;"></i> <?= e($rItem['room']) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;" data-col="dorm" data-sort-value="<?= e($rItem['dormitory']) ?>">
                                        <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-size: 11px; font-weight: 700; padding: 2px 5px;" title="<?= e($rItem['dormitory']) ?>">
                                            <?= e(shortDormName($rItem['dormitory'])) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center; font-weight: 700; color: var(--primary); font-size: 13px;" data-col="total" data-sort-value="<?= (int)$rItem['total'] ?>"><?= $rItem['total'] ?></td>
                                    <td style="text-align: center; font-weight: 600; color: var(--success); font-size: 12px;" data-col="active" data-sort-value="<?= (int)$rItem['active'] ?>"><?= $rItem['active'] ?></td>
                                    <td style="text-align: center; font-weight: 600; color: var(--danger); font-size: 12px;" data-col="cancelled" data-sort-value="<?= (int)$rItem['cancelled'] ?>"><?= $rItem['cancelled'] ?></td>
                                    <td style="text-align: center;" data-col="pct" data-sort-value="<?= $pct ?>">
                                        <div style="display: flex; align-items: center; gap: 4px; justify-content: center;">
                                            <div style="width: 36px; background: #e2e8f0; height: 5px; border-radius: 999px; overflow: hidden;">
                                                <div style="width: <?= min(100, $pct * 3) ?>%; background: #6366f1; height: 100%; border-radius: 999px;"></div>
                                            </div>
                                            <span style="font-size: 10px; font-weight: 600; color: #64748b; min-width: 28px; text-align: right;"><?= $pct ?>%</span>
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
        <div class="glass-card stats-table-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 class="card-title" style="margin-bottom: 0; font-size: 15px;">
                    <i class="fa-solid fa-soap"></i> Загрузка оборудования
                </h3>
                <span class="badge badge-info" style="font-weight: 600; font-size: 11px; padding: 2px 8px;">
                    Единиц: <?= count($machineStats) ?>
                </span>
            </div>

            <div class="table-container" style="box-shadow: none; margin-bottom: 0; border-radius: 6px; border: 1px solid #e2e8f0; max-width: 100%; overflow: hidden;">
                <div class="table-scroll" style="max-height: 420px; width: 100%; overflow-x: auto;">
                    <table class="stats-table" id="machineStatsTable">
                        <thead>
                            <tr>
                                <th class="sortable" data-sort="index" data-type="number" style="width: 36px; text-align: center;" title="Нажмите для сортировки по порядку">
                                    <div class="th-content" style="justify-content: center; width: 100%;">№ <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                                </th>
                                <th class="sortable" data-sort="machine" data-type="text" title="Нажмите для сортировки по названию оборудования">
                                    <div class="th-content">Оборудование <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                                </th>
                                <th class="sortable" data-sort="dorm" data-type="text" style="text-align: center; width: 68px;" title="Нажмите для сортировки по общежитию">
                                    <div class="th-content" style="justify-content: center; width: 100%;">Общ. <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                                </th>
                                <th class="sortable sorted-desc" data-sort="total" data-type="number" style="text-align: center; width: 64px;" title="Нажмите для сортировки по количеству стирок">
                                    <div class="th-content" style="justify-content: center; width: 100%;">Стирок <span class="sort-icon"><i class="fa-solid fa-sort-down"></i></span></div>
                                </th>
                                <th class="sortable" data-sort="pct" data-type="number" style="width: 82px; text-align: center;" title="Нажмите для сортировки по доле">
                                    <div class="th-content" style="justify-content: center; width: 100%;">Доля <span class="sort-icon"><i class="fa-solid fa-sort"></i></span></div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($machineStats)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 20px; color: var(--text-muted); font-size: 12px;">
                                        Нет записей за выбранный период
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $mIdx = 1;
                                foreach ($machineStats as $mItem): 
                                    $mPct = $totalBookings ? round(($mItem['total'] / $totalBookings) * 100, 1) : 0;
                                    $isDryer = str_contains(mb_strtolower($mItem['type']), 'сушил');
                                    $mNum = !empty($mItem['number']) ? $mItem['number'] : (preg_match('/#(\d+)/', $mItem['name'], $nm) ? $nm[1] : $mItem['name']);
                                ?>
                                <tr data-orig-idx="<?= $mIdx ?>">
                                    <td class="col-idx" style="text-align: center; color: var(--text-muted); font-size: 11px;" data-col="index" data-sort-value="<?= $mIdx ?>"><?= $mIdx++ ?></td>
                                    <td data-col="machine" data-sort-value="<?= e(($isDryer ? 'Сушилка ' : 'Стиралка ') . $mNum) ?>">
                                        <?php if ($isDryer): ?>
                                            <span class="badge" style="background: #cffafe; color: #0e7490; border: 1px solid #a5f3fc; font-size: 12px; font-weight: 700; padding: 3px 8px; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;">
                                                <i class="fa-solid fa-wind" style="font-size: 10px;"></i> Сушилка #<?= e($mNum) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #e0e7ff; color: #4338ca; border: 1px solid #c7d2fe; font-size: 12px; font-weight: 700; padding: 3px 8px; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap;">
                                                <i class="fa-solid fa-soap" style="font-size: 10px;"></i> Стиралка #<?= e($mNum) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;" data-col="dorm" data-sort-value="<?= e($mItem['dormitory']) ?>">
                                        <span class="badge" style="background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-size: 11px; font-weight: 700; padding: 2px 5px;" title="<?= e($mItem['dormitory']) ?>">
                                            <?= e(shortDormName($mItem['dormitory'])) ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center; font-weight: 700; color: var(--primary); font-size: 13px;" data-col="total" data-sort-value="<?= (int)$mItem['total'] ?>"><?= $mItem['total'] ?></td>
                                    <td style="text-align: center;" data-col="pct" data-sort-value="<?= $mPct ?>">
                                        <div style="display: flex; align-items: center; gap: 4px; justify-content: center;">
                                            <div style="width: 36px; background: #e2e8f0; height: 5px; border-radius: 999px; overflow: hidden;">
                                                <div style="width: <?= min(100, $mPct * 3) ?>%; background: <?= $isDryer ? '#06b6d4' : '#6366f1' ?>; height: 100%; border-radius: 999px;"></div>
                                            </div>
                                            <span style="font-size: 10px; font-weight: 600; color: #64748b; min-width: 28px; text-align: right;"><?= $mPct ?>%</span>
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
                            padding: 10,
                            boxWidth: 12
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }

    // 3. Интерактивная сортировка аналитических таблиц (по комнатам и оборудованию)
    function initStatsTableSorting(tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;

        const headers = table.querySelectorAll('th.sortable');
        const tbody = table.querySelector('tbody');

        headers.forEach(th => {
            th.addEventListener('click', () => {
                const col = th.getAttribute('data-sort');
                const colType = th.getAttribute('data-type') || 'text';
                const isCurrentlyAsc = th.classList.contains('sorted-asc');
                const isCurrentlyDesc = th.classList.contains('sorted-desc');

                let newDir = 'desc';
                if (isCurrentlyDesc) {
                    newDir = 'asc';
                } else if (isCurrentlyAsc) {
                    newDir = 'desc';
                } else {
                    // Текстовые поля и № начинают с 'asc' (по возрастанию/алфавиту), числа — с 'desc' (по убыванию)
                    newDir = (colType === 'text' || col === 'index') ? 'asc' : 'desc';
                }

                // Сброс активных классов и иконок на всех заголовках таблицы
                headers.forEach(h => {
                    h.classList.remove('sorted-asc', 'sorted-desc');
                    const icon = h.querySelector('.sort-icon i');
                    if (icon) icon.className = 'fa-solid fa-sort';
                });

                // Установка активного состояния для выбранного заголовка
                th.classList.add(newDir === 'asc' ? 'sorted-asc' : 'sorted-desc');
                const icon = th.querySelector('.sort-icon i');
                if (icon) {
                    icon.className = newDir === 'asc' ? 'fa-solid fa-sort-up' : 'fa-solid fa-sort-down';
                }

                // Извлечение строк с данными (пропускаем строку "Нет записей")
                const rows = Array.from(tbody.querySelectorAll('tr[data-orig-idx]'));
                if (!rows.length) return;

                rows.sort((rowA, rowB) => {
                    let cellA = rowA.querySelector(`[data-col="${col}"]`);
                    let cellB = rowB.querySelector(`[data-col="${col}"]`);

                    let valA = cellA ? cellA.getAttribute('data-sort-value') : '';
                    let valB = cellB ? cellB.getAttribute('data-sort-value') : '';

                    if (colType === 'number') {
                        const numA = parseFloat(valA) || 0;
                        const numB = parseFloat(valB) || 0;
                        return newDir === 'asc' ? numA - numB : numB - numA;
                    } else {
                        // Естественная алфавитно-числовая сортировка (например, 102 vs 201 vs 1001)
                        const cmp = String(valA).localeCompare(String(valB), 'ru', { numeric: true, sensitivity: 'base' });
                        return newDir === 'asc' ? cmp : -cmp;
                    }
                });

                // Перерисовка отсортированных строк с обновлением визуальной нумерации №
                rows.forEach((row, i) => {
                    tbody.appendChild(row);
                    const idxCell = row.querySelector('.col-idx');
                    if (idxCell) {
                        idxCell.textContent = i + 1;
                    }
                });
            });
        });
    }

    initStatsTableSorting('dormStatsTable');
    initStatsTableSorting('roomStatsTable');
    initStatsTableSorting('machineStatsTable');
</script>