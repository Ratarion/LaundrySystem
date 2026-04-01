(function() {
    // Ждем, пока DOM загрузится (на всякий случай)
    const toast = document.getElementById('success-toast');
    
    if (toast) {
        // 1. Устанавливаем время отображения (4 секунды)
        setTimeout(() => {
            // Добавляем плавное исчезновение
            toast.style.transition = 'opacity 0.4s ease';
            toast.style.opacity = '0';

            // 2. Ждем окончания анимации (400мс) и удаляем элемент из DOM
            setTimeout(() => {
                toast.remove();
            }, 400);
        }, 4000);
    }
})();