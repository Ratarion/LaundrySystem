<?php
// views/hall_of_fame.php — Зал славы прачечной КузГТУ
?>
<div class="content-area">

    <!-- ШАПКА СТРАНИЦЫ -->
    <div class="page-header" style="flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
        <div>
            <h1 class="page-title" style="display: flex; align-items: center; gap: 10px; font-size: 26px; font-weight: 800;">
                <i class="fa-solid fa-trophy" style="color: #f59e0b;"></i> Зал славы прачечной КузГТУ
            </h1>
            <p style="color: #94a3b8; font-size: 14px; margin: 4px 0 0 0;">
                Доска почёта самых дисциплинированных жителей и антирейтинг нарушителей
            </p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="/booking" class="btn btn-secondary" style="padding: 10px 18px; font-weight: 600;">
                <i class="fa-solid fa-calendar-days"></i> Бронирования
            </a>
            <?php if (!$isLoggedIn): ?>
                <a href="/login" class="btn btn-primary" style="padding: 10px 18px;">
                    <i class="fa-solid fa-key"></i> Вход в панель
                </a>
            <?php else: ?>
                <div class="user-badge" style="background: rgba(255,255,255,0.05); padding: 8px 14px; border-radius: 10px; font-size: 13px;">
                    <i class="fa-solid fa-user"></i> <span><?= e($_SESSION['username']) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ФИЛЬТР ПО ОБЩЕЖИТИЯМ -->
    <div class="glass-card" style="margin-bottom: 24px; padding: 16px 20px;">
        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
            <span style="font-size: 14px; color: #94a3b8; font-weight: 600; margin-right: 4px;">
                <i class="fa-solid fa-building"></i> Корпус:
            </span>
            <a href="/hall-of-fame<?= !empty($myCard) ? '?me=' . $myCard['id'] : '' ?>" 
               class="btn <?= empty($dormitory_id) ? 'btn-primary' : 'btn-secondary' ?>" 
               style="padding: 7px 16px; font-size: 13px; font-weight: 700; border-radius: 9999px;">
                🏛 Все общежития (<?= $totalResidents ?>)
            </a>
            <?php foreach ($dormitories as $d): 
                $isSelected = ($dormitory_id === (int)$d->id);
                $dLink = '/hall-of-fame?dormitory_id=' . $d->id . (!empty($myCard) ? '&me=' . $myCard['id'] : '');
            ?>
                <a href="<?= $dLink ?>" 
                   class="btn <?= $isSelected ? 'btn-primary' : 'btn-secondary' ?>" 
                   style="padding: 7px 16px; font-size: 13px; font-weight: 700; border-radius: 9999px;">
                    🏢 <?= e($d->name) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ВЫШЕ ТОП-1: БЛОК "МОЁ МЕСТО В РЕЙТИНГЕ" -->
    <div class="glass-card" style="margin-bottom: 28px; background: linear-gradient(135deg, rgba(30, 41, 59, 0.85) 0%, rgba(15, 23, 42, 0.95) 100%); border: 2px solid <?= !empty($myCard) ? '#38bdf8' : 'rgba(255,255,255,0.1)' ?>; box-shadow: 0 10px 30px -10px rgba(56, 189, 248, 0.25);">
        
        <?php if (!empty($myCard)): 
            $pos = (int)$myCard['rank_pos'];
            $posBadge = "#{$pos}";
            $posColor = '#38bdf8';
            if ($pos === 1) { $posBadge = "🥇 1 место"; $posColor = '#fbbf24'; }
            elseif ($pos === 2) { $posBadge = "🥈 2 место"; $posColor = '#cbd5e1'; }
            elseif ($pos === 3) { $posBadge = "🥉 3 место"; $posColor = '#f97316'; }

            $mScore = (int)$myCard['score'];
            $mStreak = (int)$myCard['confirm_streak'];
            $rankTitle = "Дисциплинированный";
            $rankBadge = "🟢";
            if ($mScore >= 140) { $rankTitle = "Мастер стирки"; $rankBadge = "💎"; }
            elseif ($mScore < 20) { $rankTitle = "Ограничен"; $rankBadge = "🔴"; }
            elseif ($mScore < 50) { $rankTitle = "Критический"; $rankBadge = "🟠"; }
            elseif ($mScore < 90) { $rankTitle = "Внимание"; $rankBadge = "🟡"; }
        ?>
            <!-- Карточка определенного пользователя -->
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="font-size: 32px; font-weight: 800; color: <?= $posColor ?>; background: rgba(255,255,255,0.05); padding: 12px 18px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); min-width: 90px; text-align: center;">
                        <?= $posBadge ?>
                    </div>
                    <div>
                        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #38bdf8; font-weight: 700; margin-bottom: 2px;">
                            <i class="fa-solid fa-user-check"></i> Ваша позиция в Зале славы:
                        </div>
                        <div style="font-size: 20px; font-weight: 800; color: #fff;">
                            <?= e(trim($myCard['last_name'] . ' ' . $myCard['first_name'] . ' ' . ($myCard['patronymic'] ?? ''))) ?>
                        </div>
                        <div style="font-size: 13px; color: #94a3b8; margin-top: 2px;">
                            <?= e($myCard['dormitory_name'] ?? 'КузГТУ') ?>, комната <?= e($myCard['inidroom']) ?> 
                            • <span style="color: #cbd5e1;">Место <?= $pos ?> из <?= $totalResidents ?></span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); padding: 8px 16px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase;">Баллы</div>
                        <div style="font-size: 18px; font-weight: 800; color: #34d399;"><?= $mScore ?> б.</div>
                    </div>

                    <?php if ($mStreak > 0): ?>
                    <div style="background: rgba(225, 29, 72, 0.15); border: 1px solid rgba(225, 29, 72, 0.4); padding: 8px 16px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase;">Стрик</div>
                        <div style="font-size: 18px; font-weight: 800; color: #fb7185;">🔥 <?= $mStreak ?></div>
                    </div>
                    <?php endif; ?>

                    <div style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); padding: 8px 16px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 11px; color: #94a3b8; text-transform: uppercase;">Ранг</div>
                        <div style="font-size: 14px; font-weight: 700; color: #e2e8f0; margin-top: 3px;"><?= $rankBadge ?> <?= $rankTitle ?></div>
                    </div>

                    <a href="/hall-of-fame<?= $dormitory_id ? '?dormitory_id='.$dormitory_id : '' ?>" class="btn btn-secondary" title="Сбросить выбор" style="padding: 10px 12px;">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- Форма поиска своего места -->
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;">
                <div>
                    <h3 style="margin: 0 0 4px 0; font-size: 17px; color: #fff; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-magnifying-glass-chart" style="color: #38bdf8;"></i> Узнать своё место в рейтинге
                    </h3>
                    <p style="margin: 0; font-size: 13px; color: #94a3b8;">
                        Введите свою фамилию или номер зачётной книжки, чтобы увидеть свою карточку и позицию:
                    </p>
                </div>

                <form method="GET" action="/hall-of-fame" style="display: flex; gap: 8px; flex: 1; max-width: 460px; min-width: 260px;">
                    <?php if ($dormitory_id): ?>
                        <input type="hidden" name="dormitory_id" value="<?= $dormitory_id ?>">
                    <?php endif; ?>
                    <input type="text" 
                           name="fio" 
                           value="<?= e($searchQuery) ?>" 
                           placeholder="Например: Иванов или 22101..." 
                           required 
                           class="form-control" 
                           style="background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.15); color: #fff;">
                    <button type="submit" class="btn btn-primary" style="white-space: nowrap; padding: 10px 20px;">
                        <i class="fa-solid fa-search"></i> Найти себя
                    </button>
                </form>
            </div>
            <?php if (!empty($searchQuery)): ?>
                <div style="margin-top: 12px; color: #f87171; font-size: 13px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> Житель по запросу "<?= e($searchQuery) ?>" не найден в выбранном общежитии.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ТОП 3 ЛИДЕРОВ (ЗОЛОТОЙ ПОДИУМ) -->
    <div style="margin-bottom: 32px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
            <h2 style="margin: 0; font-size: 20px; font-weight: 800; color: #fbbf24; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-crown"></i> Топ-3 лидеров дисциплины
            </h2>
            <span style="font-size: 12px; background: rgba(251, 191, 36, 0.15); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.3); padding: 3px 10px; border-radius: 9999px; font-weight: 700;">
                Лидеры прачечной
            </span>
        </div>

        <?php if (empty($top3)): ?>
            <div class="glass-card" style="text-align: center; padding: 32px; color: #94a3b8;">
                В этом общежитии пока нет подтверждённых жителей.
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 18px;">
                <?php foreach ($top3 as $idx => $leader): 
                    $place = $idx + 1;
                    $border = 'rgba(255,255,255,0.1)';
                    $gradient = 'linear-gradient(180deg, rgba(30, 41, 59, 0.9) 0%, rgba(15, 23, 42, 0.95) 100%)';
                    $medalIcon = 'fa-medal';
                    $medalColor = '#cbd5e1';
                    $titleText = "{$place} место";

                    if ($place === 1) {
                        $border = '#f59e0b';
                        $gradient = 'linear-gradient(180deg, rgba(245, 158, 11, 0.15) 0%, rgba(30, 41, 59, 0.95) 100%)';
                        $medalIcon = 'fa-trophy';
                        $medalColor = '#fbbf24';
                        $titleText = "🥇 1 место — Лидер";
                    } elseif ($place === 2) {
                        $border = '#94a3b8';
                        $medalColor = '#e2e8f0';
                        $titleText = "🥈 2 место";
                    } elseif ($place === 3) {
                        $border = '#d97706';
                        $medalColor = '#fb923c';
                        $titleText = "🥉 3 место";
                    }
                    $lScore = (int)$leader['score'];
                    $lStreak = (int)$leader['confirm_streak'];
                    $social = $leader['social'] ?? null;
                ?>
                <div class="glass-card" style="position: relative; background: <?= $gradient ?>; border: 2px solid <?= $border ?>; padding: 22px; display: flex; flex-direction: column; justify-content: space-between; border-radius: 14px; box-shadow: 0 12px 24px -8px rgba(0,0,0,0.4);">
                    
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <span style="font-size: 14px; font-weight: 800; color: <?= $medalColor ?>; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fa-solid <?= $medalIcon ?>"></i> <?= $titleText ?>
                            </span>
                            <span style="font-size: 12px; background: rgba(255,255,255,0.08); padding: 3px 10px; border-radius: 9999px; color: #94a3b8; font-weight: 600;">
                                <?= e($leader['dormitory_name'] ?? 'КузГТУ') ?>
                            </span>
                        </div>

                        <div style="font-size: 18px; font-weight: 800; color: #fff; margin-bottom: 4px;">
                            <?= e(trim($leader['last_name'] . ' ' . $leader['first_name'] . ' ' . ($leader['patronymic'] ?? ''))) ?>
                        </div>
                        <div style="font-size: 13px; color: #94a3b8; margin-bottom: 16px;">
                            Комната: <b style="color: #e2e8f0;"><?= e($leader['inidroom']) ?></b>
                        </div>

                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px;">
                            <span style="font-size: 13px; padding: 5px 12px; border-radius: 8px; background: rgba(16, 185, 129, 0.15); color: #34d399; font-weight: 700; border: 1px solid rgba(16, 185, 129, 0.3);">
                                <i class="fa-solid fa-star"></i> <?= $lScore ?> б.
                            </span>
                            <?php if ($lStreak > 0): ?>
                                <span style="font-size: 13px; padding: 5px 12px; border-radius: 8px; background: rgba(225, 29, 72, 0.15); color: #fb7185; font-weight: 700; border: 1px solid rgba(225, 29, 72, 0.3);">
                                    🔥 <?= $lStreak ?> стрик
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ССЫЛКА НА СОЦСЕТЬ ПО ПРАВИЛУ ПРИОРИТЕТА (TG > VK > MAX) -->
                    <div style="border-top: 1px solid rgba(255,255,255,0.08); padding-top: 14px;">
                        <?php if (!empty($social)): ?>
                            <a href="<?= e($social['url']) ?>" 
                               target="_blank" 
                               rel="noopener noreferrer" 
                               class="btn" 
                               style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; background: <?= $social['bg'] ?>; color: <?= $social['color'] ?>; border: 1px solid <?= $social['border'] ?>; font-weight: 700; padding: 9px 14px; border-radius: 10px; font-size: 13px; text-decoration: none; transition: transform 0.2s;"
                               onmouseover="this.style.transform='scale(1.02)'"
                               onmouseout="this.style.transform='scale(1)'">
                                <i class="<?= $social['icon'] ?>" style="font-size: 16px;"></i>
                                <span><?= e($social['badge']) ?></span>
                                <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 11px; opacity: 0.7; margin-left: auto;"></i>
                            </a>
                        <?php else: ?>
                            <div style="font-size: 12px; color: #64748b; text-align: center; padding: 6px;">
                                <i class="fa-solid fa-user-shield"></i> Профиль скрыт
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- МЕЖДУ НИМИ: РАЗДЕЛИТЕЛЬ 3 ТОЧКИ С КНОПКОЙ СКРЫТЬ/ПОКАЗАТЬ ПРОМЕЖУТОК -->
    <div style="text-align: center; margin: 36px 0;">
        <div style="display: inline-flex; align-items: center; gap: 14px; background: rgba(30, 41, 59, 0.7); padding: 10px 24px; border-radius: 9999px; border: 1px dashed rgba(255,255,255,0.2);">
            <span style="font-size: 20px; letter-spacing: 4px; color: #94a3b8; font-weight: 900;">•••</span>
            <span style="font-size: 13px; color: #cbd5e1; font-weight: 600;">
                Промежуток: <b style="color: #38bdf8;"><?= $middleCount ?></b> добросовестных жителей
            </span>
            <span style="font-size: 20px; letter-spacing: 4px; color: #94a3b8; font-weight: 900;">•••</span>
            
            <?php if ($middleCount > 0): ?>
                <button type="button" 
                        class="btn btn-sm btn-secondary" 
                        id="toggleMiddleBtn" 
                        onclick="toggleMiddleList()" 
                        style="padding: 4px 12px; font-size: 12px; border-radius: 9999px; margin-left: 6px;">
                    <i class="fa-solid fa-chevron-down" id="toggleMiddleIcon"></i> Показать всех
                </button>
            <?php endif; ?>
        </div>

        <!-- Контейнер для раскрытия промежуточного списка -->
        <div id="middleListContainer" style="display: none; margin-top: 20px; text-align: left;">
            <div class="glass-card" style="padding: 16px;">
                <div id="middleListContent">
                    <div style="text-align: center; color: #94a3b8; padding: 20px;">
                        <i class="fa-solid fa-spinner fa-spin"></i> Загрузка жителей...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- АНТИТОП 3: КТО ПРОПУСКАЛ СТИРКУ -->
    <div style="margin-top: 10px; margin-bottom: 40px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
            <h2 style="margin: 0; font-size: 19px; font-weight: 800; color: #f87171; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-triangle-exclamation"></i> Антитоп-3 (кто пропускал стирку)
            </h2>
            <span style="font-size: 12px; background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); padding: 3px 10px; border-radius: 9999px; font-weight: 700;">
                Штрафники дисциплины
            </span>
        </div>

        <?php if (empty($bottom3)): ?>
            <div class="glass-card" style="text-align: center; padding: 28px; color: #34d399;">
                <i class="fa-solid fa-face-smile" style="font-size: 24px; margin-bottom: 6px; display: block;"></i>
                Отлично! В этом общежитии нет злостных нарушителей с низкими баллами.
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                <?php foreach ($bottom3 as $idx => $bad): 
                    $bScore = (int)$bad['score'];
                    $bMiss = (int)$bad['miss_streak'];
                    $bPlace = $idx + 1;
                ?>
                <div class="glass-card" style="background: linear-gradient(180deg, rgba(239, 68, 68, 0.08) 0%, rgba(15, 23, 42, 0.9) 100%); border: 1px solid rgba(239, 68, 68, 0.3); padding: 18px; border-radius: 12px;">
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                        <span style="font-size: 13px; font-weight: 800; color: #f87171; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-ban"></i> Антитоп #<?= $bPlace ?>
                        </span>
                        <span style="font-size: 11px; background: rgba(255,255,255,0.06); padding: 2px 8px; border-radius: 9999px; color: #94a3b8;">
                            <?= e($bad['dormitory_name'] ?? 'КузГТУ') ?>
                        </span>
                    </div>

                    <div style="font-size: 16px; font-weight: 700; color: #e2e8f0; margin-bottom: 4px;">
                        <?= e(trim($bad['last_name'] . ' ' . $bad['first_name'] . ' ' . ($bad['patronymic'] ?? ''))) ?>
                    </div>
                    <div style="font-size: 13px; color: #94a3b8; margin-bottom: 14px;">
                        Комната: <b style="color: #cbd5e1;"><?= e($bad['inidroom']) ?></b>
                    </div>

                    <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        <span style="font-size: 12px; padding: 4px 10px; border-radius: 6px; background: rgba(239, 68, 68, 0.2); color: #f87171; font-weight: 700; border: 1px solid rgba(239, 68, 68, 0.4);">
                            <i class="fa-solid fa-heart-crack"></i> <?= $bScore ?> б.
                        </span>
                        <?php if ($bMiss > 0): ?>
                            <span style="font-size: 12px; padding: 4px 10px; border-radius: 6px; background: rgba(249, 115, 22, 0.2); color: #fb923c; font-weight: 700;">
                                ⚠️ <?= $bMiss ?> пропусков подряд
                            </span>
                        <?php else: ?>
                            <span style="font-size: 12px; color: #94a3b8;">
                                Низкий баланс
                            </span>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
