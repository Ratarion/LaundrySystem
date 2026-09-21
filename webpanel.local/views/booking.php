<?php
// views/booking.php — Вид главной страницы бронирований с поддержкой общежитий
?>
<div class="content-area">
    <?php if (!empty($success)): ?>
        <div id="success-toast" class="toast-notification">
            <div class="toast-content"><i class="fa-solid fa-circle-check" style="color: var(--success);"></i> <?= e($success) ?></div>
            <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div id="error-toast" class="toast-notification" style="background: rgba(239, 68, 68, 0.95); border-left: 4px solid #b91c1c;">
            <div class="toast-content"><i class="fa-solid fa-circle-exclamation" style="color: #fff;"></i> <?= e($error) ?></div>
            <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h1 class="page-title"><i class="fa-solid fa-calendar-days"></i> Бронирования (<?= e($roleName) ?>)</h1>
        
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <?php if (!$isLoggedIn): ?>
                <a href="/hall-of-fame" class="btn" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; font-size: 14px; padding: 9px 18px; font-weight: 700; border: none; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3); display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-trophy"></i> Зал славы
                </a>
                <a href="/login" class="btn btn-primary" style="font-size: 14px; padding: 9px 20px;">
                    <i class="fa-solid fa-key"></i> Вход в админ-панель
                </a>
            <?php else: ?>
                <div class="user-badge">
                    <i class="fa-solid fa-user"></i> <span class="user-badge-name"><?= e($_SESSION['username']) ?></span>
                    <small>(<?= e($roleName) ?>)</small>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ФОРМА ФИЛЬТРОВ -->
    <div class="glass-card">
        <form method="GET" action="/booking" class="form-grid" id="bookingFilterForm">
            <input type="hidden" name="quick_range" id="quickRangeInput" value="<?= e($quick_range ?? '') ?>">
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-building"></i> Общежитие</label>
                <?php if (!empty($sessionDormId)): ?>
                    <input type="hidden" name="dormitory_id" value="<?= $sessionDormId ?>">
                    <div class="form-control" style="background: rgba(255,255,255,0.05); color: #38bdf8; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-lock" style="font-size: 12px; opacity: 0.7;"></i>
                        <?= e($_SESSION['dormitory_name'] ?? ('Общежитие №' . $sessionDormId)) ?>
                    </div>
                <?php else: ?>
                    <select name="dormitory_id" id="dormitorySelect" class="form-control" onchange="filterMachinesByDormitory()">
                        <option value="">Все корпуса</option>
                        <?php foreach ($dormitories as $d): ?>
                            <option value="<?= $d->id ?>" <?= ($dormitory_id == $d->id) ? 'selected' : '' ?>>
                                <?= e($d->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-soap"></i> Машинка</label>
                <select name="machine_id" id="machineSelect" class="form-control">
                    <option value="">Все машинки</option>
                    <?php foreach ($machines as $m): ?>
                        <option value="<?= $m->id ?>" 
                                data-dormitory="<?= $m->dormitory_id ?>"
                                <?= ($machine_id == $m->id) ? 'selected' : '' ?>>
                            <?= e($m->dormitory_name ?? ('Общ. №' . $m->dormitory_id)) ?> — <?= e($m->type_machine) ?> #<?= e($m->number_machine) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-user"></i> ФИО жителя</label>
                <input type="text" name="fio" value="<?= e($fio ?? '') ?>" class="form-control" placeholder="Поиск по ФИО...">
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-calendar-day"></i> Дата с</label>
                <input type="date" name="date_from" id="dateFromInput" value="<?= e($date_from) ?>" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-calendar-day"></i> Дата по</label>
                <input type="date" name="date_to" id="dateToInput" value="<?= e($date_to) ?>" class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-tag"></i> Статус</label>
                <select name="status" class="form-control">
                    <option value="">Все</option>
                    <option value="Ожидание" <?= $status==='Ожидание'?'selected':'' ?>>Ожидание</option>
                    <option value="Ожидание подтверждения" <?= $status==='Ожидание подтверждения'?'selected':'' ?>>Ожидание подтверждения</option>
                    <option value="Подтверждено" <?= $status==='Подтверждено'?'selected':'' ?>>Подтверждено</option>
                    <option value="Отменено" <?= $status==='Отменено'?'selected':'' ?>>Отменено</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px; align-items: flex-end;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <i class="fa-solid fa-filter"></i> Применить
                </button>
                <a href="/booking" class="btn btn-secondary" title="Сбросить фильтры" style="padding: 10px 14px;">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>

        <!-- Быстрый выбор даты -->
        <div style="margin-top: 14px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; border-top: 1px solid rgba(0,0,0,0.06); padding-top: 12px;">
            <span style="font-size: 13px; color: #64748b; font-weight: 500;"><i class="fa-regular fa-clock"></i> Быстрый выбор:</span>
            <button type="button" data-range="today_tomorrow" class="btn btn-sm quick-date-btn <?= (($quick_range ?? '') === 'today_tomorrow') ? 'btn-primary' : 'btn-secondary' ?>" onclick="setDateRange('today_tomorrow')">Сегодня и завтра</button>
            <button type="button" data-range="today" class="btn btn-sm quick-date-btn <?= (($quick_range ?? '') === 'today') ? 'btn-primary' : 'btn-secondary' ?>" onclick="setDateRange('today')">Сегодня</button>
            <button type="button" data-range="tomorrow" class="btn btn-sm quick-date-btn <?= (($quick_range ?? '') === 'tomorrow') ? 'btn-primary' : 'btn-secondary' ?>" onclick="setDateRange('tomorrow')">Завтра</button>
            <button type="button" data-range="week" class="btn btn-sm quick-date-btn <?= (($quick_range ?? '') === 'week') ? 'btn-primary' : 'btn-secondary' ?>" onclick="setDateRange('week')">Ближайшая неделя</button>
            <button type="button" data-range="month" class="btn btn-sm quick-date-btn <?= (($quick_range ?? '') === 'month') ? 'btn-primary' : 'btn-secondary' ?>" onclick="setDateRange('month')">Месяц</button>
        </div>
    </div>

    <!-- МАССОВАЯ ОТМЕНА (только для авторизованного персонала) -->
    <?php if ($isLoggedIn): ?>
    <div class="glass-card">
        <h3 class="card-title"><i class="fa-solid fa-trash"></i> Массовая отмена с оповещением в боты</h3>
        <form method="POST" class="form-grid">
            <div class="form-group">
                <label class="form-label">Корпус / Общежитие</label>
                <?php if (!empty($sessionDormId)): ?>
                    <input type="hidden" name="cancel_dormitory_id" value="<?= $sessionDormId ?>">
                    <div class="form-control" style="background: rgba(255,255,255,0.05); color: #38bdf8; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-lock" style="font-size: 12px; opacity: 0.7;"></i>
                        <?= e($_SESSION['dormitory_name'] ?? ('Общежитие №' . $sessionDormId)) ?>
                    </div>
                <?php else: ?>
                    <select name="cancel_dormitory_id" class="form-control">
                        <option value="">Все общежития</option>
                        <?php foreach ($dormitories as $d): ?>
                            <option value="<?= $d->id ?>"><?= e($d->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Дата</label>
                <input type="date" name="cancel_date" value="<?= date('Y-m-d') ?>" required class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">Тип машины</label>
                <select name="type_machine" required class="form-control">
                    <option value="Стиральная">Стиральная</option>
                    <option value="Сушильная">Сушильная</option>
                </select>
            </div>

            <div class="form-group" style="flex: 1 1 100%; width: 100%;">
                <label class="form-label"><i class="fa-solid fa-comment-dots"></i> Сообщение жителям (причина отмены, будет отправлена в боты):</label>
                <input type="text" name="cancel_reason" value="Технические работы в прачечной" required class="form-control" placeholder="Например: Авария водопровода, санитарный день или ремонт оборудования">
            </div>

            <div style="flex: 1 1 100%; width: 100%; margin-top: 4px;">
                <button type="submit" name="mass_cancel" class="btn btn-warning" 
                        onclick="return confirm('Отменить ВСЕ записи на выбранную дату и разослать уведомления жителям с указанной причиной?')">
                    <i class="fa-solid fa-bell-slash"></i> Отменить все и разослать уведомления в боты
                </button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- ПАНЕЛЬ ДЕЙСТВИЙ: ПЕРЕКЛЮЧЕНИЕ ВИДА И ЭКСПОРТ -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
        <div class="view-mode-toggle" style="display: inline-flex; border-radius: 8px; overflow: hidden; border: 1px solid var(--border); background: #f1f5f9; padding: 3px; gap: 4px;">
            <button type="button" id="toggleTableBtn" class="btn btn-sm btn-primary" onclick="switchViewMode('table')" style="border-radius: 6px; padding: 7px 16px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-table-list"></i> Таблица
            </button>
            <button type="button" id="toggleTimelineBtn" class="btn btn-sm btn-secondary" onclick="switchViewMode('timeline')" style="border-radius: 6px; padding: 7px 16px; font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-chart-gantt"></i> Шахматка
            </button>
        </div>

        <?php if ($isLoggedIn): 
            $exportParams = $_GET;
            $exportParams['export'] = 'csv';
            $exportUrl = '/booking?' . http_build_query($exportParams);
        ?>
            <a href="<?= e($exportUrl) ?>" class="btn" style="background: #10b981; color: #fff; font-size: 13px; padding: 8px 16px; font-weight: 600; border: none; display: inline-flex; align-items: center; gap: 8px; border-radius: 8px; box-shadow: 0 2px 6px rgba(16, 185, 129, 0.25);">
                <i class="fa-solid fa-file-excel"></i> Экспорт в Excel / CSV
            </a>
        <?php endif; ?>
    </div>

    <!-- ТАБЛИЧНЫЙ ВИД БРОНИРОВАНИЙ -->
    <div id="tableViewContainer">
    <?php if (empty($bookings)): ?>
        <div class="glass-card" style="text-align: center; padding: 48px 20px;">
            <div style="font-size: 52px; color: var(--primary); margin-bottom: 16px;">
                <i class="fa-regular fa-calendar-check"></i>
            </div>
            <h3 style="margin-bottom: 8px; color: #1e293b;">На выбранные параметры и дату записей нет</h3>
            <p style="color: #64748b; max-width: 520px; margin: 0 auto 20px; font-size: 14px;">
                В этот период все машинки свободны! Жители могут забронировать удобный слот через бота в Telegram, MAX или ВКонтакте.
            </p>
            <a href="/booking" class="btn btn-primary" style="display: inline-block;">
                <i class="fa-solid fa-rotate-left"></i> Показать все записи
            </a>
        </div>
    <?php else: ?>
    <div class="table-container">
        <div class="table-scroll">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th style="width: 170px; white-space: nowrap;">Общежитие</th>
                        <th>Житель</th>
                        <th style="width: 90px; text-align: center;">Комната</th>
                        <th>Машина</th>
                        <th>Начало</th>
                        <th>Конец</th>
                        <th style="text-align: center; width: 160px;">Статус</th>
                        <?php if ($isAdmin): ?>
                            <th style="text-align: center; width: 120px;">Действие</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): 
                        $currStatus = $b['status'];
                        $badgeClass = 'badge-secondary';
                        if ($currStatus === 'Ожидание') {
                            $badgeClass = 'badge-warning';
                        } elseif ($currStatus === 'Ожидание подтверждения') {
                            $badgeClass = 'badge-info';
                        } elseif ($currStatus === 'Подверженная' || $currStatus === 'Подтверждено') {
                            $badgeClass = 'badge-success';
                        } elseif (in_array($currStatus, ['cancelled', 'Отменено', 'Отмена'])) {
                            $badgeClass = 'badge-danger';
                        }
                    ?>
                    <tr>
                        <td><?= $b['id'] ?></td>
                        <td style="white-space: nowrap;">
                            <span class="badge badge-dormitory" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 700; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;">
                                <i class="fa-solid fa-building"></i>
                                <?= e(!empty($b['dormitory_name']) ? $b['dormitory_name'] : ('Общежитие №' . ($b['dormitory_id'] ?? '1'))) ?>
                            </span>
                        </td>
                        <td style="font-weight: 600;">
                            <div><?= e(trim($b['last_name'] . ' ' . $b['first_name'] . ' ' . ($b['patronymic'] ?? ''))) ?></div>
                            <?php if (isset($b['score']) && $b['score'] !== null): 
                                $rScore = (int)$b['score'];
                                $rStreak = !empty($b['confirm_streak']) ? (int)$b['confirm_streak'] : 0;
                                $scoreColor = '#10b981'; $scoreBg = '#ecfdf5'; $rankIcon = 'fa-circle-check';
                                if ($rScore >= 140) { $scoreColor = '#6366f1'; $scoreBg = '#e0e7ff'; $rankIcon = 'fa-gem'; }
                                elseif ($rScore < 20) { $scoreColor = '#ef4444'; $scoreBg = '#fee2e2'; $rankIcon = 'fa-ban'; }
                                elseif ($rScore < 50) { $scoreColor = '#f97316'; $scoreBg = '#ffedd5'; $rankIcon = 'fa-triangle-exclamation'; }
                                elseif ($rScore < 90) { $scoreColor = '#f59e0b'; $scoreBg = '#fef3c7'; $rankIcon = 'fa-circle-exclamation'; }
                            ?>
                                <div style="margin-top: 4px; display: inline-flex; align-items: center; gap: 5px;">
                                    <span title="Дисциплина: <?= $rScore ?> баллов" style="font-size: 11px; padding: 2px 7px; border-radius: 9999px; background: <?= $scoreBg ?>; color: <?= $scoreColor ?>; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa-solid <?= $rankIcon ?>" style="font-size: 10px;"></i> <?= $rScore ?> б.
                                    </span>
                                    <?php if ($rStreak >= 2): ?>
                                        <span title="Серия подтверждений подряд: <?= $rStreak ?>" style="font-size: 11px; padding: 2px 6px; border-radius: 9999px; background: #fff1f2; color: #e11d48; font-weight: 700;">
                                            🔥 <?= $rStreak ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center; font-weight: 600;"><?= e($b['inidroom']) ?></td>
                        <td><?= e($b['type_machine']) ?> #<?= e($b['number_machine']) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($b['start_time'])) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($b['end_time'])) ?></td>
                        <td style="text-align: center;">
                            <span class="badge <?= $badgeClass ?>">
                                <?= e($currStatus) ?>
                            </span>
                        </td>
                        <?php if ($isAdmin): ?>
                        <td style="text-align: center;">
                            <?php if ($currStatus !== 'Отменено'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirmSingleCancel(this)">
                                <input type="hidden" name="cancel_id" value="<?= $b['id'] ?>">
                                <input type="hidden" name="cancel_reason" value="">
                                <button type="submit" class="btn btn-danger" style="padding: 6px 14px; font-size: 13px;">
                                    <i class="fa-solid fa-xmark"></i> Отменить
                                </button>
                            </form>
                            <?php else: ?>
                            <span style="color: #94a3b8; font-size: 13px; font-weight: 600;">Отменено</span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    </div>

    <!-- ШАХМАТКА (TIMELINE VIEW) -->
    <?php
    $startD = new DateTime($date_from);
    $endD   = new DateTime($date_to);
    $endD->modify('+1 day');
    $period = new DatePeriod($startD, new DateInterval('P1D'), $endD);
    $timelineDates = [];
    foreach ($period as $dt) {
        $timelineDates[] = $dt->format('Y-m-d');
    }
    if (count($timelineDates) > 14) {
        $timelineDates = array_slice($timelineDates, 0, 14);
    }
    if (empty($timelineDates)) {
        $timelineDates[] = date('Y-m-d');
    }

    $timelineBookings = [];
    foreach ($bookings as $b) {
        $bDate = date('Y-m-d', strtotime($b['start_time']));
        $mId = (int)($b['machine_id'] ?? $b['inidmachine'] ?? 0);
        if (!isset($timelineBookings[$bDate])) {
            $timelineBookings[$bDate] = [];
        }
        if (!isset($timelineBookings[$bDate][$mId])) {
            $timelineBookings[$bDate][$mId] = [];
        }
        $timelineBookings[$bDate][$mId][] = $b;
    }

    $displayMachines = $machines;
    if (!empty($machine_id)) {
        $displayMachines = array_filter($machines, function($m) use ($machine_id) {
            return (int)$m->id === (int)$machine_id;
        });
    }
    usort($displayMachines, function($a, $b) {
        if ($a->dormitory_id != $b->dormitory_id) return $a->dormitory_id <=> $b->dormitory_id;
        if ($a->type_machine != $b->type_machine) return strcmp($b->type_machine, $a->type_machine);
        return strnatcmp($a->number_machine, $b->number_machine);
    });
    ?>

    <div id="timelineViewContainer" style="display: none;">
        <!-- Переключатель дней для шахматки -->
        <div class="timeline-date-tabs" style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px;">
            <?php foreach ($timelineDates as $idx => $tDate): 
                $dTime = strtotime($tDate);
                $isToday = ($tDate === date('Y-m-d'));
                $isTomorrow = ($tDate === date('Y-m-d', strtotime('+1 day')));
                $label = date('d.m', $dTime);
                if ($isToday) $label .= ' (Сегодня)';
                elseif ($isTomorrow) $label .= ' (Завтра)';
                else {
                    $daysRu = ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'];
                    $label .= ' (' . $daysRu[(int)date('w', $dTime)] . ')';
                }
            ?>
                <button type="button" class="btn btn-sm timeline-day-tab <?= ($idx === 0) ? 'btn-primary' : 'btn-secondary' ?>" 
                        data-date="<?= $tDate ?>" 
                        onclick="showTimelineDay('<?= $tDate ?>')">
                    <i class="fa-regular fa-calendar"></i> <?= e($label) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Доски по каждому дню -->
        <?php foreach ($timelineDates as $idx => $tDate): 
            $isToday = ($tDate === date('Y-m-d'));
            $nowMin = (int)date('G') * 60 + (int)date('i');
            $showNowLine = $isToday && ($nowMin >= 480 && $nowMin <= 1380);
            $nowPct = $showNowLine ? round(($nowMin - 480) / 900 * 100, 3) : 0;
        ?>
        <div class="timeline-day-board glass-card" id="timeline-day-<?= $tDate ?>" style="padding: 0; overflow: hidden; <?= ($idx === 0) ? '' : 'display: none;' ?>">
            <div style="overflow-x: auto; width: 100%;">
                <div style="min-width: 980px;">
                    <!-- Заголовок сетки с часами -->
                    <div style="display: flex; background: #f8fafc; border-bottom: 2px solid var(--border); font-size: 12px; font-weight: 700; color: #64748b;">
                        <div style="width: 200px; flex-shrink: 0; padding: 12px 16px; border-right: 1px solid var(--border);">
                            <i class="fa-solid fa-soap"></i> Машина / Корпус
                        </div>
                        <div style="flex: 1; display: flex; position: relative;">
                            <?php for ($h = 8; $h <= 22; $h++): ?>
                                <div style="width: calc(100% / 15); padding: 12px 4px; text-align: left; border-right: 1px dashed rgba(226, 232, 240, 0.9); box-sizing: border-box;">
                                    <?= sprintf('%02d:00', $h) ?>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Строки машинок -->
                    <?php if (empty($displayMachines)): ?>
                        <div style="padding: 36px; text-align: center; color: #94a3b8;">
                            <i class="fa-solid fa-ban" style="font-size: 32px; margin-bottom: 8px;"></i>
                            <div>Нет доступных машинок в выбранном общежитии.</div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($displayMachines as $m): 
                            $mBookings = $timelineBookings[$tDate][$m->id] ?? [];
                            $isWash = ($m->type_machine === 'Стиральная');
                            $typeIcon = $isWash ? 'fa-soap' : 'fa-wind';
                            $iconColor = $isWash ? '#0284c7' : '#f59e0b';
                        ?>
                        <div style="display: flex; border-bottom: 1px solid var(--border); align-items: stretch; background: #ffffff;">
                            <!-- Колонка машины -->
                            <div style="width: 200px; flex-shrink: 0; padding: 10px 16px; border-right: 1px solid var(--border); background: #f8fafc; display: flex; flex-direction: column; justify-content: center;">
                                <div style="font-size: 13px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 6px;">
                                    <i class="fa-solid <?= $typeIcon ?>" style="color: <?= $iconColor ?>;"></i>
                                    <?= e($m->type_machine) ?> #<?= e($m->number_machine) ?>
                                </div>
                                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                    <?= e($m->dormitory_name ?? ('Общежитие №' . $m->dormitory_id)) ?>
                                </div>
                            </div>

                            <!-- Дорожка таймлайна -->
                            <div style="flex: 1; position: relative; height: 54px; background: #ffffff;">
                                <!-- Фоновая сетка часов -->
                                <div style="display: flex; width: 100%; height: 100%; position: absolute; top: 0; left: 0; pointer-events: none;">
                                    <?php for ($h = 8; $h <= 22; $h++): ?>
                                        <div style="width: calc(100% / 15); height: 100%; border-right: 1px dashed rgba(226, 232, 240, 0.9); box-sizing: border-box;"></div>
                                    <?php endfor; ?>
                                </div>

                                <!-- Линия текущего времени -->
                                <?php if ($showNowLine): ?>
                                    <div style="position: absolute; left: <?= $nowPct ?>%; top: 0; bottom: 0; width: 2px; background: #ef4444; z-index: 15; pointer-events: none;" title="Текущее время: <?= date('H:i') ?>">
                                        <span style="position: absolute; top: -14px; left: -14px; background: #ef4444; color: #fff; font-size: 9px; font-weight: 700; padding: 1px 4px; border-radius: 3px; box-shadow: 0 1px 3px rgba(0,0,0,0.2);">Сейчас</span>
                                    </div>
                                <?php endif; ?>

                                <!-- Блоки бронирований -->
                                <?php foreach ($mBookings as $b): 
                                    $bStartSec   = strtotime($b['start_time']);
                                    $bEndSec     = !empty($b['end_time']) ? strtotime($b['end_time']) : ($bStartSec + 90 * 60);
                                    $dayStartSec = strtotime($tDate . ' 08:00:00');
                                    $dayEndSec   = strtotime($tDate . ' 23:00:00');

                                    if ($bEndSec <= $dayStartSec || $bStartSec >= $dayEndSec) continue;

                                    $clampedStart = max($bStartSec, $dayStartSec);
                                    $clampedEnd   = min($bEndSec, $dayEndSec);

                                    $leftPct  = round(($clampedStart - $dayStartSec) / (15 * 3600) * 100, 3);
                                    $widthPct = max(2.5, round(($clampedEnd - $clampedStart) / (15 * 3600) * 100, 3));

                                    $bStatus = $b['status'];
                                    $slotBg = '#f3f4f6';
                                    $slotColor = '#1f2937';
                                    $slotBorder = '#d1d5db';
                                    $badgeCls = 'badge-secondary';

                                    if ($bStatus === 'Подтверждено' || $bStatus === 'Подверженная') {
                                        $slotBg = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                                        $slotColor = '#ffffff';
                                        $slotBorder = '#047857';
                                        $badgeCls = 'badge-success';
                                    } elseif ($bStatus === 'Ожидание подтверждения') {
                                        $slotBg = 'linear-gradient(135deg, #0284c7 0%, #0369a1 100%)';
                                        $slotColor = '#ffffff';
                                        $slotBorder = '#0284c7';
                                        $badgeCls = 'badge-info';
                                    } elseif ($bStatus === 'Ожидание') {
                                        $slotBg = 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)';
                                        $slotColor = '#ffffff';
                                        $slotBorder = '#b45309';
                                        $badgeCls = 'badge-warning';
                                    } elseif (in_array($bStatus, ['Отменено', 'cancelled', 'Отмена'])) {
                                        $slotBg = '#fee2e2';
                                        $slotColor = '#991b1b';
                                        $slotBorder = '#ef4444';
                                        $badgeCls = 'badge-danger';
                                    }

                                    $fioFull = trim(($b['last_name'] ?? '') . ' ' . ($b['first_name'] ?? '') . ' ' . ($b['patronymic'] ?? ''));
                                    $fioShort = e(($b['last_name'] ?? '') . ' ' . mb_substr($b['first_name'] ?? '', 0, 1) . '.');
                                    $timeStr = date('H:i', $bStartSec) . ' - ' . date('H:i', $bEndSec);
                                    $dateTimeStr = date('d.m.Y H:i', $bStartSec) . ' - ' . date('H:i', $bEndSec);
                                    $mName = ($b['type_machine'] ?? $m->type_machine) . ' #' . ($b['number_machine'] ?? $m->number_machine);
                                    $dName = !empty($b['dormitory_name']) ? $b['dormitory_name'] : ($m->dormitory_name ?? ('Общежитие №' . $m->dormitory_id));
                                    $score = isset($b['score']) && $b['score'] !== null ? $b['score'] : '';
                                    $streak = !empty($b['confirm_streak']) ? $b['confirm_streak'] : '';
                                    $isCancelable = ($isAdmin && !in_array($bStatus, ['Отменено', 'cancelled', 'Отмена'])) ? '1' : '0';
                                ?>
                                <div class="timeline-slot" 
                                     onclick="openTimelineModal(this)"
                                     data-id="<?= $b['id'] ?>"
                                     data-dorm="<?= e($dName) ?>"
                                     data-machine="<?= e($mName) ?>"
                                     data-time="<?= e($dateTimeStr) ?>"
                                     data-resident="<?= e($fioFull) ?>"
                                     data-room="<?= e($b['inidroom'] ?? '-') ?>"
                                     data-status="<?= e($bStatus) ?>"
                                     data-badge="<?= $badgeCls ?>"
                                     data-score="<?= e($score) ?>"
                                     data-streak="<?= e($streak) ?>"
                                     data-cancelable="<?= $isCancelable ?>"
                                     style="position: absolute; left: <?= $leftPct ?>%; width: <?= $widthPct ?>%; top: 5px; bottom: 5px; border-radius: 6px; z-index: 5; cursor: pointer; display: flex; align-items: center; padding: 0 8px; font-size: 11px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; background: <?= $slotBg ?>; color: <?= $slotColor ?>; border: 1px solid <?= $slotBorder ?>; box-shadow: 0 2px 4px rgba(0,0,0,0.08); transition: transform 0.15s ease, box-shadow 0.15s ease;"
                                     onmouseover="this.style.transform='scale(1.02)'; this.style.zIndex='25'; this.style.boxShadow='0 4px 10px rgba(0,0,0,0.2)';"
                                     onmouseout="this.style.transform='none'; this.style.zIndex='5'; this.style.boxShadow='0 2px 4px rgba(0,0,0,0.08)';"
                                     title="<?= e($mName . ' | ' . $timeStr . ' | ' . $fioFull . ' (' . $bStatus . ')') ?>">
                                    <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <strong><?= $timeStr ?></strong> <?= $fioShort ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Легенда шахматки -->
        <div style="margin-top: 14px; display: flex; gap: 16px; flex-wrap: wrap; align-items: center; font-size: 12px; color: #64748b; padding: 10px 16px; background: #ffffff; border-radius: 8px; border: 1px solid var(--border);">
            <strong style="color: #1e293b;"><i class="fa-solid fa-palette"></i> Обозначения:</strong>
            <span style="display: inline-flex; align-items: center; gap: 6px;">
                <span style="width: 12px; height: 12px; border-radius: 3px; background: #10b981; display: inline-block;"></span> Подтверждено
            </span>
            <span style="display: inline-flex; align-items: center; gap: 6px;">
                <span style="width: 12px; height: 12px; border-radius: 3px; background: #0284c7; display: inline-block;"></span> Ожидание подтверждения
            </span>
            <span style="display: inline-flex; align-items: center; gap: 6px;">
                <span style="width: 12px; height: 12px; border-radius: 3px; background: #f59e0b; display: inline-block;"></span> Ожидание
            </span>
            <span style="display: inline-flex; align-items: center; gap: 6px;">
                <span style="width: 12px; height: 12px; border-radius: 3px; background: #fee2e2; border: 1px dashed #ef4444; display: inline-block;"></span> Отменено
            </span>
            <span style="display: inline-flex; align-items: center; gap: 6px;">
                <span style="width: 12px; height: 2px; background: #ef4444; display: inline-block;"></span> Текущее время
            </span>
        </div>
    </div>

    <!-- МОДАЛЬНОЕ ОКНО ДЕТАЛЕЙ БРОНИРОВАНИЯ ИЗ ШАХМАТКИ -->
    <div id="timelineDetailModal" class="custom-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
        <div class="glass-card" style="width: 100%; max-width: 480px; margin: 20px; padding: 0; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid var(--border);">
            <div style="padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                <h3 style="margin: 0; font-size: 16px; font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-calendar-check" style="color: var(--primary);"></i> Бронирование #<span id="tdmId"></span>
                </h3>
                <button type="button" onclick="closeTimelineModal()" style="background: transparent; border: none; font-size: 20px; color: #94a3b8; cursor: pointer;">✕</button>
            </div>
            <div style="padding: 24px;">
                <div style="display: flex; flex-direction: column; gap: 12px; font-size: 14px;">
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        <span style="color: #64748b;"><i class="fa-solid fa-building"></i> Общежитие:</span>
                        <strong id="tdmDorm" style="color: #0369a1;"></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        <span style="color: #64748b;"><i class="fa-solid fa-soap"></i> Машинка:</span>
                        <strong id="tdmMachine"></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        <span style="color: #64748b;"><i class="fa-solid fa-clock"></i> Время:</span>
                        <strong id="tdmTime"></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        <span style="color: #64748b;"><i class="fa-solid fa-user"></i> Житель:</span>
                        <strong id="tdmResident"></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        <span style="color: #64748b;"><i class="fa-solid fa-door-closed"></i> Комната:</span>
                        <strong id="tdmRoom"></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        <span style="color: #64748b;"><i class="fa-solid fa-tag"></i> Статус:</span>
                        <span id="tdmStatus"></span>
                    </div>
                    <div id="tdmDisciplineRow" style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        <span style="color: #64748b;"><i class="fa-solid fa-star"></i> Дисциплина:</span>
                        <span id="tdmDiscipline"></span>
                    </div>
                </div>

                <?php if ($isAdmin): ?>
                <div id="tdmAdminAction" style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0; display: none;">
                    <form method="POST" id="tdmCancelForm" onsubmit="return confirmTimelineCancel(this)">
                        <input type="hidden" name="cancel_id" id="tdmCancelId" value="">
                        <input type="hidden" name="cancel_reason" id="tdmCancelReason" value="">
                        <button type="submit" class="btn btn-danger" style="width: 100%; padding: 10px; font-weight: 600;">
                            <i class="fa-solid fa-xmark"></i> Отменить бронирование
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <div style="padding: 12px 24px; background: #f8fafc; border-top: 1px solid var(--border); text-align: right;">
                <button type="button" class="btn btn-secondary" onclick="closeTimelineModal()">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<script>
function filterMachinesByDormitory() {
    const dormSelect = document.getElementById('dormitorySelect');
    const machineSelect = document.getElementById('machineSelect');
    if (!dormSelect || !machineSelect) return;

    const selectedDorm = dormSelect.value;
    
    for (let i = 0; i < machineSelect.options.length; i++) {
        const opt = machineSelect.options[i];
        if (!opt.value) continue;
        const optDorm = opt.getAttribute('data-dormitory');
        if (!selectedDorm || optDorm === selectedDorm) {
            opt.style.display = '';
        } else {
            opt.style.display = 'none';
            if (opt.selected) {
                machineSelect.value = '';
            }
        }
    }
    if (machineSelect._customSelect) {
        machineSelect._customSelect.sync();
    }
}

function setDateRange(type) {
    const fromInput = document.getElementById('dateFromInput');
    const toInput = document.getElementById('dateToInput');
    if (!fromInput || !toInput) return;

    // Мгновенная визуальная подсветка активной кнопки
    const quickRangeInput = document.getElementById('quickRangeInput');
    if (quickRangeInput) {
        quickRangeInput.value = type;
    }
    document.querySelectorAll('.quick-date-btn').forEach(btn => {
        if (btn.getAttribute('data-range') === type) {
            btn.classList.remove('btn-secondary');
            btn.classList.add('btn-primary');
        } else {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-secondary');
        }
    });

    const today = new Date();
    
    function fmt(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    let fromStr = '', toStr = '';
    if (type === 'today_tomorrow') {
        fromStr = fmt(today);
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        toStr = fmt(tomorrow);
    } else if (type === 'today') {
        fromStr = toStr = fmt(today);
    } else if (type === 'tomorrow') {
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        fromStr = toStr = fmt(tomorrow);
    } else if (type === 'week') {
        fromStr = fmt(today);
        const week = new Date(today);
        week.setDate(week.getDate() + 6);
        toStr = fmt(week);
    } else if (type === 'month') {
        const past = new Date(today);
        past.setDate(past.getDate() - 7);
        fromStr = fmt(past);
        const next = new Date(today);
        next.setDate(next.getDate() + 23);
        toStr = fmt(next);
    }

    if (fromInput._flatpickr) {
        fromInput._flatpickr.setDate(fromStr, false);
    }
    fromInput.value = fromStr;

    if (toInput._flatpickr) {
        toInput._flatpickr.setDate(toStr, false);
    }
    toInput.value = toStr;

    document.getElementById('bookingFilterForm').submit();
}

function confirmSingleCancel(form) {
    const reason = prompt("Укажите причину отмены (сообщение будет отправлено жителю в бот):", "По решению администратора");
    if (reason === null) {
        return false;
    }
    const reasonInput = form.querySelector('input[name="cancel_reason"]');
    if (reasonInput) {
        reasonInput.value = reason.trim() || "По решению администратора";
    }
    return true;
}

// Переключение между таблицей и шахматкой
function switchViewMode(mode) {
    try {
        localStorage.setItem('laundry_view_mode', mode);
    } catch (e) {}

    const tableDiv = document.getElementById('tableViewContainer');
    const timelineDiv = document.getElementById('timelineViewContainer');
    const tableBtn = document.getElementById('toggleTableBtn');
    const timelineBtn = document.getElementById('toggleTimelineBtn');

    if (mode === 'timeline') {
        if (tableDiv) tableDiv.style.display = 'none';
        if (timelineDiv) timelineDiv.style.display = 'block';
        if (tableBtn) { tableBtn.classList.remove('btn-primary'); tableBtn.classList.add('btn-secondary'); }
        if (timelineBtn) { timelineBtn.classList.remove('btn-secondary'); timelineBtn.classList.add('btn-primary'); }
    } else {
        if (tableDiv) tableDiv.style.display = 'block';
        if (timelineDiv) timelineDiv.style.display = 'none';
        if (tableBtn) { tableBtn.classList.remove('btn-secondary'); tableBtn.classList.add('btn-primary'); }
        if (timelineBtn) { timelineBtn.classList.remove('btn-primary'); timelineBtn.classList.add('btn-secondary'); }
    }
}

// Переключение активного дня на шахматке
function showTimelineDay(tDate) {
    document.querySelectorAll('.timeline-day-board').forEach(function(el) {
        el.style.display = 'none';
    });
    document.querySelectorAll('.timeline-day-tab').forEach(function(btn) {
        if (btn.getAttribute('data-date') === tDate) {
            btn.classList.remove('btn-secondary');
            btn.classList.add('btn-primary');
        } else {
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-secondary');
        }
    });
    const targetBoard = document.getElementById('timeline-day-' + tDate);
    if (targetBoard) {
        targetBoard.style.display = 'block';
    }
}

// Открытие модального окна деталей слота
function openTimelineModal(el) {
    const d = el.dataset;
    document.getElementById('tdmId').innerText = d.id || '';
    document.getElementById('tdmDorm').innerText = d.dorm || '';
    document.getElementById('tdmMachine').innerText = d.machine || '';
    document.getElementById('tdmTime').innerText = d.time || '';
    document.getElementById('tdmResident').innerText = d.resident || '';
    document.getElementById('tdmRoom').innerText = d.room || '-';

    const statusEl = document.getElementById('tdmStatus');
    statusEl.innerText = d.status || '';
    statusEl.className = 'badge ' + (d.badge || 'badge-secondary');

    const discRow = document.getElementById('tdmDisciplineRow');
    if (d.score !== undefined && d.score !== '') {
        discRow.style.display = 'flex';
        let discHtml = `<strong>${d.score} б.</strong>`;
        if (d.streak && parseInt(d.streak) >= 2) {
            discHtml += ` <span style="color:#e11d48; margin-left:6px; font-weight:700;">🔥 ${d.streak}</span>`;
        }
        document.getElementById('tdmDiscipline').innerHTML = discHtml;
    } else {
        discRow.style.display = 'none';
    }

    const adminAction = document.getElementById('tdmAdminAction');
    if (adminAction) {
        if (d.cancelable === '1') {
            adminAction.style.display = 'block';
            document.getElementById('tdmCancelId').value = d.id;
        } else {
            adminAction.style.display = 'none';
        }
    }

    const modal = document.getElementById('timelineDetailModal');
    if (modal) modal.style.display = 'flex';
}

function closeTimelineModal() {
    const modal = document.getElementById('timelineDetailModal');
    if (modal) modal.style.display = 'none';
}

function confirmTimelineCancel(form) {
    const reason = prompt("Укажите причину отмены (сообщение будет отправлено жителю в бот):", "По решению администратора");
    if (reason === null) return false;
    document.getElementById('tdmCancelReason').value = reason.trim() || "По решению администратора";
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    filterMachinesByDormitory();

    // Восстановление сохраненного режима просмотра (таблица или шахматка)
    try {
        const savedMode = localStorage.getItem('laundry_view_mode');
        if (savedMode === 'timeline') {
            switchViewMode('timeline');
        }
    } catch (e) {}

    // Закрытие модального окна по клику вне его или клавише Esc
    const tModal = document.getElementById('timelineDetailModal');
    if (tModal) {
        tModal.addEventListener('click', function(e) {
            if (e.target === tModal) closeTimelineModal();
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeTimelineModal();
    });

    // Сброс активной подсветки быстрого выбора при ручном изменении дат
    ['dateFromInput', 'dateToInput'].forEach(function(id) {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('change', function() {
                const quickRangeInput = document.getElementById('quickRangeInput');
                if (quickRangeInput) quickRangeInput.value = '';
                document.querySelectorAll('.quick-date-btn').forEach(function(btn) {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-secondary');
                });
            });
        }
    });
});
</script>