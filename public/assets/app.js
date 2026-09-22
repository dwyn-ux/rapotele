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

    /* ── Command Palette Ctrl+K (page search) ────────────── */
    const palette = document.getElementById('cmd-palette');
    const searchInput = document.getElementById('cmd-palette-input');
    const paletteList = document.getElementById('cmd-palette-list');
    const paletteDataEl = document.getElementById('cmd-palette-data');
    if (palette && searchInput && paletteList && paletteDataEl) {
        let pages = [];
        try {
            pages = JSON.parse(paletteDataEl.textContent || '[]');
        } catch (err) {
            pages = [];
        }
        let activeIndex = -1;

        const closePalette = () => {
            paletteList.hidden = true;
            searchInput.setAttribute('aria-expanded', 'false');
            activeIndex = -1;
        };
        const openPalette = () => {
            renderPalette(searchInput.value);
            paletteList.hidden = false;
            searchInput.setAttribute('aria-expanded', 'true');
        };
        const norm = (s) => (s || '').toLowerCase();
        function renderPalette(q) {
            const query = norm(q).trim();
            const matches = pages.filter((p) => query === ''
                || norm(p.label).includes(query)
                || norm(p.group).includes(query)).slice(0, 12);
            paletteList.innerHTML = '';
            activeIndex = matches.length > 0 ? 0 : -1;
            if (!matches.length) {
                const empty = document.createElement('div');
                empty.className = 'cmd-palette-empty';
                empty.textContent = query === '' ? 'Ketik untuk mencari halaman...' : 'Halaman tidak ditemukan';
                paletteList.appendChild(empty);
                return;
            }
            matches.forEach((p, i) => {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'cmd-palette-item' + (i === activeIndex ? ' active' : '');
                item.setAttribute('role', 'option');
                const label = document.createElement('span');
                label.textContent = p.label;
                const group = document.createElement('small');
                group.textContent = p.group;
                item.append(label, group);
                item.addEventListener('click', () => {
                    window.location.href = p.url;
                });
                item.addEventListener('mousemove', () => {
                    setActive(i);
                });
                paletteList.appendChild(item);
            });
        }
        const setActive = (i) => {
            const items = Array.from(paletteList.querySelectorAll('.cmd-palette-item'));
            if (!items.length) return;
            activeIndex = ((i % items.length) + items.length) % items.length;
            items.forEach((el, j) => el.classList.toggle('active', j === activeIndex));
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        };
        const goActive = () => {
            const items = Array.from(paletteList.querySelectorAll('.cmd-palette-item'));
            if (activeIndex >= 0 && items[activeIndex]) items[activeIndex].click();
        };
        searchInput.addEventListener('focus', openPalette);
        searchInput.addEventListener('input', () => {
            openPalette();
        });
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                openPalette();
                setActive(activeIndex + 1);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActive(activeIndex - 1);
            } else if (e.key === 'Enter') {
                if (!paletteList.hidden) {
                    e.preventDefault();
                    goActive();
                }
            } else if (e.key === 'Escape') {
                closePalette();
                searchInput.blur();
            }
        });
        document.addEventListener('click', (e) => {
            if (!palette.contains(e.target)) closePalette();
        });
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
                openPalette();
            }
            if (e.key === 'Escape') closePalette();
        });
    }

    /* ── Toast Notifications ─────────────────────────────── */
    const notificationIcons = {
        success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
        danger: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
        warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
    };
    const notificationTitles = {
        success: 'Berhasil',
        danger: 'Terjadi kesalahan',
        warning: 'Perlu perhatian',
        info: 'Informasi',
    };

    window.showToast = function(message, type = 'info', duration = 5000) {
        type = type === 'error' ? 'danger' : type;
        if (!notificationIcons[type]) type = 'info';

        let container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'toast ' + type;
        toast.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        toast.innerHTML = '<div class="toast-icon">' + notificationIcons[type] + '</div>'
            + '<div class="toast-content"><strong class="toast-title"></strong><p class="toast-message"></p></div>'
            + '<button type="button" class="toast-close" aria-label="Tutup notifikasi">'
            + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
            + '</button><span class="toast-progress" aria-hidden="true"></span>';
        toast.querySelector('.toast-title').textContent = notificationTitles[type];
        toast.querySelector('.toast-message').textContent = String(message);
        toast.style.setProperty('--toast-duration', Math.max(0, duration) + 'ms');
        container.appendChild(toast);

        let dismissed = false;
        let timeoutId = null;
        const dismiss = () => {
            if (dismissed) return;
            dismissed = true;
            if (timeoutId) window.clearTimeout(timeoutId);
            toast.classList.add('is-leaving');
            window.setTimeout(() => {
                toast.remove();
                if (!container.children.length) container.remove();
            }, 220);
        };
        toast.querySelector('.toast-close').addEventListener('click', dismiss);
        if (duration > 0) timeoutId = window.setTimeout(dismiss, duration);
        else toast.querySelector('.toast-progress').remove();

        return toast;
    };

    let closeActiveConfirm = null;
    window.showConfirm = function(options = {}) {
        if (typeof options === 'string') options = { message: options };
        const tone = ['danger', 'warning', 'primary'].includes(options.tone) ? options.tone : 'danger';
        const title = options.title || (tone === 'danger' ? 'Konfirmasi tindakan' : 'Lanjutkan proses?');
        const message = options.message || 'Apakah Anda yakin ingin melanjutkan?';
        const confirmLabel = options.confirmLabel || (tone === 'danger' ? 'Ya, lanjutkan' : 'Lanjutkan');
        const cancelLabel = options.cancelLabel || 'Batal';

        return new Promise((resolve) => {
            if (closeActiveConfirm) closeActiveConfirm(false);

            const previousFocus = document.activeElement;
            const backdrop = document.createElement('div');
            backdrop.className = 'app-confirm-backdrop';
            backdrop.innerHTML = '<section class="app-confirm-dialog ' + tone + '" role="alertdialog" aria-modal="true" aria-labelledby="app-confirm-title" aria-describedby="app-confirm-message">'
                + '<div class="app-confirm-icon">' + notificationIcons[tone === 'primary' ? 'info' : tone] + '</div>'
                + '<div class="app-confirm-copy"><h2 id="app-confirm-title"></h2><p id="app-confirm-message"></p></div>'
                + '<div class="app-confirm-actions"><button type="button" class="button app-confirm-cancel"></button><button type="button" class="button app-confirm-accept"></button></div>'
                + '</section>';

            const dialog = backdrop.querySelector('.app-confirm-dialog');
            const cancelButton = backdrop.querySelector('.app-confirm-cancel');
            const confirmButton = backdrop.querySelector('.app-confirm-accept');
            backdrop.querySelector('#app-confirm-title').textContent = title;
            backdrop.querySelector('#app-confirm-message').textContent = message;
            cancelButton.textContent = cancelLabel;
            confirmButton.textContent = confirmLabel;
            confirmButton.classList.add(tone === 'primary' ? 'primary' : tone);

            let finished = false;
            const finish = (result) => {
                if (finished) return;
                finished = true;
                document.removeEventListener('keydown', handleKeydown);
                document.body.classList.remove('app-dialog-open');
                backdrop.classList.add('is-closing');
                closeActiveConfirm = null;
                window.setTimeout(() => backdrop.remove(), 180);
                if (previousFocus && typeof previousFocus.focus === 'function') previousFocus.focus();
                resolve(result);
            };
            const handleKeydown = (event) => {
                if (event.key === 'Escape') {
                    event.preventDefault();
                    finish(false);
                    return;
                }
                if (event.key !== 'Tab') return;
                const focusable = [cancelButton, confirmButton];
                const currentIndex = focusable.indexOf(document.activeElement);
                const nextIndex = event.shiftKey
                    ? (currentIndex <= 0 ? focusable.length - 1 : currentIndex - 1)
                    : (currentIndex >= focusable.length - 1 ? 0 : currentIndex + 1);
                event.preventDefault();
                focusable[nextIndex].focus();
            };

            closeActiveConfirm = finish;
            cancelButton.addEventListener('click', () => finish(false));
            confirmButton.addEventListener('click', () => finish(true));
            backdrop.addEventListener('mousedown', (event) => {
                if (event.target === backdrop) finish(false);
            });
            dialog.addEventListener('mousedown', (event) => event.stopPropagation());
            document.addEventListener('keydown', handleKeydown);
            document.body.appendChild(backdrop);
            document.body.classList.add('app-dialog-open');
            cancelButton.focus();
        });
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        if (form.dataset.appConfirmApproved === 'true') {
            delete form.dataset.appConfirmApproved;
            return;
        }

        const submitter = event.submitter;
        const source = submitter && submitter.dataset.confirm ? submitter : form;
        if (!source.dataset.confirm) return;

        event.preventDefault();
        window.showConfirm({
            message: source.dataset.confirm,
            title: source.dataset.confirmTitle,
            confirmLabel: source.dataset.confirmLabel,
            cancelLabel: source.dataset.confirmCancelLabel,
            tone: source.dataset.confirmTone,
        }).then((confirmed) => {
            if (!confirmed || !form.isConnected) return;
            form.dataset.appConfirmApproved = 'true';
            if (submitter && submitter.form === form && !submitter.disabled) form.requestSubmit(submitter);
            else form.requestSubmit();
        });
    });

    document.querySelectorAll('.app-flash-message').forEach((flash) => {
        window.showToast(flash.dataset.message || '', flash.dataset.type || 'info');
        flash.remove();
    });
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
