/**
 * EN: Implements control-panel module behavior and admin-country operations in `control-panel/js/control/support-chats-embed.js`.
 * AR: ينفذ سلوك وحدة لوحة التحكم وعمليات إدارة الدول في `control-panel/js/control/support-chats-embed.js`.
 */
(function() {
    var frame = document.getElementById('chatsFrame');
    if (!frame) return;
    frame.onload = function() {
        try {
            var win = this.contentWindow;
            if (win && win.document) {
                var links = win.document.querySelectorAll('a[href*="control-support-chats.php"]');
                for (var i = 0; i < links.length; i++) {
                    var link = links[i];
                    var href = link.getAttribute('href');
                    if (href && href.indexOf('embedded=1') === -1) {
                        link.setAttribute('href', href.replace('control-support-chats.php', 'control/support-chats.php'));
                        link.setAttribute('target', '_parent');
                    }
                }
            }
        } catch (e) {}
    };
})();
