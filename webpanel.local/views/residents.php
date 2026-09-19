<?php
// views/residents.php — Управление жителями с поддержкой зачёток и статуса ботов
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
        <h1 class="page-title"><i class="fa-solid fa-user-graduate"></i> Пользователи (Жители)</h1>
        <div class="user-badge">
            <i class="fa-solid fa-user"></i> <span class="user-badge-name"><?= e($_SESSION['username']) ?></span>
            <small>(<?= e($roleName) ?>)</small>
        </div>
    </div>

    <!-- Форма фильтров жителей -->
    <div class="glass-card" style="margin-bottom: 20px;">
        <form method="GET" action="/residents" class="form-grid" id="residentsFilterForm">
            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-building"></i> Общежитие</label>
                <?php if (!empty($sessionDormId)): ?>
                    <input type="hidden" name="dormitory_id" value="<?= $sessionDormId ?>">
                    <div class="form-control" style="background: rgba(255,255,255,0.05); color: #38bdf8; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-lock" style="font-size: 12px; opacity: 0.7;"></i>
                        <?= e($_SESSION['dormitory_name'] ?? ('Общежитие №' . $sessionDormId)) ?>
                    </div>
                <?php else: ?>
                    <select name="dormitory_id" class="form-control">
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
                <label class="form-label"><i class="fa-solid fa-user"></i> ФИО жителя</label>
                <input type="text" name="fio" value="<?= e($fio ?? '') ?>" class="form-control" placeholder="Поиск по ФИО...">
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-door-closed"></i> Комната</label>
                <input type="text" name="room" value="<?= e($room ?? '') ?>" class="form-control" placeholder="101">
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-id-card"></i> Номер зачётки / ID</label>
                <input type="text" name="idcard" value="<?= e($idcard ?? '') ?>" class="form-control" placeholder="Номер зачётки...">
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-robot"></i> Подключение ботов</label>
                <select name="bot_status" class="form-control">
                    <option value="">Все статусы</option>
                    <option value="connected" <?= ($bot_status ?? '') === 'connected' ? 'selected' : '' ?>>🤖 Подключён бот</option>
                    <option value="not_connected" <?= ($bot_status ?? '') === 'not_connected' ? 'selected' : '' ?>>❌ Не подключён</option>
                    <option value="tg" <?= ($bot_status ?? '') === 'tg' ? 'selected' : '' ?>>Telegram (TG)</option>
                    <option value="vk" <?= ($bot_status ?? '') === 'vk' ? 'selected' : '' ?>>ВКонтакте (VK)</option>
                    <option value="max" <?= ($bot_status ?? '') === 'max' ? 'selected' : '' ?>>MAX Bot</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-bell"></i> Свободные слоты</label>
                <select name="notify_status" class="form-control">
                    <option value="">Все</option>
                    <option value="1" <?= ($notify_status ?? '') === '1' ? 'selected' : '' ?>>🔔 Включены</option>
                    <option value="0" <?= ($notify_status ?? '') === '0' ? 'selected' : '' ?>>🔕 Отключены</option>
                </select>
            </div>

            <div style="display: flex; gap: 8px; align-items: flex-end;">
                <button type="submit" class="btn btn-primary" style="flex: 1;">
                    <i class="fa-solid fa-filter"></i> Применить
                </button>
                <a href="/residents" class="btn btn-secondary" title="Сбросить фильтры" style="padding: 10px 14px;">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Форма добавления / редактирования -->
    <div class="glass-card">
        <h3 class="card-title"><?= $editResident ? 'Редактировать жителя' : 'Добавить нового жителя' ?></h3>
        <form method="POST" class="form-grid">
            <?php if ($editResident): ?>
                <input type="hidden" name="id" value="<?= $editResident['id'] ?>">
                <input type="hidden" name="edit_resident" value="1">
            <?php else: ?>
                <input type="hidden" name="add_resident" value="1">
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
                            <option value="<?= $d->id ?>" <?= (($editResident['dormitory_id'] ?? $dormitory_id) == $d->id) ? 'selected' : '' ?>>
                                <?= e($d->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label">Фамилия</label>
                <input type="text" name="last_name" value="<?= e($editResident['last_name'] ?? '') ?>" required class="form-control" placeholder="Иванов">
            </div>

            <div class="form-group">
                <label class="form-label">Имя</label>
                <input type="text" name="first_name" value="<?= e($editResident['first_name'] ?? '') ?>" required class="form-control" placeholder="Иван">
            </div>

            <div class="form-group">
                <label class="form-label">Отчество</label>
                <input type="text" name="patronymic" value="<?= e($editResident['patronymic'] ?? '') ?>" class="form-control" placeholder="Иванович">
            </div>

            <div class="form-group">
                <label class="form-label">Комната</label>
                <input type="number" name="inidroom" value="<?= e($editResident['inidroom'] ?? '') ?>" required class="form-control" placeholder="101">
            </div>

            <div class="form-group">
                <label class="form-label">Номер зачётки / ID карты</label>
                <input type="number" name="idcards" value="<?= e($editResident['idcards'] ?? '') ?>" required class="form-control" placeholder="123456">
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-bell"></i> Уведомления о свободных слотах</label>
                <select name="notify_unconfirmed" class="form-control">
                    <option value="1" <?= (!isset($editResident) || !empty($editResident['notify_unconfirmed'])) ? 'selected' : '' ?>>🔔 Включены (рассылка при отмене)</option>
                    <option value="0" <?= (isset($editResident) && empty($editResident['notify_unconfirmed'])) ? 'selected' : '' ?>>🔕 Отключены</option>
                </select>
            </div>

            <div style="display: flex; gap: 10px; align-items: flex-end;">
                <button type="submit" class="btn btn-primary">
                    <?= $editResident ? 'Сохранить изменения' : 'Добавить жителя' ?>
                </button>

                <?php if ($editResident): ?>
                    <a href="/residents" class="btn btn-secondary">Отмена</a>
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
                        <th style="width: 170px; white-space: nowrap;">Общежитие</th>
                        <th>ФИО жителя</th>
                        <th style="text-align: center; width: 100px;">Комната</th>
                        <th style="text-align: center; width: 130px;">Зачётка/Карта</th>
                        <th style="text-align: center; width: 140px;">Боты</th>
                        <th style="text-align: center; width: 140px;">Свободные слоты</th>
                        <th style="text-align: center; width: 220px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($residents as $r): 
                        $residentFio = trim($r->last_name . ' ' . $r->first_name . ' ' . ($r->patronymic ?? ''));
                    ?>
                    <tr>
                        <td><?= $r->id ?></td>
                        <td style="white-space: nowrap;">
                            <span class="badge badge-dormitory" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-weight: 700; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px;">
                                <i class="fa-solid fa-building"></i> <?= e($r->dormitory_name ?? ('Общежитие №' . ($r->dormitory_id ?? 1))) ?>
                            </span>
                        </td>
                        <td style="font-weight: 600;"><?= e($residentFio) ?></td>
                        <td style="text-align: center; font-weight: 600;"><?= e($r->inidroom) ?></td>
                        <td style="text-align: center; font-family: monospace; font-size: 14px;"><?= e($r->idcards ?? '—') ?></td>
                        <td style="text-align: center;">
                            <?php if (!empty($r->tg_id)): ?>
                                <span class="badge badge-primary" title="TG ID: <?= e($r->tg_id) ?>" style="margin-right: 4px;">
                                    <i class="fa-brands fa-telegram"></i> TG
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($r->vk_id)): ?>
                                <span class="badge badge-info" title="VK ID: <?= e($r->vk_id) ?>" style="margin-right: 4px;">
                                    <i class="fa-brands fa-vk"></i> VK
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($r->max_id)): ?>
                                <span class="badge" style="background: linear-gradient(135deg, #FF6B00, #FF8800); color: #fff;" title="MAX ID: <?= e($r->max_id) ?>">
                                    <i class="fa-solid fa-comment-dots"></i> MAX
                                </span>
                            <?php endif; ?>
                            <?php if (empty($r->tg_id) && empty($r->vk_id) && empty($r->max_id)): ?>
                                <span class="badge badge-secondary" style="opacity: 0.6;">Не подключён</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if (!empty($r->notify_unconfirmed)): ?>
                                <span class="badge" style="background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;" title="Получает уведомления о свободных слотах">
                                    <i class="fa-solid fa-bell"></i> Вкл
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background-color: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; opacity: 0.7;" title="Отключил уведомления">
                                    <i class="fa-solid fa-bell-slash"></i> Выкл
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <a href="/residents?edit=<?= $r->id ?>" class="btn btn-primary" style="padding:6px 14px; font-size:13px; margin-right:6px;">Редактировать</a>
                            
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить жителя <?= e($residentFio) ?>?')">
                                <input type="hidden" name="delete_id" value="<?= $r->id ?>">
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