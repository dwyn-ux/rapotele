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
});
