<?php
// views/dormitories.php — Управление общежитиями студгородка
?>
<div class="content-area">
    <?php if (isset($success)): ?>
        <div id="success-toast" class="toast-notification">
            <div class="toast-content"><i class="fa-solid fa-circle-check" style="color: var(--success);"></i> <?= e($success) ?></div>
            <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div id="error-toast" class="toast-notification" style="border-left-color: var(--danger);">
            <div class="toast-content"><i class="fa-solid fa-triangle-exclamation" style="color: var(--danger);"></i> <?= e($error) ?></div>
            <button class="toast-close" onclick="this.parentElement.remove()">✕</button>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h1 class="page-title"><i class="fa-solid fa-hotel"></i> Общежития студгородка</h1>
        <div class="user-badge">
            <i class="fa-solid fa-user"></i> <span class="user-badge-name"><?= e($_SESSION['username']) ?></span>
            <small>(<?= e($roleName) ?>)</small>
        </div>
    </div>

    <!-- Форма добавления / редактирования -->
    <div class="glass-card">
        <h3 class="card-title"><?= $editDorm ? 'Редактировать общежитие' : 'Добавить новое общежитие' ?></h3>
        <form method="POST" class="form-grid">
            <?php if ($editDorm): ?>
                <input type="hidden" name="id" value="<?= $editDorm['id'] ?>">
                <input type="hidden" name="edit_dormitory" value="1">
            <?php else: ?>
                <input type="hidden" name="add_dormitory" value="1">
            <?php endif; ?>

            <div class="form-group" style="flex: 1; min-width: 120px;">
                <label class="form-label">Номер корпуса</label>
                <input type="number" name="number" value="<?= e($editDorm['number'] ?? '') ?>" required class="form-control" placeholder="1">
            </div>

            <div class="form-group" style="flex: 2; min-width: 220px;">
                <label class="form-label">Название</label>
                <input type="text" name="name" value="<?= e($editDorm['name'] ?? '') ?>" required class="form-control" placeholder="Общежитие №1">
            </div>

            <div class="form-group" style="flex: 3; min-width: 280px;">
                <label class="form-label">Адрес</label>
                <input type="text" name="address" value="<?= e($editDorm['address'] ?? '') ?>" class="form-control" placeholder="ул. Мичурина, 57">
            </div>

            <div style="display: flex; gap: 10px; align-items: flex-end;">
                <button type="submit" class="btn btn-primary">
                    <?= $editDorm ? 'Сохранить' : 'Добавить корпус' ?>
                </button>

                <?php if ($editDorm): ?>
                    <a href="/dormitories" class="btn btn-secondary">Отмена</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ТАБЛИЦА -->
    <div class="table-container">
        <div class="table-scroll">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th style="width: 110px; text-align: center;">Корпус №</th>
                        <th>Название общежития</th>
                        <th>Адрес</th>
                        <th style="text-align: center; width: 220px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dormitories as $d): ?>
                    <tr>
                        <td><?= $d->id ?></td>
                        <td style="text-align: center; font-weight: 700; font-size: 16px;">
                            <span class="badge badge-info">№<?= $d->number ?></span>
                        </td>
                        <td style="font-weight: 600;"><?= e($d->name) ?></td>
                        <td><?= e($d->address ?? '—') ?></td>
                        <td style="text-align: center;">
                            <a href="/dormitories?edit=<?= $d->id ?>" class="btn btn-primary" style="padding:6px 14px; font-size:13px; margin-right:6px;">Редактировать</a>
                            
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить <?= e($d->name) ?>?')">
                                <input type="hidden" name="delete_id" value="<?= $d->id ?>">
                                <button type="submit" class="btn btn-danger" style="padding:6px 14px; font-size:13px;">Удалить</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
