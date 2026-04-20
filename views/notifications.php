<?php
// views/notifications.php
?>
<div style="flex: 1; padding: 20px;">

    <?php if (isset($success)): ?>
        <div id="success-toast" class="toast-notification">
            <div class="toast-content">✅ <?= e($success) ?></div>
            <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>🛎️ Уведомления</h1>
        <span style="color: #4caf50; font-weight: 600;">
            👤 <?= e($_SESSION['username']) ?> <small>(<?= e($roleName) ?>)</small>
        </span>
    </div>

    <!-- Форма отправки -->
    <div style="background: #1f1f1f; padding: 25px; border-radius: 12px; margin-bottom: 30px;">
        <h3>Отправить новое уведомление</h3>
        <form method="POST" style="display:flex; gap:15px; align-items:end; flex-wrap:wrap;">
            <input type="hidden" name="send_notification" value="1">

            <label style="flex:1;">
                Житель<br>
                <select name="resident_id" required style="width:100%; padding:12px; border-radius:8px; background:#2a2a2a; color:#fff; border:none;">
                    <option value="">Выберите жителя...</option>
                    <?php foreach ($residents as $r): ?>
                    <option value="<?= $r['id'] ?>">
                        <?= e($r['last_name'] . ' ' . $r['first_name']) ?> (комн. <?= e($r['inidroom']) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label style="flex:2;">
                Текст уведомления<br>
                <input type="text" name="description" placeholder="Например: Стиральная машина не включается" required
                       style="width:100%; padding:12px; border-radius:8px; background:#2a2a2a; color:#fff; border:none;">
            </label>

            <button type="submit" class="btn btn-primary" style="padding:12px 32px;">
                Отправить
            </button>
        </form>
    </div>

    <!-- ТАБЛИЦА УВЕДОМЛЕНИЙ -->
    <div style="max-height: 60vh; overflow-y: auto; border: 2px solid #333; border-radius: 12px; background: #1a1a1a;">
        <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
            <thead style="position: sticky; top: 0; z-index: 10; background: #1f1f1f;">
                <tr>
                    <th style="padding:14px 12px; text-align:left;">Дата</th>
                    <th style="padding:14px 12px; text-align:left;">Житель</th>
                    <th style="padding:14px 12px; text-align:left;">Сообщение</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $n): ?>
                <tr>
                    <td style="padding:12px;"><?= e($n->create_date) ?></td>
                    <td style="padding:12px;"><?= e($n->resident_name) ?> (<?= e($n->inidroom) ?>)</td>
                    <td style="padding:12px;"><?= e($n->description) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>