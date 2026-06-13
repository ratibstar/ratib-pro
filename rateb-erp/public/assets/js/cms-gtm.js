(function () {
    var el = document.currentScript;
    var id = el && el.getAttribute('data-gtm-id');
    if (!id || typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
    var first = document.getElementsByTagName('script')[0];
    var script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(id);
    if (first && first.parentNode) {
        first.parentNode.insertBefore(script, first);
    } else {
        document.head.appendChild(script);
    }
})();
