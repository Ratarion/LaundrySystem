<?php
// views/machines.php — Редизайн управления машинами
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
        <h1 class="page-title"><i class="fa-solid fa-screwdriver-wrench"></i> Техника</h1>
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
                <a href="/machines" class="btn btn-secondary">Сбросить</a>
            <?php endif; ?>
        </form>
    </div>
    <?php endif; ?>

    <!-- Форма добавления / редактирования -->
    <div class="glass-card">
        <h3 class="card-title"><?= $editMachine ? 'Редактировать машину' : 'Добавить новую машину' ?></h3>
        <form method="POST" class="form-grid">
            <?php if ($editMachine): ?>
                <input type="hidden" name="id" value="<?= $editMachine['id'] ?>">
                <input type="hidden" name="edit_machine" value="1">
            <?php else: ?>
                <input type="hidden" name="add_machine" value="1">
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label">Общежитие</label>
                <?php if (!empty($sessionDormId)): ?>
                    <input type="hidden" name="dormitory_id" value="<?= $sessionDormId ?>">
                    <div class="form-control" style="background: rgba(255,255,255,0.05); color: #38bdf8; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-lock" style="font-size: 12px; opacity: 0.7;"></i>
                        <?= e($_SESSION['dormitory_name'] ?? ('Общежитие №' . $sessionDormId)) ?>
                    </div>
                <?php else: ?>
                    <select name="dormitory_id" required class="form-control">
                        <?php foreach ($dormitories as $d): ?>
                            <option value="<?= $d->id ?>" <?= (($editMachine['dormitory_id'] ?? $dormitory_id) == $d->id) ? 'selected' : '' ?>>
                                <?= e($d->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Тип машины</label>
                <select name="type_machine" required class="form-control">
                    <option value="Стиральная" <?= ($editMachine && $editMachine['type_machine']==='Стиральная')?'selected':'' ?>>Стиральная</option>
                    <option value="Сушильная"  <?= ($editMachine && $editMachine['type_machine']==='Сушильная') ?'selected':'' ?>>Сушильная</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Номер / Название</label>
                <input type="text" name="number_machine" value="<?= e($editMachine['number_machine'] ?? '') ?>" 
                       placeholder="Например: #5 или 3 этаж" required class="form-control">
            </div>

            <div class="form-group">
                <label class="form-label">Статус</label>
                <select name="status" required class="form-control">
                    <option value="1" <?= ($editMachine && $editMachine['status']==1)?'selected':'' ?>>Работает</option>
                    <option value="0" <?= ($editMachine && $editMachine['status']==0)?'selected':'' ?>>Отключена</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">
                <?= $editMachine ? 'Сохранить' : 'Добавить машину' ?>
            </button>

            <?php if ($editMachine): ?>
                <a href="/machines" class="btn btn-secondary">Отмена</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ТАБЛИЦА -->
    <div class="table-container">
        <div class="table-scroll">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th style="width: 170px; white-space: nowrap;">Общежитие</th>
                        <th>Тип машины</th>
                        <th>Номер / Название</th>
                        <th style="text-align: center; width: 140px;">Статус</th>
                        <th style="text-align: center; width: 280px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($machines as $m): 
                        $isActive = $m->status == 1;
                    ?>
                    <tr>
                        <td><?= $m->id ?></td>
                        <td style="white-space: nowrap;">
                            <span class="badge badge-dormitory" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 700; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;">
                                <i class="fa-solid fa-building"></i> <?= e($m->dormitory_name ?? ('Общежитие №' . ($m->dormitory_id ?? 1))) ?>
                            </span>
                        </td>
                        <td><?= e($m->type_machine) ?></td>
                        <td><?= e($m->number_machine) ?></td>
                        
                        <!-- Красивый тоггл -->
                        <td style="text-align: center;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="toggle_id" value="<?= $m->id ?>">
                                <label class="switch">
                                    <input type="checkbox" <?= $isActive ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <span class="slider"></span>
                                </label>
                            </form>
                        </td>

                        <td style="text-align: center;">
                            <a href="/machines?edit=<?= $m->id ?>" class="btn btn-primary" style="padding:6px 14px; font-size:13px; margin-right:6px;">Редактировать</a>
                            
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить машину?')">
                                <input type="hidden" name="delete_id" value="<?= $m->id ?>">
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