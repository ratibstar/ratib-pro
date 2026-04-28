/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/registration-requests-embed.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/registration-requests-embed.js`.
 */
(function() {
    var frame = document.getElementById('requestsFrame');
    if (!frame) return;
    frame.onload = function() {
        try {
            var win = this.contentWindow;
            if (win && win.document) {
                var rewrites = [
                    ['control-registration-requests.php', 'control/registration-requests.php'],
                    ['control-agencies.php', 'control/agencies.php'],
                    ['control-support-chats.php', 'control/support-chats.php'],
                    ['control/dashboard.php', 'control/dashboard.php'],
                    ['select-country.php', 'select-country.php'],
                    ['dashboard-accounting.php', 'control/accounting.php']
                ];
                var anchors = win.document.querySelectorAll('a[href]');
                for (var i = 0; i < anchors.length; i++) {
                    var link = anchors[i];
                    var href = link.getAttribute('href') || '';
                    if (href.indexOf('embedded=1') !== -1) continue;
                    for (var j = 0; j < rewrites.length; j++) {
                        var from = rewrites[j][0];
                        var to = rewrites[j][1];
                        if (href.indexOf(from) !== -1 && (href.indexOf('control') !== -1 || href.indexOf('select-country') !== -1 || href.indexOf('dashboard') !== -1)) {
                            link.setAttribute('href', href.replace(from, to).replace(/[?&]embedded=1/g, ''));
                            link.setAttribute('target', '_parent');
                            break;
                        }
                    }
                }
            }
        } catch (e) {}
    };
})();
