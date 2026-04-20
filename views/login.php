<?php
// views/login.php
?>
<div style="flex: 1; 
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); 
            padding: 20px;">

    <div style="position: relative; max-width: 420px; width: 100%; background: white; 
                padding: 50px 40px 40px; border-radius: 16px; 
                box-shadow: 0 10px 30px rgba(0,0,0,0.15);">

        <!-- Кнопка «Вернуться назад» -->
        <a href="/booking" 
           style="position: absolute; top: 20px; left: 20px; 
                  color: #1976d2; text-decoration: none; font-size: 15px; 
                  display: flex; align-items: center; gap: 4px;">
            ← Вернуться назад
        </a>

        <!-- Иконка ключа -->
        <div style="text-align: center; margin-bottom: 25px;">
            <span style="font-size: 52px; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));">🔑</span>
        </div>

        <h1 style="text-align: center; margin-bottom: 35px; font-size: 26px; color: #263238;">
            Вход в админ-панель
        </h1>

        <?php if (isset($error)): ?>
            <div style="background: #ffebee; color: #c62828; padding: 14px; 
                        border-radius: 8px; margin-bottom: 25px; text-align: center; font-size: 15px;">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input 
                type="text" 
                name="username" 
                required 
                autofocus
                placeholder="Введите логин"
                style="width: 100%; padding: 16px 18px; margin-bottom: 18px; 
                       border: 2px solid #ddd; border-radius: 10px; font-size: 16px; 
                       box-sizing: border-box;"
            >

            <input 
                type="password" 
                name="password" 
                required
                placeholder="Введите пароль"
                style="width: 100%; padding: 16px 18px; margin-bottom: 28px; 
                       border: 2px solid #ddd; border-radius: 10px; font-size: 16px; 
                       box-sizing: border-box;"
            >

            <button 
                type="submit" 
                style="width: 100%; padding: 16px; background: #1976d2; color: white; 
                       border: none; border-radius: 10px; font-size: 17px; font-weight: 600; 
                       cursor: pointer; transition: background 0.2s;"
                onmouseover="this.style.background='#1565c0'"
                onmouseout="this.style.background='#1976d2'"
            >
                Войти
            </button>
        </form>

        <p style="text-align: center; margin-top: 25px; color: #666; font-size: 14px;">
            Только администраторам и техническому персоналу
        </p>
    </div>
</div>