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

    <!-- Форма отправки -->
    <div class="glass-card">
        <h3 class="card-title">Отправить новое уведомление</h3>
        <form method="POST" class="form-grid">
            <input type="hidden" name="send_notification" value="1">

            <div class="form-group" style="flex: 1; min-width: 280px;">
                <label class="form-label">Житель</label>
                <select name="resident_id" required class="form-control">
                    <option value="">Выберите жителя...</option>
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
                </select>
            </div>

            <div class="form-group" style="flex: 2; min-width: 300px;">
                <label class="form-label">Текст уведомления</label>
                <input type="text" name="description" placeholder="Например: Стиральная машина не включается" required class="form-control">
            </div>

            <button type="submit" class="btn btn-primary">
                Отправить
            </button>
        </form>
    </div>

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