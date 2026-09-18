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

    // 3. Глобальная инициализация стильных выпадающих списков (как у календаря Flatpickr)
    function initCustomSelects() {
        const selects = document.querySelectorAll('select.form-control:not([data-custom-select="false"]), select:not([data-custom-select="false"]):not([multiple])');

        selects.forEach(function(select) {
            if (select._customSelect) {
                select._customSelect.sync();
                return;
            }

            if (select.closest('.custom-select-container')) return;

            const container = document.createElement('div');
            container.className = 'custom-select-container';
            if (select.className) {
                const extraClasses = select.className.split(' ').filter(c => c && c !== 'form-control');
                if (extraClasses.length) container.classList.add(...extraClasses);
            }

            select.parentNode.insertBefore(container, select);
            container.appendChild(select);
            select.classList.add('custom-select-hidden');

            const trigger = document.createElement('div');
            trigger.className = 'custom-select-trigger form-control';
            trigger.tabIndex = 0;
            trigger.setAttribute('role', 'combobox');
            trigger.setAttribute('aria-expanded', 'false');

            const valueSpan = document.createElement('span');
            valueSpan.className = 'custom-select-value';

            const arrowSpan = document.createElement('span');
            arrowSpan.className = 'custom-select-arrow';
            arrowSpan.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>';

            trigger.appendChild(valueSpan);
            trigger.appendChild(arrowSpan);
            container.appendChild(trigger);

            const dropdown = document.createElement('div');
            dropdown.className = 'custom-select-dropdown';
            dropdown.setAttribute('role', 'listbox');

            let searchInput = null;
            let searchWrapper = null;
            if (select.options.length > 7) {
                searchWrapper = document.createElement('div');
                searchWrapper.className = 'custom-select-search-wrap';
                searchWrapper.innerHTML = `
                    <div class="custom-select-search-inner">
                        <svg class="custom-select-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        <input type="text" class="custom-select-search-input" placeholder="Поиск..." autocomplete="off">
                    </div>
                `;
                searchInput = searchWrapper.querySelector('input');
                dropdown.appendChild(searchWrapper);
            }

            const optionsList = document.createElement('div');
            optionsList.className = 'custom-select-options';
            dropdown.appendChild(optionsList);

            const emptyMsg = document.createElement('div');
            emptyMsg.className = 'custom-select-empty';
            emptyMsg.textContent = 'Ничего не найдено';
            emptyMsg.style.display = 'none';
            dropdown.appendChild(emptyMsg);

            container.appendChild(dropdown);

            function sync() {
                const selectedOpt = select.options[select.selectedIndex];
                valueSpan.textContent = selectedOpt ? selectedOpt.text : (select.getAttribute('placeholder') || 'Выберите значение');

                if (select.value === '') {
                    valueSpan.classList.add('is-placeholder');
                } else {
                    valueSpan.classList.remove('is-placeholder');
                }

                optionsList.innerHTML = '';
                let visibleCount = 0;
                let currentGroup = null;

                Array.from(select.options).forEach((opt, idx) => {
                    if (opt.parentElement && opt.parentElement.tagName === 'OPTGROUP') {
                        if (currentGroup !== opt.parentElement) {
                            currentGroup = opt.parentElement;
                            const groupHeader = document.createElement('div');
                            groupHeader.className = 'custom-select-optgroup-label';
                            groupHeader.textContent = currentGroup.label;
                            optionsList.appendChild(groupHeader);
                        }
                    } else {
                        currentGroup = null;
                    }

                    const optEl = document.createElement('div');
                    optEl.className = 'custom-select-option';
                    optEl.setAttribute('role', 'option');
                    optEl.setAttribute('data-value', opt.value);
                    optEl.setAttribute('data-index', idx);

                    if (opt.selected) {
                        optEl.classList.add('selected');
                        optEl.setAttribute('aria-selected', 'true');
                    }

                    if (opt.disabled) {
                        optEl.classList.add('disabled');
                    }

                    if (opt.style.display === 'none') {
                        optEl.style.display = 'none';
                    } else {
                        visibleCount++;
                    }

                    const textSpan = document.createElement('span');
                    textSpan.className = 'custom-select-option-text';
                    textSpan.textContent = opt.text;
                    optEl.appendChild(textSpan);

                    const checkSpan = document.createElement('span');
                    checkSpan.className = 'custom-select-check';
                    checkSpan.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                    optEl.appendChild(checkSpan);

                    optEl.addEventListener('click', function(e) {
                        e.stopPropagation();
                        if (opt.disabled) return;
                        select.selectedIndex = idx;
                        select.value = opt.value;

                        sync();
                        close();

                        select.dispatchEvent(new Event('input', { bubbles: true }));
                        select.dispatchEvent(new Event('change', { bubbles: true }));
                    });

                    optionsList.appendChild(optEl);
                });

                if (searchWrapper) {
                    searchWrapper.style.display = visibleCount > 7 ? 'block' : 'none';
                }
            }

            function open() {
                document.querySelectorAll('.custom-select-container.is-open').forEach(c => {
                    if (c !== container && c._customSelect) c._customSelect.close();
                });

                container.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');

                const rect = container.getBoundingClientRect();
                const spaceBelow = window.innerHeight - rect.bottom;
                const dropdownHeight = dropdown.offsetHeight || 260;

                if (spaceBelow < dropdownHeight && rect.top > dropdownHeight) {
                    container.classList.add('is-dropup');
                } else {
                    container.classList.remove('is-dropup');
                }

                if (rect.left + dropdown.offsetWidth > window.innerWidth - 12) {
                    dropdown.style.left = 'auto';
                    dropdown.style.right = '0';
                } else {
                    dropdown.style.left = '0';
                    dropdown.style.right = 'auto';
                }

                if (searchInput && searchWrapper && searchWrapper.style.display !== 'none') {
                    searchInput.value = '';
                    filterOptions('');
                    setTimeout(() => searchInput.focus(), 60);
                }

                const selectedItem = optionsList.querySelector('.custom-select-option.selected');
                if (selectedItem) {
                    selectedItem.scrollIntoView({ block: 'nearest' });
                }
            }

            function close() {
                container.classList.remove('is-open', 'is-dropup');
                trigger.setAttribute('aria-expanded', 'false');
                if (searchInput) {
                    searchInput.value = '';
                    filterOptions('');
                }
            }

            function toggle() {
                if (container.classList.contains('is-open')) {
                    close();
                } else {
                    open();
                }
            }

            function filterOptions(query) {
                const q = query.toLowerCase().trim();
                const items = optionsList.querySelectorAll('.custom-select-option');
                let hasVisible = false;

                items.forEach(item => {
                    const idx = parseInt(item.getAttribute('data-index'), 10);
                    const originalOpt = select.options[idx];
                    if (originalOpt && originalOpt.style.display === 'none') {
                        item.style.display = 'none';
                        return;
                    }

                    const text = item.textContent.toLowerCase();
                    if (!q || text.includes(q)) {
                        item.style.display = '';
                        hasVisible = true;
                    } else {
                        item.style.display = 'none';
                    }
                });

                // Скрываем заголовки групп, если в них нет подходящих пунктов
                optionsList.querySelectorAll('.custom-select-optgroup-label').forEach(header => {
                    let next = header.nextElementSibling;
                    let groupHasVisible = false;
                    while (next && !next.classList.contains('custom-select-optgroup-label')) {
                        if (next.classList.contains('custom-select-option') && next.style.display !== 'none') {
                            groupHasVisible = true;
                            break;
                        }
                        next = next.nextElementSibling;
                    }
                    header.style.display = groupHasVisible ? '' : 'none';
                });

                emptyMsg.style.display = hasVisible ? 'none' : 'block';
            }

            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    filterOptions(e.target.value);
                });
                searchInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        close();
                        trigger.focus();
                    }
                });
                searchInput.addEventListener('click', (e) => e.stopPropagation());
            }

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                toggle();
            });

            trigger.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (!container.classList.contains('is-open')) {
                        open();
                    }
                } else if (e.key === 'Escape') {
                    close();
                }
            });

            select.addEventListener('change', () => {
                sync();
            });

            if (select.form) {
                select.form.addEventListener('reset', () => {
                    setTimeout(sync, 0);
                });
            }

            try {
                const nativeValueGetter = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value').get;
                const nativeValueSetter = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value').set;
                Object.defineProperty(select, 'value', {
                    get() {
                        return nativeValueGetter.call(this);
                    },
                    set(val) {
                        nativeValueSetter.call(this, val);
                        sync();
                    },
                    configurable: true
                });
            } catch (err) {}

            const observer = new MutationObserver(() => {
                sync();
            });
            observer.observe(select, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'disabled', 'selected'] });

            select._customSelect = {
                sync: sync,
                open: open,
                close: close,
                container: container
            };

            sync();
        });
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.custom-select-container')) {
            document.querySelectorAll('.custom-select-container.is-open').forEach(c => {
                if (c._customSelect) c._customSelect.close();
                else c.classList.remove('is-open', 'is-dropup');
            });
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.custom-select-container.is-open').forEach(c => {
                if (c._customSelect) c._customSelect.close();
                else c.classList.remove('is-open', 'is-dropup');
            });
        }
    });

    function initSidebarToggle() {
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        const sidebar = document.getElementById('mainSidebar');
        const container = document.querySelector('.container');

        if (!toggleBtn || !sidebar || !container) return;

        // Restore desktop collapsed state
        if (window.innerWidth > 768) {
            const savedState = localStorage.getItem('sidebar_collapsed');
            if (savedState === 'true') {
                container.classList.add('sidebar-collapsed');
            }
        }

        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
            } else {
                container.classList.toggle('sidebar-collapsed');
                const isCollapsed = container.classList.contains('sidebar-collapsed');
                localStorage.setItem('sidebar_collapsed', isCollapsed ? 'true' : 'false');
            }
        });

        // Close mobile drawer on click outside
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 768 && sidebar.classList.contains('mobile-open')) {
                if (!sidebar.contains(e.target) && e.target !== toggleBtn) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });

        // Close mobile drawer on link click
        sidebar.querySelectorAll('.menu-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) {
                    sidebar.classList.remove('mobile-open');
                }
            });
        });
    }

    window.initCustomSelects = initCustomSelects;
    window.initSidebarToggle = initSidebarToggle;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initDatePicker();
            initCustomSelects();
            initSidebarToggle();
        });
    } else {
        initDatePicker();
        initCustomSelects();
        initSidebarToggle();
    }
})();