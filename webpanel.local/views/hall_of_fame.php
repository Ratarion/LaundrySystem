<?php
// views/hall_of_fame.php — Зал славы прачечной КузГТУ (Светлый стиль общей дизайн-системы сайта)
?>
<div class="content-area">

    <!-- ШАПКА СТРАНИЦЫ -->
    <div class="page-header" style="flex-wrap: wrap; gap: 14px; margin-bottom: 24px;">
        <div>
            <h1 class="page-title" style="display: flex; align-items: center; gap: 10px; font-size: 24px; font-weight: 800; color: var(--text-main);">
                <i class="fa-solid fa-trophy" style="color: var(--primary);"></i> Зал славы прачечной КузГТУ
            </h1>
            <p style="color: var(--text-muted); font-size: 14px; margin: 4px 0 0 0;">
                Доска почёта самых дисциплинированных жителей и антирейтинг нарушителей
            </p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="/booking" class="btn btn-secondary" style="padding: 9px 18px; font-weight: 600;">
                <i class="fa-solid fa-calendar-days"></i> Бронирования
            </a>
            <?php if (!$isLoggedIn): ?>
                <a href="/login" class="btn btn-primary" style="padding: 9px 18px;">
                    <i class="fa-solid fa-key"></i> Вход в панель
                </a>
            <?php else: ?>
                <div class="user-badge">
                    <i class="fa-solid fa-user"></i> <span class="user-badge-name"><?= e($_SESSION['username']) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ФИЛЬТР ПО ОБЩЕЖИТИЯМ -->
    <div class="glass-card" style="margin-bottom: 24px; padding: 16px 20px;">
        <div style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center;">
            <span style="font-size: 13px; color: var(--text-muted); font-weight: 700; margin-right: 4px; text-transform: uppercase; letter-spacing: 0.05em;">
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
    <div class="glass-card" style="margin-bottom: 28px; <?= !empty($myCard) ? 'border: 2px solid var(--primary); box-shadow: 0 6px 24px -4px rgba(243, 128, 32, 0.18);' : '' ?>">
        
        <?php if (!empty($myCard)): 
            $pos = (int)$myCard['rank_pos'];
            $posBadge = "#{$pos}";
            $posColor = 'var(--primary)';
            $posBg = '#fff7ed';
            $posBorder = '#fed7aa';
            if ($pos === 1) { $posBadge = "🥇 1 место"; $posColor = '#d97706'; $posBg = '#fef3c7'; $posBorder = '#fde68a'; }
            elseif ($pos === 2) { $posBadge = "🥈 2 место"; $posColor = '#475569'; $posBg = '#f1f5f9'; $posBorder = '#cbd5e1'; }
            elseif ($pos === 3) { $posBadge = "🥉 3 место"; $posColor = '#c2410c'; $posBg = '#ffedd5'; $posBorder = '#fed7aa'; }

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
                    <div style="font-size: 26px; font-weight: 800; color: <?= $posColor ?>; background: <?= $posBg ?>; border: 1.5px solid <?= $posBorder ?>; padding: 12px 18px; border-radius: 12px; min-width: 90px; text-align: center;">
                        <?= $posBadge ?>
                    </div>
                    <div>
                        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--primary); font-weight: 700; margin-bottom: 2px;">
                            <i class="fa-solid fa-user-check"></i> Ваша позиция в Зале славы:
                        </div>
                        <div style="font-size: 20px; font-weight: 800; color: var(--text-main);">
                            <?= e(trim($myCard['last_name'] . ' ' . $myCard['first_name'] . ' ' . ($myCard['patronymic'] ?? ''))) ?>
                        </div>
                        <div style="font-size: 13px; color: var(--text-muted); margin-top: 2px;">
                            <?= e($myCard['dormitory_name'] ?? 'КузГТУ') ?> 
                            • <span>Место <?= $pos ?> из <?= $totalResidents ?></span>
                        </div>
                    </div>
                </div>

                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 8px 16px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 11px; color: #065f46; text-transform: uppercase; font-weight: 600;">Баллы</div>
                        <div style="font-size: 18px; font-weight: 800; color: #059669;"><?= $mScore ?> б.</div>
                    </div>

                    <?php if ($mStreak > 0): ?>
                    <div style="background: #fff1f2; border: 1px solid #fecdd3; padding: 8px 16px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 11px; color: #9f1239; text-transform: uppercase; font-weight: 600;">Стрик</div>
                        <div style="font-size: 18px; font-weight: 800; color: #e11d48;">🔥 <?= $mStreak ?></div>
                    </div>
                    <?php endif; ?>

                    <div style="background: #f8fafc; border: 1px solid var(--border); padding: 8px 16px; border-radius: 10px; text-align: center;">
                        <div style="font-size: 11px; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Ранг</div>
                        <div style="font-size: 14px; font-weight: 700; color: var(--text-main); margin-top: 3px;"><?= $rankBadge ?> <?= $rankTitle ?></div>
                    </div>

                    <a href="/hall-of-fame<?= $dormitory_id ? '?dormitory_id='.$dormitory_id : '' ?>" class="btn btn-secondary" title="Сбросить выбор" style="padding: 10px 14px;">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                </div>
            </div>

        <?php else: ?>
            <!-- Форма поиска своего места -->
            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;">
                <div>
                    <h3 style="margin: 0 0 4px 0; font-size: 17px; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-magnifying-glass-chart" style="color: var(--primary);"></i> Узнать своё место в рейтинге
                    </h3>
                    <p style="margin: 0; font-size: 13px; color: var(--text-muted);">
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
                           style="background: #ffffff; border: 1px solid var(--border); color: var(--text-main);">
                    <button type="submit" class="btn btn-primary" style="white-space: nowrap; padding: 10px 20px;">
                        <i class="fa-solid fa-search"></i> Найти себя
                    </button>
                </form>
            </div>
            <?php if (!empty($searchQuery)): ?>
                <div style="margin-top: 12px; color: var(--danger); font-size: 13px; font-weight: 600;">
                    <i class="fa-solid fa-triangle-exclamation"></i> Житель по запросу "<?= e($searchQuery) ?>" не найден в выбранном общежитии.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ТОП 3 ЛИДЕРОВ (ПОДИУМ) -->
    <div style="margin-bottom: 32px;">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 16px;">
            <h2 style="margin: 0; font-size: 19px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-crown" style="color: #f59e0b;"></i> Топ-3 лидеров дисциплины
            </h2>
            <span style="font-size: 12px; background: #fef3c7; color: #b45309; border: 1px solid #fde68a; padding: 3px 10px; border-radius: 9999px; font-weight: 700;">
                Лидеры прачечной
            </span>
        </div>

        <?php if (empty($top3)): ?>
            <div class="glass-card" style="text-align: center; padding: 32px; color: var(--text-muted);">
                В этом общежитии пока нет подтверждённых жителей.
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 18px;">
                <?php foreach ($top3 as $idx => $leader): 
                    $place = $idx + 1;
                    $border = '1.5px solid var(--border)';
                    $cardBg = '#ffffff';
                    $medalIcon = 'fa-medal';
                    $medalColor = '#64748b';
                    $titleText = "{$place} место";
                    $boxShadow = 'var(--shadow-sm)';

                    if ($place === 1) {
                        $border = '2px solid #f59e0b';
                        $medalIcon = 'fa-trophy';
                        $medalColor = '#d97706';
                        $titleText = "🥇 1 место — Лидер";
                        $boxShadow = '0 8px 24px -4px rgba(245, 158, 11, 0.18)';
                    } elseif ($place === 2) {
                        $border = '1.5px solid #cbd5e1';
                        $medalColor = '#475569';
                        $titleText = "🥈 2 место";
                        $boxShadow = 'var(--shadow-sm)';
                    } elseif ($place === 3) {
                        $border = '1.5px solid #fed7aa';
                        $medalColor = '#c2410c';
                        $titleText = "🥉 3 место";
                        $boxShadow = 'var(--shadow-sm)';
                    }
                    $lScore = (int)$leader['score'];
                    $lStreak = (int)$leader['confirm_streak'];
                    $social = $leader['social'] ?? null;
                ?>
                <div class="glass-card" style="position: relative; background: <?= $cardBg ?>; border: <?= $border ?>; padding: 22px; display: flex; flex-direction: column; justify-content: space-between; border-radius: 12px; box-shadow: <?= $boxShadow ?>;">
                    
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                            <span style="font-size: 14px; font-weight: 800; color: <?= $medalColor ?>; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fa-solid <?= $medalIcon ?>"></i> <?= $titleText ?>
                            </span>
                            <span style="font-size: 12px; background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 3px 10px; border-radius: 9999px; font-weight: 700;">
                                <?= e($leader['dormitory_name'] ?? 'КузГТУ') ?>
                            </span>
                        </div>

                        <div style="font-size: 18px; font-weight: 800; color: var(--text-main); margin-bottom: 14px;">
                            <?= e(trim($leader['last_name'] . ' ' . $leader['first_name'] . ' ' . ($leader['patronymic'] ?? ''))) ?>
                        </div>

                        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px;">
                            <span style="font-size: 13px; padding: 5px 12px; border-radius: 8px; background: #ecfdf5; color: #059669; font-weight: 700; border: 1px solid #a7f3d0;">
                                <i class="fa-solid fa-star"></i> <?= $lScore ?> б.
                            </span>
                            <?php if ($lStreak > 0): ?>
                                <span style="font-size: 13px; padding: 5px 12px; border-radius: 8px; background: #fff1f2; color: #e11d48; font-weight: 700; border: 1px solid #fecdd3;">
                                    🔥 <?= $lStreak ?> стрик
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ССЫЛКА НА СОЦСЕТЬ ПО ПРАВИЛУ ПРИОРИТЕТА (TG > VK > MAX) -->
                    <div style="border-top: 1px solid var(--border); padding-top: 14px;">
                        <?php if (!empty($social)): ?>
                            <a href="<?= e($social['url']) ?>" 
                               target="_blank" 
                               rel="noopener noreferrer" 
                               class="btn" 
                               style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; background: <?= $social['bg'] ?>; color: <?= $social['color'] ?>; border: 1px solid <?= $social['border'] ?>; font-weight: 700; padding: 9px 14px; border-radius: 8px; font-size: 13px; text-decoration: none; transition: all 0.2s ease;"
                               onmouseover="this.style.opacity='0.85'; this.style.transform='translateY(-1px)'"
                               onmouseout="this.style.opacity='1'; this.style.transform='translateY(0)'">
                                <i class="<?= $social['icon'] ?>" style="font-size: 16px;"></i>
                                <span><?= e($social['badge']) ?></span>
                                <i class="fa-solid fa-arrow-up-right-from-square" style="font-size: 11px; opacity: 0.7; margin-left: auto;"></i>
                            </a>
                        <?php else: ?>
                            <div style="font-size: 12px; color: var(--text-muted); text-align: center; padding: 6px;">
                                <i class="fa-solid fa-user-shield"></i> Профиль скрыт
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- РАЗДЕЛИТЕЛЬ: ОСТАЛЬНЫЕ УЧАСТНИКИ РЕЙТИНГА -->
    <div style="text-align: center; margin: 36px 0 48px 0;">
        <div style="display: inline-flex; align-items: center; gap: 14px; background: #ffffff; padding: 10px 24px; border-radius: 9999px; border: 1.5px dashed var(--border); box-shadow: var(--shadow-sm);">
            <span style="font-size: 20px; letter-spacing: 4px; color: #9ca3af; font-weight: 900;">•••</span>
            <span style="font-size: 13px; color: var(--text-main); font-weight: 600;">
                Остальные участники рейтинга: <b style="color: var(--primary); font-weight: 800;"><?= $middleCount ?></b> чел.
            </span>
            <span style="font-size: 20px; letter-spacing: 4px; color: #9ca3af; font-weight: 900;">•••</span>
            
            <?php if ($middleCount > 0): ?>
                <button type="button" 
                        class="btn btn-sm btn-secondary" 
                        id="toggleMiddleBtn" 
                        onclick="toggleMiddleList()" 
                        style="padding: 5px 14px; font-size: 12px; border-radius: 9999px; margin-left: 6px; font-weight: 600;">
                    <i class="fa-solid fa-chevron-down" id="toggleMiddleIcon"></i> Показать всех
                </button>
            <?php endif; ?>
        </div>

        <!-- Контейнер для раскрытия списка остальных участников -->
        <div id="middleListContainer" style="display: none; margin-top: 20px; text-align: left;">
            <div class="glass-card" style="padding: 16px; overflow-x: auto;">
                <div id="middleListContent">
                    <div style="text-align: center; color: var(--text-muted); padding: 20px;">
                        <i class="fa-solid fa-spinner fa-spin" style="color: var(--primary);"></i> Загрузка жителей...
                    </div>
                </div>
            </div>
        </div>
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
                document.getElementById('middleListContent').innerHTML = '<div style="text-align:center; color:var(--text-muted); padding:20px;">Нет промежуточных жителей.</div>';
                return;
            }
            var html = '<table style="width:100%; border-collapse:collapse; font-size:13px;">';
            html += '<thead><tr style="background:#f9fafb; border-bottom:2px solid var(--border); color:var(--text-muted); text-align:left;">' +
                '<th style="padding:10px 14px; width:70px;">Место</th>' +
                '<th style="padding:10px 14px;">Житель</th>' +
                '<th style="padding:10px 14px;">Корпус</th>' +
                '<th style="padding:10px 14px; text-align:center;">Баллы</th>' +
                '<th style="padding:10px 14px; text-align:center;">Стрик</th>' +
                '</tr></thead><tbody>';
            data.residents.forEach(function(r) {
                var pos = r.rank_pos;
                var streakBadge = r.confirm_streak > 0 ? ('🔥 ' + r.confirm_streak) : '—';
                html += '<tr style="border-bottom:1px solid var(--border);">' +
                    '<td style="padding:10px 14px; font-weight:700; color:var(--primary);">#' + pos + '</td>' +
                    '<td style="padding:10px 14px; font-weight:600; color:var(--text-main);">' + (r.last_name + ' ' + r.first_name + (r.patronymic ? ' ' + r.patronymic : '')) + '</td>' +
                    '<td style="padding:10px 14px; color:var(--text-muted);">' + (r.dormitory_name || '—') + '</td>' +
                    '<td style="padding:10px 14px; text-align:center; font-weight:700; color:#059669;">' + r.score + ' б.</td>' +
                    '<td style="padding:10px 14px; text-align:center; font-weight:600; color:#e11d48;">' + streakBadge + '</td>' +
                '</tr>';
            });
            html += '</tbody></table>';
            document.getElementById('middleListContent').innerHTML = html;
        })
        .catch(function(err) {
            document.getElementById('middleListContent').innerHTML = '<div style="text-align:center; color:var(--danger); padding:20px;">Ошибка загрузки списка.</div>';
        });
}
</script>