var middleLoaded = false;

function toggleMiddleList() {
    var container = document.getElementById('middleListContainer');
    var icon = document.getElementById('toggleMiddleIcon');
    var btn = document.getElementById('toggleMiddleBtn');

    if (container.style.display === 'none' || container.style.display === '') {
        container.style.display = 'block';
        icon.className = 'fa-solid fa-chevron-up';
        btn.innerHTML = '<i class="fa-solid fa-chevron-up"></i> Скрыть';
        if (!middleLoaded) {
            loadMiddleList();
        }
    } else {
        container.style.display = 'none';
        icon.className = 'fa-solid fa-chevron-down';
        btn.innerHTML = '<i class="fa-solid fa-chevron-down"></i> Показать всех';
    }
}

function loadMiddleList() {
    var dormId = '<?= $dormitory_id ? (int)$dormitory_id : '' ?>';
    var url = '/hall-of-fame?ajax_middle=1' + (dormId ? '&dormitory_id=' + dormId : '');

    fetch(url)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            middleLoaded = true;
            if (!data.success || !data.residents || data.residents.length === 0) {
                document.getElementById('middleListContent').innerHTML = '<div style="text-align:center; color:#94a3b8; padding:20px;">Нет промежуточных жителей.</div>';
                return;
            }
            var html = '<table class="custom-table" style="font-size:13px; width:100%;">';
            html += '<thead><tr><th style="width:70px;">Место</th><th>Житель</th><th style="text-align:center;">Комната</th><th>Корпус</th><th style="text-align:center;">Баллы</th><th style="text-align:center;">Стрик</th></tr></thead><tbody>';
            data.residents.forEach(function(r) {
                var pos = r.rank_pos;
                var streakBadge = r.confirm_streak > 0 ? ('🔥 ' + r.confirm_streak) : '—';
                html += '<tr>' +
                    '<td style="font-weight:700; color:#38bdf8;">#' + pos + '</td>' +
                    '<td style="font-weight:600;">' + (r.last_name + ' ' + r.first_name + (r.patronymic ? ' ' + r.patronymic : '')) + '</td>' +
                    '<td style="text-align:center;">' + (r.inidroom || '—') + '</td>' +
                    '<td style="color:#94a3b8;">' + (r.dormitory_name || '—') + '</td>' +
                    '<td style="text-align:center; font-weight:700; color:#34d399;">' + r.score + ' б.</td>' +
                    '<td style="text-align:center; font-weight:600; color:#fb7185;">' + streakBadge + '</td>' +
                '</tr>';
            });
            html += '</tbody></table>';
            document.getElementById('middleListContent').innerHTML = html;
        })
        .catch(function(err) {
            document.getElementById('middleListContent').innerHTML = '<div style="text-align:center; color:#f87171; padding:20px;">Ошибка загрузки списка.</div>';
        });
}
</script>
