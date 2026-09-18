<?php
// views/notifications.php — Редизайн страницы уведомлений
?>
<div class="content-area">
    <?php if (isset($success)): ?>
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
        <h1 class="page-title"><i class="fa-solid fa-bell"></i> Уведомления</h1>
        <div class="user-badge">
            <i class="fa-solid fa-user"></i> <span class="user-badge-name"><?= e($_SESSION['username']) ?></span>
            <small>(<?= e($roleName) ?>)</small>
        </div>
    </div>

    <!-- Фильтр по общежитию -->
    <?php if (empty($sessionDormId)): ?>
    <div class="glass-card" style="margin-bottom: 20px;">
        <form method="GET" class="form-grid" style="grid-template-columns: 1fr auto auto; align-items: flex-end;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Фильтр по общежитию</label>
                <select name="dormitory_id" class="form-control" onchange="this.form.submit()">
                    <option value="">Все общежития</option>
                    <?php foreach ($dormitories as $d): ?>
                        <option value="<?= $d->id ?>" <?= ($dormitory_id == $d->id) ? 'selected' : '' ?>>
                            <?= e($d->name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">Применить</button>
            <?php if (!empty($dormitory_id)): ?>
                <a href="/notifications" class="btn btn-secondary">Сбросить</a>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>

    <?php
    $currentDormName = '';
    if (!empty($dormitory_id)) {
        foreach ($dormitories as $d) {
            if ($d->id == $dormitory_id) {
                $currentDormName = $d->name;
                break;
            }
        }
    }
    ?>

    <!-- Форма отправки -->
    <div class="glass-card">
        <h3 class="card-title"><i class="fa-solid fa-paper-plane" style="color: var(--primary);"></i> Отправить новое уведомление</h3>
        <form method="POST" class="form-grid" onsubmit="return confirmMassNotification(this);">
            <input type="hidden" name="send_notification" value="1">
            <input type="hidden" name="filter_dormitory_id" value="<?= e($dormitory_id) ?>">

            <div class="form-group" style="flex: 1.2; min-width: 300px;">
                <label class="form-label">Получатель</label>
                <select name="resident_id" required class="form-control" id="resident-select">
                    <option value="">Выберите получателя...</option>

                    <optgroup label="📢 Массовая рассылка (всем жильцам)">
                        <?php if (!empty($sessionDormId)): ?>
                            <option value="all" style="font-weight: 700; color: #166534; background: #f0fdf4;">
                                📢 Всем жильцам (<?= e($_SESSION['dormitory_name'] ?? ('Общежитие №' . $sessionDormId)) ?>)
                            </option>
                        <?php elseif (!empty($dormitory_id)): ?>
                            <option value="all" style="font-weight: 700; color: #166534; background: #f0fdf4;">
                                📢 Всем жильцам (<?= e($currentDormName ?: ('Общежитие №' . $dormitory_id)) ?>)
                            </option>
                            <option value="all_everywhere" style="font-weight: 700; color: #1e40af; background: #eff6ff;">
                                📢 Всем жильцам ВСЕХ общежитий (общая рассылка)
                            </option>
                        <?php else: ?>
                            <option value="all" style="font-weight: 700; color: #166534; background: #f0fdf4;">
                                📢 Всем жильцам ВСЕХ общежитий (общая рассылка)
                            </option>
                            <?php foreach ($dormitories as $d): ?>
                                <option value="dorm_<?= $d->id ?>" style="font-weight: 600; color: #0369a1;">
                                    📢 Всем жильцам: <?= e($d->name) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </optgroup>

                    <optgroup label="👤 Конкретный житель">
                        <?php foreach ($residents as $r): ?>
                        <option value="<?= $r['id'] ?>">
                            [<?= e($r['dormitory_name'] ?? ('Общ. №' . ($r['dormitory_id'] ?? 1))) ?>] <?= e($r['last_name'] . ' ' . $r['first_name'] . (!empty($r['patronymic']) ? ' ' . $r['patronymic'] : '')) ?> (комн. <?= e($r['inidroom']) ?>)
                            <?php
                                $channels = [];
                                if (!empty($r['tg_id'])) $channels[] = 'Telegram';
                                if (!empty($r['vk_id'])) $channels[] = 'VK';
                                if (!empty($r['max_id'])) $channels[] = 'MAX';
                                echo $channels ? ' [' . implode(', ', $channels) . ']' : ' [нет бота]';
                            ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
                <small style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: block;">
                    <i class="fa-solid fa-circle-info"></i> Доступна отправка как отдельному жителю, так и массово всем жильцам.
                </small>
            </div>

            <div class="form-group" style="flex: 2; min-width: 300px;">
                <label class="form-label">Текст уведомления</label>
                <input type="text" name="description" placeholder="Например: Стиральная машина №2 временно на обслуживании" required class="form-control">
                <small style="font-size: 11px; color: var(--text-muted); margin-top: 4px; display: block;">
                    <i class="fa-solid fa-robot"></i> Сообщение будет доставлено через подключённых ботов (Telegram, VK, MAX).
                </small>
            </div>

            <button type="submit" class="btn btn-primary" style="height: 42px; margin-bottom: 24px; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-paper-plane"></i> Отправить
            </button>
        </form>
    </div>

    <script>
    function confirmMassNotification(form) {
        var select = form.querySelector('select[name="resident_id"]');
        if (!select) return true;
        var val = select.value;
        if (val === 'all' || val === 'all_everywhere' || (val && val.indexOf('dorm_') === 0)) {
            var optText = select.options[select.selectedIndex].text.trim();
            return confirm('⚠️ Внимание! Вы собираетесь отправить уведомление:\n' + optText + '\n\nПродолжить отправку?');
        }
        return true;
    }
    </script>

    <!-- ТАБЛИЦА УВЕДОМЛЕНИЙ -->
    <div class="table-container">
        <div class="table-scroll">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 160px; white-space: nowrap;">Дата</th>
                        <th style="width: 180px; white-space: nowrap;">Общежитие</th>
                        <th style="width: 240px;">Житель</th>
                        <th>Сообщение</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notifications as $n): ?>
                    <tr>
                        <td style="white-space: nowrap; font-weight: 500;">
                            <i class="fa-regular fa-clock" style="color: #94a3b8; margin-right: 4px;"></i>
                            <?= !empty($n->create_date) ? date('d.m.Y H:i', strtotime($n->create_date)) : '—' ?>
                        </td>
                        <td style="white-space: nowrap;">
                            <span class="badge badge-dormitory" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 700; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;">
                                <i class="fa-solid fa-building"></i> <?= e($n->dormitory_name ?? ('Общежитие №' . ($n->dormitory_id ?? 1))) ?>
                            </span>
                        </td>
                        <td><?= e($n->resident_name) ?> (комн. <?= e($n->inidroom) ?>)</td>
                        <td><?= e($n->description) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>