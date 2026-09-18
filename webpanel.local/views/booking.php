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
        
        <?php if (!$isLoggedIn): ?>
            <a href="/login" class="btn btn-primary" style="font-size: 15px; padding: 10px 24px;">
                <i class="fa-solid fa-key"></i> Вход в админ-панель
            </a>
        <?php else: ?>
            <div class="user-badge">
                <i class="fa-solid fa-user"></i> <span class="user-badge-name"><?= e($_SESSION['username']) ?></span>
                <small>(<?= e($roleName) ?>)</small>
            </div>
        <?php endif; ?>
    </div>

    <!-- ФОРМА ФИЛЬТРОВ -->
    <div class="glass-card">
        <form method="GET" action="/booking" class="form-grid" id="bookingFilterForm">
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

        <!-- Быстрый выбор даты для жителей -->
        <div style="margin-top: 14px; display: flex; gap: 8px; flex-wrap: wrap; align-items: center; border-top: 1px solid rgba(0,0,0,0.06); padding-top: 12px;">
            <span style="font-size: 13px; color: #64748b; font-weight: 500;"><i class="fa-regular fa-clock"></i> Быстрый выбор:</span>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDateRange('today')">Сегодня</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDateRange('tomorrow')">Завтра</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDateRange('week')">Ближайшая неделя</button>
            <button type="button" class="btn btn-sm btn-secondary" onclick="setDateRange('month')">Месяц</button>
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

    <!-- ТАБЛИЦА БРОНИРОВАНИЙ -->
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
                        <td style="font-weight: 600;"><?= e(trim($b['last_name'] . ' ' . $b['first_name'] . ' ' . ($b['patronymic'] ?? ''))) ?></td>
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

    const today = new Date();
    
    function fmt(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    let fromStr = '', toStr = '';
    if (type === 'today') {
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

document.addEventListener('DOMContentLoaded', filterMachinesByDormitory);
</script>