<?php
// views/change_password.php — Редизайн страницы смены пароля
?>
<div class="content-area">
    <div class="page-header">
        <h1 class="page-title"><i class="fa-solid fa-key"></i> Смена пароля</h1>
    </div>
    
    <?php if (!empty($message)): ?>
        <div style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.25); padding: 14px 18px; border-radius: 8px; color: var(--success); margin-bottom: 24px; font-weight: 500; font-size: 14px; display: inline-flex; align-items: center; gap: 8px;">
            <span><i class="fa-solid fa-circle-check" style="color: var(--success);"></i></span> <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="glass-card" style="max-width: 480px;">
        <form method="POST" style="display: flex; flex-direction: column; gap: 20px;">
            <div class="form-group" style="min-width: 100%;">
                <label class="form-label">Новый пароль</label>
                
                <div style="position: relative; display: flex; align-items: center; width: 100%;">
                    <input type="password" name="new_password" id="password_input" required 
                           class="form-control" style="padding-right: 46px;">
                    
                    <span id="toggle_password" style="position: absolute; right: 14px; cursor: pointer; user-select: none; font-size: 16px; color: var(--text-muted);">
                        <i class="fa-solid fa-eye" id="toggle_eye_icon"></i>
                    </span>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary" style="align-self: flex-start; padding: 12px 24px;">
                Сохранить новый пароль
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('toggle_password').addEventListener('click', function () {
    const passwordInput = document.getElementById('password_input');
    const eyeIcon = document.getElementById('toggle_eye_icon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.className = 'fa-solid fa-eye-slash';
    } else {
        passwordInput.type = 'password';
        eyeIcon.className = 'fa-solid fa-eye';
    }
});
</script>