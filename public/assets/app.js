document.addEventListener('DOMContentLoaded', () => {
    /* ── Sidebar Scroll Position ────────────────────────── */
    const sidebarNav = document.querySelector('.sidebar-nav');
    const STORAGE_KEY = 'sidebar_scroll_pos';
    if (sidebarNav) {
        const saved = sessionStorage.getItem(STORAGE_KEY);
        if (saved) {
            sidebarNav.scrollTop = parseInt(saved, 10) || 0;
        }
        sidebarNav.addEventListener('scroll', () => {
            sessionStorage.setItem(STORAGE_KEY, sidebarNav.scrollTop);
        });
        /* Also save before navigating away */
        document.querySelectorAll('.sidebar .menu a').forEach((link) => {
            link.addEventListener('mousedown', () => {
                sessionStorage.setItem(STORAGE_KEY, sidebarNav.scrollTop);
            });
        });
    }

    /* ── Smooth Filter Auto-Submit ───────────────────────── */
    document.querySelectorAll('form[method="get"] select').forEach((select) => {
        select.addEventListener('change', () => {
            const form = select.closest('form');
            if (!form) return;
            /* Save sidebar scroll before submitting */
            if (sidebarNav) {
                sessionStorage.setItem(STORAGE_KEY, sidebarNav.scrollTop);
            }
            form.submit();
        });
    });

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

// ═══════════════════════════════════════
// FILE UPLOAD COMPONENT
// ═══════════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.file-upload-input').forEach(function(input) {
        var box = input.closest('.file-upload-box');
        var inlineWrap = input.closest('.file-upload-inline');
        var filenameEl = box ? box.querySelector('.file-upload-filename') : null;
        var inlineNameEl = inlineWrap ? inlineWrap.querySelector('.file-upload-inline-filename') : null;
        if (!box && !inlineWrap) return;

        function showFileName(files) {
            if (!files || !files.length) return;
            var name = files[0].name;
            var size = (files[0].size / 1024).toFixed(1);
            var label = name + ' (' + size + ' KB)';
            if (filenameEl) {
                filenameEl.textContent = label;
                box.classList.add('has-file');
            }
            if (inlineNameEl) {
                inlineNameEl.textContent = label;
            }
        }

        input.addEventListener('change', function() { showFileName(this.files); });

        if (box) {
            ['dragenter', 'dragover'].forEach(function(ev) {
                box.addEventListener(ev, function(e) { e.preventDefault(); e.stopPropagation(); box.classList.add('dragover'); });
            });
            ['dragleave', 'drop'].forEach(function(ev) {
                box.addEventListener(ev, function(e) { e.preventDefault(); e.stopPropagation(); box.classList.remove('dragover'); });
            });
            box.addEventListener('drop', function(e) {
                if (e.dataTransfer.files.length) {
                    input.files = e.dataTransfer.files;
                    showFileName(e.dataTransfer.files);
                }
            });
        }
    });
});

// ═══════════════════════════════════════
// SEARCHABLE SELECT
// ═══════════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {
    var chevron = '<svg class="search-select-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>';
    var checkSvg = '<svg class="check-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
    var searchSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>';

    document.querySelectorAll('select:not(.search-select-original)').forEach(function(sel) {
        try {
            if (sel.closest('.search-select')) return;
            if (sel.options.length < 8) return;
            if (sel.multiple) return;

            var wrap = document.createElement('div');
        wrap.className = 'search-select';
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);
        sel.classList.add('search-select-original');

        var trigger = document.createElement('div');
        trigger.className = 'search-select-trigger';
        trigger.innerHTML = '<span class="placeholder">Pilih...</span>' + chevron;
        wrap.appendChild(trigger);

        var dd = document.createElement('div');
        dd.className = 'search-select-dropdown';
        wrap.appendChild(dd);

        var searchBox = document.createElement('div');
        searchBox.className = 'search-select-search';
        searchBox.innerHTML = searchSvg + '<input type="text" placeholder="Cari...">';
        dd.appendChild(searchBox);

        var optList = document.createElement('div');
        optList.className = 'search-select-options';
        dd.appendChild(optList);

        var items = [];

        function openDropdown() {
            dd.classList.add('open');
            trigger.classList.add('open');
            var input = searchBox.querySelector('input');
            input.value = '';
            filterItems('');
            setTimeout(function() { input.focus(); }, 30);
        }

        function closeDropdown() {
            dd.classList.remove('open');
            trigger.classList.remove('open');
        }

        function isOpen() {
            return dd.classList.contains('open');
        }

        function filterItems(q) {
            var lc = q.toLowerCase();
            var anyVisible = false;
            items.forEach(function(item) {
                var match = lc === '' || item.text.indexOf(lc) !== -1;
                item.el.style.display = match ? '' : 'none';
                if (match) anyVisible = true;
            });
            var empty = optList.querySelector('.search-select-empty');
            if (!anyVisible && !empty) {
                var e = document.createElement('div');
                e.className = 'search-select-empty';
                e.textContent = 'Tidak ditemukan';
                optList.appendChild(e);
            } else if (anyVisible && empty) {
                empty.remove();
            }
        }

        function buildItems() {
            optList.innerHTML = '';
            items = [];
            for (var i = 0; i < sel.options.length; i++) {
                var opt = sel.options[i];
                if (opt.disabled && opt.value === '') continue;
                var div = document.createElement('div');
                div.className = 'search-select-option';
                div.dataset.value = opt.value;
                div.innerHTML = checkSvg + '<span>' + opt.textContent + '</span>';
                if (opt.selected) {
                    div.classList.add('selected');
                    trigger.querySelector('span').textContent = opt.textContent;
                }
                (function(optionEl) {
                    optionEl.addEventListener('click', function() {
                        sel.value = this.dataset.value;
                        optList.querySelectorAll('.search-select-option').forEach(function(o) { o.classList.remove('selected'); });
                        this.classList.add('selected');
                        trigger.querySelector('span').textContent = this.querySelector('span').textContent;
                        closeDropdown();
                        sel.dispatchEvent(new Event('change', {bubbles: true}));
                    });
                })(div);
                optList.appendChild(div);
                items.push({el: div, text: opt.textContent.toLowerCase()});
            }
        }
        buildItems();

        searchBox.querySelector('input').addEventListener('input', function() {
            filterItems(this.value);
        });
        searchBox.querySelector('input').addEventListener('click', function(e) {
            e.stopPropagation();
        });

        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (isOpen()) {
                closeDropdown();
            } else {
                document.querySelectorAll('.search-select-dropdown').forEach(function(d) { d.classList.remove('open'); });
                document.querySelectorAll('.search-select-trigger.open').forEach(function(t) { t.classList.remove('open'); });
                openDropdown();
            }
        });

        dd.addEventListener('click', function(e) {
            e.stopPropagation();
        });
        } catch (err) {
            console.warn('Search select skipped:', sel.name || sel.id, err);
        }
    });

    document.addEventListener('click', function() {
        document.querySelectorAll('.search-select-dropdown').forEach(function(d) { d.classList.remove('open'); });
        document.querySelectorAll('.search-select-trigger.open').forEach(function(t) { t.classList.remove('open'); });
    });
});
