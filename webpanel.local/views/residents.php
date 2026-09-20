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

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-user-shield"></i> Статус доступа</label>
                <select name="ban_status" class="form-control">
                    <option value="">Все статусы</option>
                    <option value="active" <?= ($ban_status ?? '') === 'active' ? 'selected' : '' ?>>✅ Только активные</option>
                    <option value="banned" <?= ($ban_status ?? '') === 'banned' ? 'selected' : '' ?>>🚫 Заблокированные</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-star"></i> Рейтинг дисциплины</label>
                <select name="score_status" class="form-control">
                    <option value="">Все уровни</option>
                    <option value="master" <?= ($score_status ?? '') === 'master' ? 'selected' : '' ?>>💎 Мастера (140+)</option>
                    <option value="good" <?= ($score_status ?? '') === 'good' ? 'selected' : '' ?>>🟢 Отличные (90-139)</option>
                    <option value="warning" <?= ($score_status ?? '') === 'warning' ? 'selected' : '' ?>>🟡 Внимание (50-89)</option>
                    <option value="critical" <?= ($score_status ?? '') === 'critical' ? 'selected' : '' ?>>🔴 Штрафники (&lt;50)</option>
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
                <input type="text" name="idcards" value="<?= e($editResident['idcards'] ?? '') ?>" required class="form-control" placeholder="Например: 25Т107 или 244519">
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-bell"></i> Уведомления о свободных слотах</label>
                <select name="notify_unconfirmed" class="form-control">
                    <option value="1" <?= (!isset($editResident) || !empty($editResident['notify_unconfirmed'])) ? 'selected' : '' ?>>🔔 Включены (рассылка при отмене)</option>
                    <option value="0" <?= (isset($editResident) && empty($editResident['notify_unconfirmed'])) ? 'selected' : '' ?>>🔕 Отключены</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label"><i class="fa-solid fa-user-shield"></i> Статус доступа к записи</label>
                <select name="is_banned" class="form-control">
                    <option value="0" <?= (empty($editResident['is_banned'])) ? 'selected' : '' ?>>✅ Активен (запись разрешена)</option>
                    <option value="1" <?= (!empty($editResident['is_banned'])) ? 'selected' : '' ?>>🚫 Заблокирован (запись запрещена)</option>
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
                        <th style="text-align: center; width: 140px;">Рейтинг / Стрик</th>
                        <th style="text-align: center; width: 130px;">Доступ</th>
                        <th style="text-align: center; width: 210px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($residents as $r): 
                        $residentFio = trim($r->last_name . ' ' . $r->first_name . ' ' . ($r->patronymic ?? ''));
                        $scoreVal = $r->score ?? 100;
                        $streakVal = $r->confirm_streak ?? 0;
                        $scoreBadgeStyle = 'background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0;';
                        $scoreIcon = '🟢';
                        if ($scoreVal >= 140) {
                            $scoreBadgeStyle = 'background: linear-gradient(135deg, #e0e7ff, #ede9fe); color: #4338ca; border: 1px solid #c7d2fe; font-weight: 700;';
                            $scoreIcon = '💎';
                        } elseif ($scoreVal < 50) {
                            $scoreBadgeStyle = 'background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; font-weight: 700;';
                            $scoreIcon = '🔴';
                        } elseif ($scoreVal < 90) {
                            $scoreBadgeStyle = 'background-color: #fef9c3; color: #854d0e; border: 1px solid #fef08a; font-weight: 600;';
                            $scoreIcon = '🟡';
                        }
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
                        <td style="text-align: center; white-space: nowrap;">
                            <span class="badge" style="<?= $scoreBadgeStyle ?>" title="Рейтинг дисциплины: <?= $scoreVal ?> / 200">
                                <?= $scoreIcon ?> <?= $scoreVal ?>
                            </span>
                            <?php if ($streakVal > 0): ?>
                                <span class="badge" style="background-color: #ffedd5; color: #c2410c; border: 1px solid #fed7aa; margin-left: 2px;" title="Серия успешных подтверждений: <?= $streakVal ?>">
                                    🔥 <?= $streakVal ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if (!empty($r->is_banned)): ?>
                                <span class="badge" style="background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; font-weight: 600;" title="Заблокирован (запись запрещена)">
                                    <i class="fa-solid fa-ban"></i> Заблокирован
                                </span>
                            <?php else: ?>
                                <span class="badge" style="background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; font-weight: 600;" title="Активен (запись разрешена)">
                                    <i class="fa-solid fa-check"></i> Активен
                                </span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center; white-space: nowrap;">
                            <a href="/residents?edit=<?= $r->id ?>" class="btn" style="background-color: #2563eb; border-color: #1d4ed8; color: #fff; padding: 6px 10px; font-size: 13px; margin-right: 3px; border-radius: 6px;" title="Редактировать жителя">
                                <i class="fa-solid fa-pen"></i>
                            </a>

                            <button type="button" class="btn" onclick="openScoreHistoryModal(<?= $r->id ?>, '<?= e(addslashes($residentFio)) ?>', <?= $scoreVal ?>, <?= $streakVal ?>)" style="background-color: #8b5cf6; border-color: #7c3aed; color: #fff; padding: 6px 10px; font-size: 13px; margin-right: 3px; border-radius: 6px;" title="История баллов">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </button>

                            <button type="button" class="btn" onclick="openAdjustScoreModal(<?= $r->id ?>, '<?= e(addslashes($residentFio)) ?>', <?= $scoreVal ?>)" style="background-color: #f59e0b; border-color: #d97706; color: #fff; padding: 6px 10px; font-size: 13px; margin-right: 3px; border-radius: 6px;" title="Изменить баллы">
                                <i class="fa-solid fa-sliders"></i>
                            </button>
                            
                            <form method="POST" style="display:inline; margin-right:3px;" onsubmit="return confirm('<?= !empty($r->is_banned) ? ('Разблокировать жителя ' . e($residentFio) . '?') : ('Заблокировать жителя ' . e($residentFio) . '? Он не сможет записываться на стирку.') ?>')">
                                <input type="hidden" name="toggle_ban_id" value="<?= $r->id ?>">
                                <?php if (!empty($r->is_banned)): ?>
                                    <button type="submit" class="btn btn-success" style="padding:6px 9px; font-size:13px;" title="Разблокировать доступ"><i class="fa-solid fa-unlock"></i></button>
                                <?php else: ?>
                                    <button type="submit" class="btn" style="padding:6px 9px; font-size:13px; background-color: #64748b; border-color: #475569; color: #fff;" title="Заблокировать запись"><i class="fa-solid fa-ban"></i></button>
                                <?php endif; ?>
                            </form>

                            <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить жителя <?= e($residentFio) ?>?')">
                                <input type="hidden" name="delete_id" value="<?= $r->id ?>">
                                <button type="submit" class="btn btn-danger" style="padding: 6px 10px; font-size: 13px; border-radius: 6px;" title="Удалить жителя">
                                    <i class="fa-solid fa-xmark"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Модальное окно истории баллов -->
