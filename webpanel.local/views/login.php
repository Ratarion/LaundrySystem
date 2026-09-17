<?php
// views/login.php — Редизайн страницы авторизации в тёмном стиле премиум-класса
?>
<div class="login-wrapper">
    <div class="login-card">
        <!-- Кнопка назад -->
        <a href="/booking" class="login-back-link">
            <i class="fa-solid fa-arrow-left"></i> Вернуться назад
        </a>

        <!-- Иконка ключа -->
        <div class="login-icon"><i class="fa-solid fa-key" style="font-size: 40px; color: var(--primary);"></i></div>

        <h1 class="login-title">Вход в админ-панель</h1>

        <?php if (isset($error)): ?>
            <div class="login-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div style="margin-bottom: 18px;">
                <input 
                    type="text" 
                    name="username" 
                    required 
                    autofocus
                    placeholder="Введите логин"
                    class="form-control"
                    style="padding: 14px 16px;"
                >
            </div>

            <div style="margin-bottom: 24px;">
                <input 
                    type="password" 
                    name="password" 
                    required
                    placeholder="Введите пароль"
                    class="form-control"
                    style="padding: 14px 16px;"
                >
            </div>

            <button 
                type="submit" 
                class="btn btn-primary" 
                style="width: 100%; padding: 14px; font-size: 16px;"
            >
                Войти
            </button>
        </form>

        <p class="login-footer-text">
            Только администраторам и техническому персоналу
        </p>
    </div>
</div>