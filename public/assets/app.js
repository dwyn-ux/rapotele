document.addEventListener('DOMContentLoaded', () => {
    /* ── Sidebar Toggle ──────────────────────────────────── */
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
            // Also close user dropdown
            const dropdownMenu = document.getElementById('user-dropdown-menu');
            if (dropdownMenu) dropdownMenu.classList.remove('open');
        }
    });

    document.querySelectorAll('.sidebar .menu a').forEach((link) => {
        link.addEventListener('click', () => {
            if (window.matchMedia('(max-width: 980px)').matches) {
                closeSidebar();
            }
        });
    });

    /* ── Menu Groups (accordion) ─────────────────────────── */
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

    /* ── Textarea Auto-resize ────────────────────────────── */
    document.querySelectorAll('textarea').forEach((textarea) => {
        const resize = () => {
            textarea.style.height = 'auto';
            textarea.style.height = Math.max(100, textarea.scrollHeight) + 'px';
        };
        textarea.addEventListener('input', resize);
        resize();
    });

    /* ── Input Panel Toggle ──────────────────────────────── */
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

    /* ── Schedule Template Selection ─────────────────────── */
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

    /* ── Auto-generate Username ──────────────────────────── */
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

    /* ── Table Search ────────────────────────────────────── */
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

    /* ── User Dropdown ───────────────────────────────────── */
    const userDropdownTrigger = document.querySelector('[data-user-dropdown]');
    const userDropdownMenu = document.getElementById('user-dropdown-menu');
    if (userDropdownTrigger && userDropdownMenu) {
        userDropdownTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdownMenu.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if (!userDropdownMenu.contains(e.target) && !userDropdownTrigger.contains(e.target)) {
                userDropdownMenu.classList.remove('open');
            }
        });
    }

    /* ── Global Search Ctrl+K ────────────────────────────── */
    const searchInput = document.querySelector('.topbar-search input');
    if (searchInput) {
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                searchInput.focus();
            }
        });
    }

    /* ── Toast Notifications ─────────────────────────────── */
    window.showToast = function(message, type = 'info', duration = 4000) {
        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const iconMap = {
            success: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
            danger: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
            warning: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
            info: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#0EA5E9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        };

        const toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.innerHTML = (iconMap[type] || iconMap.info) + '<span>' + message + '</span>';
        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(20px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    };
});