<div id="scoreHistoryModal" class="custom-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:#1e293b; border:1px solid rgba(255,255,255,0.1); border-radius:12px; width:95%; max-width:650px; max-height:85vh; display:flex; flex-direction:column; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
        <div style="padding:16px 20px; border-bottom:1px solid rgba(255,255,255,0.1); display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:18px; color:#fff; display:flex; align-items:center; gap:8px;">
                <i class="fa-solid fa-clock-rotate-left" style="color:#a78bfa;"></i>
                История баллов: <span id="shModalName" style="color:#38bdf8;"></span>
            </h3>
            <button type="button" onclick="closeScoreHistoryModal()" style="background:transparent; border:none; color:#94a3b8; font-size:20px; cursor:pointer;">✕</button>
        </div>
        <div style="padding:12px 20px; background:rgba(255,255,255,0.03); border-bottom:1px solid rgba(255,255,255,0.05); display:flex; gap:16px; font-size:14px;">
            <div>Текущий баланс: <b id="shModalScore" style="color:#34d399;"></b></div>
            <div>Серия (стрик): <b id="shModalStreak" style="color:#fb923c;"></b></div>
        </div>
        <div id="shModalContent" style="padding:20px; overflow-y:auto; flex:1; min-height:180px;">
            <div style="text-align:center; color:#94a3b8; padding:20px;">Загрузка истории...</div>
        </div>
        <div style="padding:12px 20px; border-top:1px solid rgba(255,255,255,0.1); text-align:right;">
            <button type="button" class="btn btn-secondary" onclick="closeScoreHistoryModal()">Закрыть</button>
        </div>
    </div>
</div>

