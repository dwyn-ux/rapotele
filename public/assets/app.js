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

    /* ── Sidebar Toggle & Collapse ────────────────────────── */
    const STORAGE_SIDEBAR_COLLAPSED = 'sidebar_collapsed';
    const isDesktop = () => window.matchMedia('(min-width: 981px)').matches;

    // Apply saved desktop collapsed state
    if (isDesktop() && localStorage.getItem(STORAGE_SIDEBAR_COLLAPSED) === '1') {
        document.body.classList.add('sidebar-collapsed');
        document.documentElement.classList.add('sidebar-collapsed');
    }

    const setMobileSidebarState = (open) => {
        document.body.classList.toggle('sidebar-open', open);
        document.querySelectorAll('[data-sidebar-open], [data-sidebar-toggle]').forEach((button) => {
            button.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    };

    const toggleSidebar = () => {
        if (isDesktop()) {
            const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
            document.documentElement.classList.toggle('sidebar-collapsed', isCollapsed);
            try {
                localStorage.setItem(STORAGE_SIDEBAR_COLLAPSED, isCollapsed ? '1' : '0');
            } catch (e) {}
        } else {
            const isOpen = !document.body.classList.contains('sidebar-open');
            setMobileSidebarState(isOpen);
        }
    };

    const toggleButtons = Array.from(document.querySelectorAll('[data-sidebar-toggle], [data-sidebar-open]'));
    toggleButtons.forEach((button) => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            toggleSidebar();
        });
    });

    document.querySelectorAll('[data-sidebar-close]').forEach((button) => {
        button.addEventListener('click', () => setMobileSidebarState(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setMobileSidebarState(false);
            const dropdownMenu = document.getElementById('user-dropdown-menu');
            if (dropdownMenu) dropdownMenu.classList.remove('open');
        } else if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'b') {
            const tag = (event.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || event.target.isContentEditable) {
                return;
            }
            event.preventDefault();
            toggleSidebar();
        }
    });

    document.querySelectorAll('.sidebar .menu a').forEach((link) => {
        link.addEventListener('click', () => {
            if (!isDesktop()) {
                setMobileSidebarState(false);
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

    /* ── App CRUD Modal Pop-up System ─────────────────────── */
    window.openCrudModal = function(modalEl) {
        if (!modalEl) modalEl = document.getElementById('crud-modal-backdrop') || document.querySelector('.app-modal-backdrop');
        if (!modalEl) return;
        modalEl.hidden = false;
        modalEl.classList.remove('is-closing');
        modalEl.classList.add('is-open');
        const dialog = modalEl.querySelector('.app-modal-dialog') || modalEl;
        dialog.classList.add('is-open');
        const bodyEl = modalEl.querySelector('#crud-modal-body, .app-modal-body');
        if (bodyEl) {
            bodyEl.style.display = 'block';
            bodyEl.style.visibility = 'visible';
            bodyEl.style.opacity = '1';
        }
        document.body.classList.add('app-modal-open');
        const firstInput = modalEl.querySelector('input:not([type="hidden"]), select, textarea');
        if (firstInput) setTimeout(() => firstInput.focus(), 60);
    };

    window.closeCrudModal = function(modalEl) {
        if (!modalEl) modalEl = document.getElementById('crud-modal-backdrop') || document.querySelector('.app-modal-backdrop');
        if (!modalEl) return;
        modalEl.classList.add('is-closing');
        window.setTimeout(() => {
            modalEl.hidden = true;
            modalEl.classList.remove('is-open', 'is-closing');
            document.body.classList.remove('app-modal-open');
        }, 180);
    };

    function initModalFormScripts(container) {
        container.querySelectorAll('script').forEach((oldScript) => {
            const newScript = document.createElement('script');
            Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
            newScript.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons();
        }
    }

    function applyTableSearch(input) {
        if (!input) return;
        const panel = input.closest('.table-panel');
        if (!panel) return;
        const tbody = panel.querySelector('tbody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr'));
        const emptyRow = rows.find((r) => r.querySelector('.empty'));
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
    }

    function refreshCurrentTable() {
        const searchInput = document.querySelector('.table-search-input');
        const savedQuery = searchInput ? searchInput.value : '';

        fetch(window.location.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTableWrap = doc.querySelector('.table-panel .table-wrap');
            const currentTableWrap = document.querySelector('.table-panel .table-wrap');

            if (newTableWrap && currentTableWrap) {
                currentTableWrap.innerHTML = newTableWrap.innerHTML;
            }

            if (window.lucide && typeof window.lucide.createIcons === 'function') {
                window.lucide.createIcons();
            }

            if (searchInput) {
                searchInput.value = savedQuery;
                applyTableSearch(searchInput);
            }
        })
        .catch(err => console.warn('Gagal me-refresh tabel:', err));
    }

    // Cache initial form content on page load
    const initialModalBackdrop = document.getElementById('crud-modal-backdrop') || document.querySelector('.app-modal-backdrop');
    if (initialModalBackdrop) {
        const bodyEl = initialModalBackdrop.querySelector('#crud-modal-body, .app-modal-body');
        const titleEl = initialModalBackdrop.querySelector('#crud-modal-title');
        if (bodyEl && !initialModalBackdrop.classList.contains('is-open')) {
            initialModalBackdrop.dataset.initialHtml = bodyEl.innerHTML;
        }
        if (titleEl) {
            initialModalBackdrop.dataset.initialTitle = titleEl.textContent;
        }
        if (initialModalBackdrop.classList.contains('is-open') && !initialModalBackdrop.hasAttribute('hidden')) {
            document.body.classList.add('app-modal-open');
        }
    }

    // Trigger Tambah button -> Open modal with fresh form
    document.addEventListener('click', (event) => {
        const addBtn = event.target.closest('.app-modal-trigger-add, .input-panel-toggle');
        if (!addBtn) return;
        event.preventDefault();
        const modalEl = document.getElementById('crud-modal-backdrop') || document.querySelector('.app-modal-backdrop');
        if (!modalEl) return;

        const titleEl = modalEl.querySelector('#crud-modal-title');
        const bodyEl = modalEl.querySelector('#crud-modal-body, .app-modal-body');
        const defaultTitle = addBtn.dataset.titleAdd || (titleEl ? titleEl.dataset.defaultTitle : '') || modalEl.dataset.initialTitle || 'Tambah Data';

        if (titleEl) titleEl.textContent = defaultTitle;

        if (modalEl.dataset.initialHtml && bodyEl) {
            bodyEl.innerHTML = modalEl.dataset.initialHtml;
            bodyEl.style.display = 'block';
            bodyEl.style.visibility = 'visible';
            bodyEl.style.opacity = '1';
            initModalFormScripts(bodyEl);
        } else if (bodyEl) {
            bodyEl.style.display = 'block';
            bodyEl.style.visibility = 'visible';
            bodyEl.style.opacity = '1';
            const form = bodyEl.querySelector('form');
            if (form) form.reset();
            const idInput = bodyEl.querySelector('input[name="id"]');
            if (idInput) idInput.value = '0';
        }

        window.openCrudModal(modalEl);
    });

    // Trigger Edit button in table -> Load data & Open modal
    document.addEventListener('click', (event) => {
        const editLink = event.target.closest('a[data-crud-edit], .app-btn-edit, .table-panel table a[href*="edit="], .table-panel table a[href*="edit_reward="]');
        if (!editLink) return;
        if (editLink.hasAttribute('download') || editLink.target === '_blank') return;

        const modalEl = document.getElementById('crud-modal-backdrop') || document.querySelector('.app-modal-backdrop');
        if (!modalEl) return;

        event.preventDefault();
        const titleEl = modalEl.querySelector('#crud-modal-title');
        const bodyEl = modalEl.querySelector('#crud-modal-body, .app-modal-body');

        if (!modalEl.dataset.initialHtml && bodyEl) {
            modalEl.dataset.initialHtml = bodyEl.innerHTML;
        }

        if (titleEl) titleEl.textContent = 'Memuat form edit...';
        if (bodyEl) {
            bodyEl.innerHTML = '<div class="app-modal-loading"><div class="app-spinner"></div><div>Mengambil data untuk diedit...</div></div>';
            bodyEl.style.display = 'block';
            bodyEl.style.visibility = 'visible';
            bodyEl.style.opacity = '1';
        }

        window.openCrudModal(modalEl);

        fetch(editLink.href, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.text();
        })
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newBody = doc.getElementById('crud-modal-body') || doc.querySelector('.app-modal-body') || doc.querySelector('.input-panel-body') || doc.querySelector('#form-data form');
            const newTitle = doc.getElementById('crud-modal-title') || doc.querySelector('.panel-title h2');

            if (newTitle && titleEl) {
                titleEl.textContent = newTitle.textContent.trim();
            } else if (titleEl) {
                titleEl.textContent = 'Edit Data';
            }

            if (newBody && bodyEl) {
                bodyEl.innerHTML = newBody.innerHTML;
                bodyEl.style.display = 'block';
                bodyEl.style.visibility = 'visible';
                bodyEl.style.opacity = '1';
                initModalFormScripts(bodyEl);
            } else {
                throw new Error('Form tidak ditemukan pada halaman.');
            }
        })
        .catch(err => {
            if (bodyEl) {
                bodyEl.innerHTML = '<div style="text-align:center;padding:24px;color:var(--danger);"><p>Gagal memuat form: ' + err.message + '</p><button type="button" class="button" onclick="closeCrudModal()">Tutup</button></div>';
            }
        });
    });

    // Close modal triggers
    document.addEventListener('click', (event) => {
        // Close button (X)
        if (event.target.closest('.app-modal-close')) {
            event.preventDefault();
            window.closeCrudModal();
            return;
        }
        // Cancel/Reset link inside modal
        const cancelBtn = event.target.closest('#crud-modal-backdrop .app-modal-cancel, #crud-modal-backdrop a.button, .app-modal-backdrop a.button');
        if (cancelBtn) {
            const isSubmit = cancelBtn.type === 'submit' || cancelBtn.classList.contains('primary');
            if (!isSubmit) {
                event.preventDefault();
                window.closeCrudModal();
                return;
            }
        }
        // Backdrop click outside dialog
        const modalBackdrop = event.target.closest('.app-modal-backdrop');
        if (modalBackdrop && event.target === modalBackdrop) {
            window.closeCrudModal(modalBackdrop);
        }
    });

    // Close on Escape key
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            const openModal = document.querySelector('.app-modal-backdrop.is-open:not([hidden])');
            if (openModal) {
                event.preventDefault();
                window.closeCrudModal(openModal);
            }
        }
    });

    // Form submission inside modal via AJAX (Save Tambah / Edit)
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        const modalEl = form.closest('#crud-modal-backdrop, .app-modal-backdrop');
        if (!modalEl) return;

        event.preventDefault();
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"], .actions button.primary') || event.submitter;
        const origBtnHtml = submitBtn ? submitBtn.innerHTML : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="app-spinner" style="width:14px;height:14px;border-width:2px;display:inline-block;vertical-align:middle;margin-right:6px;"></span> Menyimpan...';
        }

        const formData = new FormData(form);
        formData.append('_ajax', '1');

        fetch(form.action || window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(async (res) => {
            const contentType = res.headers.get('content-type') || '';
            let data = null;
            if (contentType.includes('application/json')) {
                data = await res.json();
            } else {
                data = { ok: res.ok };
            }
            return { status: res.status, ok: res.ok, data };
        })
        .then(({ status, ok, data }) => {
            if (!ok || (data && data.ok === false)) {
                const errMsg = (data && data.error) ? data.error : 'Terjadi kesalahan saat menyimpan data.';
                if (typeof window.showToast === 'function') {
                    window.showToast(errMsg, 'danger');
                } else {
                    alert(errMsg);
                }
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origBtnHtml;
                }
                return;
            }

            // SUCCESS! Close modal and refresh table while keeping search intact
            window.closeCrudModal(modalEl);

            if (data && Array.isArray(data.flash) && data.flash.length > 0) {
                data.flash.forEach(f => {
                    const tone = (f.type === 'error' || f.type === 'danger') ? 'danger' : (f.type || 'success');
                    if (typeof window.showToast === 'function') {
                        window.showToast(f.message, tone);
                    }
                });
            } else {
                if (typeof window.showToast === 'function') {
                    window.showToast('Data berhasil disimpan.', 'success');
                }
            }

            refreshCurrentTable();
        })
        .catch(err => {
            console.error(err);
            if (typeof window.showToast === 'function') {
                window.showToast('Gagal menghubungi server: ' + err.message, 'danger');
            }
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = origBtnHtml;
            }
        });
    });

    // Delete row via AJAX (so deleting doesn't reload page and wipe search text)
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        const isInTable = form.closest('.table-panel table, .row-actions');
        const isDelete = form.classList.contains('app-form-delete') || form.querySelector('input[name="action"][value*="delete_"]');
        if (!isInTable || !isDelete) return;

        // If data-confirm is not yet approved, let confirm dialog handle it first
        if (form.dataset.confirm && form.dataset.appConfirmApproved !== 'true') {
            return;
        }

        event.preventDefault();
        const row = form.closest('tr');
        const formData = new FormData(form);
        formData.append('_ajax', '1');

        fetch(form.action || window.location.href, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(res => res.json().catch(() => ({ ok: res.ok })))
        .then(data => {
            if (data.ok === false) {
                window.showToast(data.error || 'Gagal menghapus data.', 'danger');
                return;
            }
            if (data.flash && Array.isArray(data.flash) && data.flash.length > 0) {
                data.flash.forEach(f => window.showToast(f.message, (f.type === 'error' || f.type === 'danger') ? 'danger' : (f.type || 'success')));
            } else {
                window.showToast('Data berhasil dihapus.', 'success');
            }
            if (row) {
                row.style.transition = 'opacity 0.22s, transform 0.22s';
                row.style.opacity = '0';
                row.style.transform = 'scale(0.96)';
                setTimeout(() => {
                    row.remove();
                    const searchInput = document.querySelector('.table-search-input');
                    if (searchInput) applyTableSearch(searchInput);
                }, 220);
            } else {
                refreshCurrentTable();
            }
        })
        .catch(err => {
            window.showToast('Gagal menghapus: ' + err.message, 'danger');
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
        input.addEventListener('input', () => applyTableSearch(input));
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

    function initSearchableSelect(sel) {
        if (sel.classList.contains('search-select-original')) return;
        if (sel.closest('.search-select')) return;
        if (sel.classList.contains('no-search-select') || sel.dataset.noSearch !== undefined || sel.classList.contains('matrix-activity-select') || sel.classList.contains('matrix-slot-select')) return;
        if (sel.options.length < 8) return;
        if (sel.multiple) return;

        var wrap = document.createElement('div');
        wrap.className = 'search-select';
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);
        sel.classList.add('search-select-original');

        var trigger = document.createElement('div');
        trigger.className = 'search-select-trigger';
        trigger.setAttribute('tabindex', '0');
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.innerHTML = '<span class="placeholder">Pilih...</span>' + chevron;
        wrap.appendChild(trigger);

        var dd = document.createElement('div');
        dd.className = 'search-select-dropdown';
        wrap.appendChild(dd);

        var searchBox = document.createElement('div');
        searchBox.className = 'search-select-search';
        searchBox.innerHTML = searchSvg + '<input type="text" placeholder="Cari..." autocomplete="off" spellcheck="false">';
        dd.appendChild(searchBox);

        var searchInput = searchBox.querySelector('input');

        var optList = document.createElement('div');
        optList.className = 'search-select-options';
        optList.setAttribute('role', 'listbox');
        dd.appendChild(optList);

        var items = [];
        var activeIndex = -1;

        function getVisibleItems() {
            return items.filter(function(item) {
                return item.el.style.display !== 'none';
            });
        }

        function scrollOptionIntoView(el) {
            if (!el || !optList) return;
            var elTop = el.offsetTop;
            var elBottom = elTop + el.offsetHeight;
            var listTop = optList.scrollTop;
            var listBottom = listTop + optList.clientHeight;
            if (elTop < listTop) {
                optList.scrollTop = elTop;
            } else if (elBottom > listBottom) {
                optList.scrollTop = elBottom - optList.clientHeight;
            }
        }

        function setActive(index, scroll) {
            var visible = getVisibleItems();
            if (!visible.length) {
                activeIndex = -1;
                items.forEach(function(item) { item.el.classList.remove('active'); });
                return;
            }
            if (index < 0) {
                index = visible.length - 1;
            } else if (index >= visible.length) {
                index = 0;
            }
            activeIndex = index;
            items.forEach(function(item) { item.el.classList.remove('active'); });
            var activeEl = visible[activeIndex].el;
            activeEl.classList.add('active');
            if (scroll) {
                scrollOptionIntoView(activeEl);
            }
        }

        function selectOption(optionEl) {
            if (!optionEl) return;
            var val = optionEl.dataset.value;
            sel.value = val;
            optList.querySelectorAll('.search-select-option').forEach(function(o) {
                o.classList.remove('selected');
            });
            optionEl.classList.add('selected');
            var triggerSpan = trigger.querySelector('span');
            var text = optionEl.querySelector('span').textContent;
            triggerSpan.textContent = text;
            if (val === '') {
                triggerSpan.classList.add('placeholder');
            } else {
                triggerSpan.classList.remove('placeholder');
            }
            closeDropdown();
            sel.dispatchEvent(new Event('change', { bubbles: true }));
            trigger.focus();
        }

        function syncFromSelect() {
            var currentVal = sel.value;
            var matchedOption = null;
            optList.querySelectorAll('.search-select-option').forEach(function(o) {
                if (o.dataset.value === currentVal) {
                    o.classList.add('selected');
                    matchedOption = o;
                } else {
                    o.classList.remove('selected');
                }
            });
            var triggerSpan = trigger.querySelector('span');
            if (matchedOption) {
                triggerSpan.textContent = matchedOption.querySelector('span').textContent;
                triggerSpan.classList.toggle('placeholder', currentVal === '');
            } else {
                var opt = sel.selectedOptions && sel.selectedOptions[0];
                triggerSpan.textContent = opt ? opt.textContent : 'Pilih...';
                triggerSpan.classList.toggle('placeholder', !opt || !opt.value);
            }
        }

        function openDropdown() {
            document.querySelectorAll('.search-select-dropdown.open').forEach(function(d) {
                if (d !== dd) d.classList.remove('open');
            });
            document.querySelectorAll('.search-select-trigger.open').forEach(function(t) {
                if (t !== trigger) {
                    t.classList.remove('open');
                    t.setAttribute('aria-expanded', 'false');
                }
            });

            dd.classList.add('open');
            trigger.classList.add('open');
            trigger.setAttribute('aria-expanded', 'true');
            searchInput.value = '';
            filterItems('');
            searchInput.focus();
            setTimeout(function() { searchInput.focus(); }, 30);
        }

        function closeDropdown() {
            dd.classList.remove('open');
            trigger.classList.remove('open');
            trigger.setAttribute('aria-expanded', 'false');
            activeIndex = -1;
            items.forEach(function(item) { item.el.classList.remove('active'); });
        }

        function isOpen() {
            return dd.classList.contains('open');
        }

        function filterItems(q) {
            var lc = (q || '').toLowerCase().trim();
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

            var visible = getVisibleItems();
            if (visible.length > 0) {
                var targetIdx = 0;
                if (lc === '') {
                    var selectedIdx = visible.findIndex(function(item) {
                        return item.el.classList.contains('selected');
                    });
                    if (selectedIdx >= 0) {
                        targetIdx = selectedIdx;
                    }
                }
                setActive(targetIdx, true);
            } else {
                setActive(-1, false);
            }
        }

        function buildItems() {
            optList.innerHTML = '';
            items = [];
            var selectedFound = false;
            var triggerSpan = trigger.querySelector('span');

            for (var i = 0; i < sel.options.length; i++) {
                var opt = sel.options[i];
                if (opt.disabled && opt.value === '') continue;
                var div = document.createElement('div');
                div.className = 'search-select-option';
                div.setAttribute('role', 'option');
                div.dataset.value = opt.value;
                div.innerHTML = checkSvg + '<span>' + opt.textContent + '</span>';
                if (opt.selected) {
                    div.classList.add('selected');
                    triggerSpan.textContent = opt.textContent;
                    triggerSpan.classList.toggle('placeholder', opt.value === '');
                    selectedFound = true;
                }
                (function(optionEl) {
                    optionEl.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        selectOption(this);
                    });
                    optionEl.addEventListener('mousemove', function() {
                        var visible = getVisibleItems();
                        var idx = visible.findIndex(function(it) { return it.el === optionEl; });
                        if (idx !== -1 && idx !== activeIndex) {
                            setActive(idx, false);
                        }
                    });
                })(div);
                optList.appendChild(div);
                items.push({ el: div, text: opt.textContent.toLowerCase() });
            }

            if (!selectedFound) {
                var first = sel.options[0];
                triggerSpan.textContent = first ? first.textContent : 'Pilih...';
                triggerSpan.classList.add('placeholder');
            }
        }

        buildItems();

        sel.addEventListener('change', syncFromSelect);

        if (sel.form) {
            sel.form.addEventListener('reset', function() {
                setTimeout(syncFromSelect, 10);
            });
        }

        if (window.MutationObserver) {
            var observer = new MutationObserver(function() {
                buildItems();
            });
            observer.observe(sel, { childList: true });
        }

        searchInput.addEventListener('input', function() {
            filterItems(this.value);
        });

        searchInput.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                setActive(activeIndex + 1, true);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setActive(activeIndex - 1, true);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                var visible = getVisibleItems();
                if (activeIndex >= 0 && activeIndex < visible.length) {
                    selectOption(visible[activeIndex].el);
                } else if (visible.length > 0) {
                    selectOption(visible[0].el);
                }
            } else if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                closeDropdown();
                trigger.focus();
            } else if (e.key === 'Tab') {
                closeDropdown();
            }
        });

        trigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (isOpen()) {
                closeDropdown();
            } else {
                openDropdown();
            }
        });

        trigger.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                e.stopPropagation();
                if (!isOpen()) {
                    openDropdown();
                }
            } else if (e.key === 'Escape') {
                if (isOpen()) {
                    e.preventDefault();
                    e.stopPropagation();
                    closeDropdown();
                }
            }
        });

        dd.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    document.querySelectorAll('select:not(.search-select-original)').forEach(function(sel) {
        try {
            initSearchableSelect(sel);
        } catch (err) {
            console.warn('Search select skipped:', sel.name || sel.id, err);
        }
    });

    document.addEventListener('click', function() {
        document.querySelectorAll('.search-select-dropdown.open').forEach(function(d) {
            d.classList.remove('open');
        });
        document.querySelectorAll('.search-select-trigger.open').forEach(function(t) {
            t.classList.remove('open');
            t.setAttribute('aria-expanded', 'false');
        });
    });
});
