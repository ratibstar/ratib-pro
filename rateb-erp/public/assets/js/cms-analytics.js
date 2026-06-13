(function () {
    var el = document.currentScript;
    var id = el && el.getAttribute('data-ga-id');
    if (!id || typeof window === 'undefined') return;
    window.dataLayer = window.dataLayer || [];
    function gtag() { window.dataLayer.push(arguments); }
    window.gtag = gtag;
    gtag('js', new Date());
    gtag('config', id);
})();