<!-- Модальное окно ручной корректировки баллов -->
<div id="adjustScoreModal" class="custom-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:#1e293b; border:1px solid rgba(255,255,255,0.1); border-radius:12px; width:95%; max-width:480px; overflow:hidden; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);">
        <form method="POST" action="/residents">
            <input type="hidden" name="adjust_score" value="1">
            <input type="hidden" name="resident_id" id="adjModalResidentId" value="">
            
            <div style="padding:16px 20px; border-bottom:1px solid rgba(255,255,255,0.1); display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:18px; color:#fff; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-sliders" style="color:#fbbf24;"></i> Корректировка баллов
                </h3>
                <button type="button" onclick="closeAdjustScoreModal()" style="background:transparent; border:none; color:#94a3b8; font-size:20px; cursor:pointer;">✕</button>
            </div>
            
            <div style="padding:20px;">
                <div style="margin-bottom:14px; font-size:14px; color:#cbd5e1;">
                    Житель: <b id="adjModalName" style="color:#38bdf8;"></b><br>
                    Текущие баллы: <b id="adjModalScore" style="color:#34d399;"></b> / 200
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label">Изменение баллов (+ / -)</label>
                    <input type="number" name="score_delta" class="form-control" placeholder="Например: 10 или -15" required style="font-size:16px; font-weight:700;">
                    <small style="color:#94a3b8; display:block; margin-top:4px;">Положительное число добавит баллы, отрицательное спишет.</small>
                </div>

                <div class="form-group" style="margin-bottom:14px;">
                    <label class="form-label">Причина корректировки</label>
                    <select name="score_reason" class="form-control">
                        <option value="ADMIN_ADJUSTMENT">Решение коменданта / администрации</option>
                        <option value="BONUS_COMMUNITY">Поощрение за помощь в общежитии (+)</option>
                        <option value="EXCUSED_ABSENCE">Уважительная причина (болезнь / справка)</option>
                        <option value="PENALTY_VIOLATION">Штраф за нарушение правил прачечной (-)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:10px;">
                    <label class="form-label">Комментарий / Детали</label>
                    <input type="text" name="score_details" class="form-control" placeholder="Краткое пояснение для жителя...">
                </div>
            </div>

            <div style="padding:14px 20px; border-top:1px solid rgba(255,255,255,0.1); text-align:right; display:flex; justify-content:flex-end; gap:10px;">
                <button type="button" class="btn btn-secondary" onclick="closeAdjustScoreModal()">Отмена</button>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Применить</button>
            </div>
        </form>
    </div>
</div>

<script>
function openScoreHistoryModal(residentId, name, score, streak) {
    document.getElementById('shModalName').innerText = name;
    document.getElementById('shModalScore').innerText = score + ' / 200';
    document.getElementById('shModalStreak').innerText = '🔥 ' + streak;
    document.getElementById('shModalContent').innerHTML = '<div style="text-align:center; color:#94a3b8; padding:20px;">Загрузка истории...</div>';
    
    var modal = document.getElementById('scoreHistoryModal');
    modal.style.display = 'flex';

    fetch('/residents?score_history=' + residentId)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (!data.success || !data.logs || data.logs.length === 0) {
                document.getElementById('shModalContent').innerHTML = '<div style="text-align:center; color:#94a3b8; padding:20px;">История начислений пока пуста.</div>';
                return;
            }
            var html = '<table class="custom-table" style="font-size:13px; width:100%;">';
            html += '<thead><tr><th>Дата</th><th>Дельта</th><th>Баланс</th><th>Причина</th><th>Описание</th></tr></thead><tbody>';
            data.logs.forEach(function(l) {
                var sign = l.delta > 0 ? '+' : '';
                var deltaColor = l.delta > 0 ? '#34d399' : (l.delta < 0 ? '#f87171' : '#94a3b8');
                var dt = new Date(l.created_at);
                var dtStr = dt.toLocaleString('ru-RU', {day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit'});
                html += '<tr>' +
                    '<td style="white-space:nowrap; color:#94a3b8;">' + dtStr + '</td>' +
                    '<td style="font-weight:700; color:' + deltaColor + ';">' + sign + l.delta + '</td>' +
                    '<td style="font-weight:600;">' + l.score_after + '</td>' +
                    '<td><span class="badge badge-secondary" style="font-size:11px;">' + (l.reason || '—') + '</span></td>' +
                    '<td>' + (l.details || '—') + '</td>' +
                '</tr>';
            });
            html += '</tbody></table>';
            document.getElementById('shModalContent').innerHTML = html;
        })
        .catch(function(err) {
            document.getElementById('shModalContent').innerHTML = '<div style="text-align:center; color:#f87171; padding:20px;">Ошибка загрузки истории баллов.</div>';
        });
}

function closeScoreHistoryModal() {
    document.getElementById('scoreHistoryModal').style.display = 'none';
}

function openAdjustScoreModal(residentId, name, score) {
    document.getElementById('adjModalResidentId').value = residentId;
    document.getElementById('adjModalName').innerText = name;
    document.getElementById('adjModalScore').innerText = score;
    document.getElementById('adjustScoreModal').style.display = 'flex';
}

function closeAdjustScoreModal() {
    document.getElementById('adjustScoreModal').style.display = 'none';
}

window.addEventListener('click', function(e) {
    var shModal = document.getElementById('scoreHistoryModal');
    var adjModal = document.getElementById('adjustScoreModal');
    if (e.target === shModal) shModal.style.display = 'none';
    if (e.target === adjModal) adjModal.style.display = 'none';
});
</script>