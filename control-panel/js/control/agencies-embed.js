/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/agencies-embed.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/agencies-embed.js`.
 */
(function() {
    var frame = document.getElementById('agenciesFrame');
    if (!frame) return;
    frame.onload = function() {
        try {
            var win = this.contentWindow;
            if (win && win.document) {
                var links = win.document.querySelectorAll('a[href*="control-agencies.php"]');
                for (var i = 0; i < links.length; i++) {
                    var link = links[i];
                    var href = link.getAttribute('href');
                    if (href && href.indexOf('embedded=1') === -1) {
                        link.setAttribute('href', href.replace('control-agencies.php', 'control/agencies.php'));
                        link.setAttribute('target', '_parent');
                    }
                }
            }
        } catch (e) {}
    };
})();
