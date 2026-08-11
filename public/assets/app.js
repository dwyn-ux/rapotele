document.addEventListener('DOMContentLoaded', () => {
    const sidebarButtons = Array.from(document.querySelectorAll('[data-sidebar-open]'));
    const setSidebarState = (open) => {
        document.body.classList.toggle('sidebar-open', open);
        sidebarButtons.forEach((button) => {
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    };
    const openSidebar = () => setSidebarState(true);
    const closeSidebar = () => setSidebarState(false);

    sidebarButtons.forEach((button) => {
        button.addEventListener('click', openSidebar);
    });

    document.querySelectorAll('[data-sidebar-close]').forEach((button) => {
        button.addEventListener('click', closeSidebar);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    document.querySelectorAll('.sidebar .menu a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 980px)').matches) {
                closeSidebar();
            }
        });
    });

    const menuGroups = Array.from(document.querySelectorAll('.menu-group'));
    menuGroups.forEach((group) => {
        group.addEventListener('toggle', () => {
            if (!group.open) {
                return;
            }
            menuGroups.forEach((other) => {
                if (other !== group) {
                    other.open = false;
                }
            });
        });
    });

    document.querySelectorAll('textarea').forEach((textarea) => {
        const resize = () => {
            textarea.style.height = 'auto';
            textarea.style.height = Math.max(96, textarea.scrollHeight) + 'px';
        };
        textarea.addEventListener('input', resize);
        resize();
    });

    document.querySelectorAll('.input-panel-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const panel = button.closest('.input-panel');
            if (!panel) {
                return;
            }
            panel.classList.toggle('is-open');
            const isOpen = panel.classList.contains('is-open');
            button.textContent = isOpen
                ? (button.dataset.closeLabel || 'Tutup Form')
                : (button.dataset.toggleLabel || 'Tambah');
            if (isOpen) {
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    const scheduleGenerateForm = document.querySelector('[data-schedule-generate-form]');
    if (scheduleGenerateForm) {
        document.querySelectorAll('[data-schedule-template]').forEach((button) => {
            button.addEventListener('click', () => {
                const days = new Set((button.dataset.days || '').split(',').filter(Boolean));
                scheduleGenerateForm.querySelectorAll('input[name="days[]"]').forEach((checkbox) => {
                    checkbox.checked = days.has(checkbox.value);
                });

                const maxPeriod = scheduleGenerateForm.querySelector('input[name="max_period"]');
                if (maxPeriod && button.dataset.maxPeriod) {
                    maxPeriod.value = button.dataset.maxPeriod;
                }

                const periodsPerAssignment = scheduleGenerateForm.querySelector('input[name="periods_per_assignment"]');
                if (periodsPerAssignment && button.dataset.periodsPerAssignment) {
                    periodsPerAssignment.value = button.dataset.periodsPerAssignment;
                }

                document.querySelectorAll('[data-schedule-template]').forEach((item) => {
                    item.classList.toggle('is-selected', item === button);
                });
                scheduleGenerateForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
    }

    const nameInput = document.querySelector('input[name="name"]');
    const usernameInput = document.querySelector('input[name="username"][data-autofill-username]');
    if (nameInput && usernameInput && !usernameInput.readOnly) {
        const generateUsername = (name) => {
            const words = name.trim().split(/\s+/).slice(0, 2);
            const slug = words.join('').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]/g, '');
            return slug || 'guru';
        };
        const updatePlaceholder = () => {
            usernameInput.placeholder = 'otomatis: ' + generateUsername(nameInput.value);
        };
        nameInput.addEventListener('input', updatePlaceholder);
        updatePlaceholder();
    }

    document.querySelectorAll('.table-search-input').forEach((input) => {
        const panel = input.closest('.table-panel');
        if (!panel) return;
        const tbody = panel.querySelector('tbody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const emptyRow = rows.find((r) => r.querySelector('.empty'));
        input.addEventListener('input', () => {
            const q = input.value.toLowerCase().trim();
            let visible = 0;
            rows.forEach((row) => {
                if (row === emptyRow) return;
                const text = row.textContent.toLowerCase();
                const match = q === '' || text.includes(q);
                row.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            if (emptyRow) emptyRow.style.display = visible === 0 ? '' : 'none';
        });
    });
});
