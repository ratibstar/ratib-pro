(function () {
    try {
        var root = document.documentElement;
        var isPortal = root.getAttribute('data-portal-layout') === '1'
            || root.getAttribute('data-career-layout') === '1';
        var key = isPortal ? 'rateb_portal_theme' : 'rateb_mkt_theme';
        var saved = localStorage.getItem(key);
        var mode = saved || (isPortal ? 'auto' : 'light');
        if (mode === 'auto') {
            mode = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
        }
        var bs = mode === 'dark' ? 'dark' : 'light';
        root.setAttribute('data-theme', mode);
        root.setAttribute('data-bs-theme', bs);
    } catch (e) {}
})();
