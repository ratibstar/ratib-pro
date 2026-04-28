/**
 * EN: Implements system administration/observability module behavior in `admin/assets/js/debug-dashboard-live.js`.
 * AR: ينفذ سلوك وحدة إدارة النظام والمراقبة في `admin/assets/js/debug-dashboard-live.js`.
 */
(function () {
    var key = 'debug_dashboard_live_mode';
    var checkbox = document.getElementById('liveMode');
    var timer = null;
    if (!checkbox) {
        return;
    }

    function startLiveMode() {
        if (timer !== null) {
            return;
        }
        timer = window.setInterval(function () { window.location.reload(); }, 5000);
    }

    function stopLiveMode() {
        if (timer === null) {
            return;
        }
        window.clearInterval(timer);
        timer = null;
    }

    var saved = window.localStorage.getItem(key);
    if (saved === '1') {
        checkbox.checked = true;
        startLiveMode();
    }

    checkbox.addEventListener('change', function () {
        if (checkbox.checked) {
            window.localStorage.setItem(key, '1');
            startLiveMode();
        } else {
            window.localStorage.setItem(key, '0');
            stopLiveMode();
        }
    });
})();
