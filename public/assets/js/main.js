(function() {
    // 1. Уведомления toast
    const toast = document.getElementById('success-toast') || document.getElementById('error-toast');
    if (toast) {
        setTimeout(() => {
            toast.style.transition = 'opacity 0.4s ease';
            toast.style.opacity = '0';
            setTimeout(() => { toast.remove(); }, 400);
        }, 4500);
    }

    // 2. Глобальная инициализация стильного выпадающего календаря (Flatpickr)
    function initDatePicker() {
        if (typeof flatpickr === 'undefined') return;

        if (flatpickr.l10ns && flatpickr.l10ns.ru) {
            flatpickr.localize(flatpickr.l10ns.ru);
        }

        const dateInputs = document.querySelectorAll('input[type="date"], .date-picker');
        dateInputs.forEach(function(input) {
            if (input._flatpickr) return;

            // Считываем начальное значение даты
            const initialVal = input.value || '';

            // Переключаем тип в text, чтобы браузер не открывал свой нативный календарь
            input.type = 'text';

            flatpickr(input, {
                locale: 'ru',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd.m.Y',
                defaultDate: initialVal || null,
                allowInput: false,
                disableMobile: true, // Исключаем нативный мобильный пикер, всегда показываем стильный UI
                prevArrow: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>',
                nextArrow: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>',
                onReady: function(selectedDates, dateStr, instance) {
                    if (instance.altInput) {
                        instance.altInput.classList.add('form-control', 'custom-datepicker-input');
                        instance.altInput.placeholder = 'ДД.ММ.ГГГГ';
                        if (input.id) {
                            instance.altInput.setAttribute('data-for-id', input.id);
                        }
                    }

                    // Добавляем нижнюю панель кнопок "Сегодня" и "Очистить"
                    const footer = document.createElement('div');
                    footer.className = 'flatpickr-custom-footer';
                    footer.innerHTML = `
                        <button type="button" class="flatpickr-btn-clear">Очистить</button>
                        <button type="button" class="flatpickr-btn-today">Сегодня</button>
                    `;

                    footer.querySelector('.flatpickr-btn-today').addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        instance.setDate(new Date(), true);
                        instance.close();
                    });

                    footer.querySelector('.flatpickr-btn-clear').addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        instance.clear();
                        instance.close();
                    });

                    instance.calendarContainer.appendChild(footer);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDatePicker);
    } else {
        initDatePicker();
    }
})();