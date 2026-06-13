(function () {
    try {
        var root = document.documentElement;
        var isPortal = root.getAttribute('data-portal-layout') === '1';
        var key = isPortal ? 'rateb_portal_theme' : 'rateb_mkt_theme';
        var mode = localStorage.getItem(key) || (isPortal ? 'dark' : 'light');
        var bs = mode === 'dark' ? 'dark' : 'light';
        root.setAttribute('data-theme', mode);
        root.setAttribute('data-bs-theme', bs);
    } catch (e) {}
})();
