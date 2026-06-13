(function () {
    try {
        var mode = localStorage.getItem('rateb_mkt_theme') || 'light';
        var bs = mode === 'dark' ? 'dark' : 'light';
        document.documentElement.setAttribute('data-theme', mode);
        document.documentElement.setAttribute('data-bs-theme', bs);
    } catch (e) {}
})();
