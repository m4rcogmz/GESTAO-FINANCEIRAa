/**
 * Gestão Financeira — UI shell, temas em gráficos, toasts
 */
(function () {
    'use strict';

    const THEME_STORAGE_KEY = 'gf_theme';

    function readCssVar(name, el) {
        const root = el || document.body;
        return getComputedStyle(root).getPropertyValue(name).trim();
    }

    /** Cores derivadas do tema atual (Chart.js, etc.) */
    window.getFinanceChartTheme = function () {
        const b = document.body;
        return {
            c1: readCssVar('--chart-1', b) || readCssVar('--accent', b),
            c2: readCssVar('--chart-2', b),
            c3: readCssVar('--chart-3', b),
            c4: readCssVar('--chart-4', b),
            c5: readCssVar('--chart-5', b),
            accent: readCssVar('--accent', b),
            accentMuted: readCssVar('--accent-muted', b),
            success: readCssVar('--success', b),
            danger: readCssVar('--danger', b),
            grid: readCssVar('--border-subtle', b),
            text: readCssVar('--text-tertiary', b),
            textSecondary: readCssVar('--text-secondary', b),
        };
    };

    /** Paleta para gráficos com muitas fatias */
    window.financeChartPalette = function () {
        const t = window.getFinanceChartTheme();
        return [t.c1, t.c2, t.c3, t.c4, t.c5, t.accent, t.success, t.danger];
    };

    /** Toasts */
    window.DSToast = {
        show: function (message, type) {
            type = type || 'info';
            let wrap = document.getElementById('dsToastContainer');
            if (!wrap) {
                wrap = document.createElement('div');
                wrap.id = 'dsToastContainer';
                wrap.className = 'ds-toast-container';
                wrap.setAttribute('aria-live', 'polite');
                document.body.appendChild(wrap);
            }
            const el = document.createElement('div');
            el.className = 'ds-toast ds-toast--' + type;
            el.innerHTML = '<span>' + String(message) + '</span>';
            wrap.appendChild(el);
            setTimeout(function () {
                el.style.opacity = '0';
                el.style.transform = 'translateX(12px)';
                el.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                setTimeout(function () { el.remove(); }, 280);
            }, 4200);
        }
    };

    function applyStoredPublicTheme() {
        if (!document.body.classList.contains('public-page')) return;
        const t = localStorage.getItem(THEME_STORAGE_KEY);
        const allowed = ['dark', 'light', 'purple', 'red', 'gray'];
        if (!t || !allowed.includes(t)) return;
        allowed.forEach(function (name) {
            document.body.classList.remove('theme-' + name);
        });
        document.body.classList.add('theme-' + t);
        document.body.classList.add('public-page');
    }

    function initSidebar() {
        const body = document.body;
        const sidebar = document.getElementById('appSidebar');
        const backdrop = document.getElementById('sidebarBackdrop');
        const btnMobile = document.getElementById('btnMobileSidebar');
        const btnCollapse = document.getElementById('btnCollapseSidebar');

        if (!sidebar) return;

        function openMobile() {
            body.classList.add('app-mobile-sidebar-open');
        }
        function closeMobile() {
            body.classList.remove('app-mobile-sidebar-open');
        }

        if (btnMobile) {
            btnMobile.addEventListener('click', function () {
                if (body.classList.contains('app-mobile-sidebar-open')) closeMobile();
                else openMobile();
            });
        }
        if (backdrop) {
            backdrop.addEventListener('click', closeMobile);
        }

        if (btnCollapse) {
            btnCollapse.addEventListener('click', function () {
                body.classList.toggle('app-sidebar-collapsed');
                try {
                    localStorage.setItem('gf_sidebar_collapsed', body.classList.contains('app-sidebar-collapsed') ? '1' : '0');
                } catch (e) { /* ignore */ }
            });
            try {
                if (localStorage.getItem('gf_sidebar_collapsed') === '1') {
                    body.classList.add('app-sidebar-collapsed');
                }
            } catch (e) { /* ignore */ }
        }

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) closeMobile();
        });
    }

    /** Exportar preferência de tema (páginas públicas) */
    window.GFThemeStorage = {
        set: function (name) {
            try {
                localStorage.setItem(THEME_STORAGE_KEY, name);
            } catch (e) { /* ignore */ }
        }
    };

    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('[data-set-theme]');
        if (!trigger) return;
        e.preventDefault();
        const name = trigger.getAttribute('data-set-theme');
        const allowed = ['dark', 'light', 'purple', 'red', 'gray'];
        if (!allowed.includes(name)) return;
        try {
            localStorage.setItem('gf_theme', name);
        } catch (err) { /* ignore */ }
        allowed.forEach(function (x) {
            document.body.classList.remove('theme-' + x);
        });
        document.body.classList.add('theme-' + name);
        document.body.classList.add('public-page');
    });

    document.addEventListener('DOMContentLoaded', function () {
        applyStoredPublicTheme();
        initSidebar();
    });
})();
