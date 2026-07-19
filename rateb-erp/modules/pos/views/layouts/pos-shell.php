<?php
declare(strict_types=1);

$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
$configJson = json_encode($registerConfig ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
// Phase PI — auth-lock stays on critical path only for biometric gate pages (security).
// Register / scan-ready pages load it idle after DCL so it cannot stall first scan.
$posAuthLockUrl = rateb_pos_asset('js/pos-auth-lock.js');
$posAuthLockIdle = !str_contains((string) ($pageContent ?? ''), 'data-pos-biometric-gate');
$deferScripts = [
    rateb_pos_asset('js/pos-register-motion.js'),
    rateb_pos_asset('js/pos-register-cashier.js'),
    rateb_pos_asset('js/pos-offline-bootstrap.js'),
];
if ($posAuthLockIdle) {
    // First in idle queue so vault/lock still initializes ASAP after load.
    array_unshift($deferScripts, $posAuthLockUrl);
}
$deferScriptsJson = json_encode($deferScripts, JSON_UNESCAPED_SLASHES);
?>
<!DOCTYPE html>
<html lang="<?php echo \Rateb\App\Pos\Support\PosView::escape($locale); ?>" dir="<?php echo $dir; ?>" data-theme-scope="pos" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="color-scheme" content="dark light">
    <meta name="rateb-csrf" content="<?php echo \Rateb\App\Pos\Support\PosView::escape(\Rateb\App\Core\Csrf::token()); ?>">
    <script>
    (function () {
        try {
            var mode = localStorage.getItem('rateb_pos_theme') || 'dark';
            var bs = mode === 'auto'
                ? (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                : (mode === 'light' ? 'light' : 'dark');
            document.documentElement.setAttribute('data-theme', mode);
            document.documentElement.setAttribute('data-bs-theme', bs);
        } catch (e) {}
    })();
    </script>
    <script>
    (function () {
        /* Block hard refresh while offline (Ctrl+F5 bypasses SW → black POS). */
        function offlineNow() {
            try { return navigator.onLine === false; } catch (e) { return false; }
        }
        function toast() {
            try {
                var id = 'rateb-pos-offline-reload-toast';
                var el = document.getElementById(id);
                if (!el) {
                    el = document.createElement('div');
                    el.id = id;
                    el.setAttribute('role', 'status');
                    el.style.cssText = 'position:fixed;bottom:14px;left:50%;transform:translateX(-50%);z-index:100000;'
                        + 'max-width:min(22rem,92vw);padding:10px 14px;background:#7f1d1d;color:#fee2e2;'
                        + 'font:13px/1.45 Tajawal,system-ui,sans-serif;border-radius:10px;text-align:center';
                    (document.body || document.documentElement).appendChild(el);
                }
                el.textContent = 'التحديث غير متاح دون اتصال — تبقى على شاشة البيع المحفوظة.';
                el.hidden = false;
                clearTimeout(toast._t);
                toast._t = setTimeout(function () { el.hidden = true; }, 3200);
            } catch (eT) { /* ignore */ }
        }
        window.addEventListener('keydown', function (ev) {
            if (!offlineNow()) return;
            var key = String(ev.key || ev.code || '');
            var isF5 = key === 'F5' || key === 'f5';
            var isR = key === 'r' || key === 'R' || key === 'KeyR';
            if (!isF5 && !((ev.ctrlKey || ev.metaKey) && isR)) return;
            ev.preventDefault();
            ev.stopPropagation();
            toast();
        }, true);
        try {
            var _reload = window.location.reload.bind(window.location);
            window.location.reload = function () {
                if (offlineNow()) { toast(); return; }
                return _reload.apply(window.location, arguments);
            };
        } catch (eR) { /* ignore */ }
    })();
    </script>
    <link rel="manifest" href="<?php echo rateb_public_url('pos-manifest.webmanifest'); ?>">
    <title><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? __('pos_register')); ?></title>
    <link href="<?php echo rateb_pos_asset('css/pos-register.css'); ?>" rel="stylesheet">
</head>
<body class="rateb-pos-shell">
<main class="rateb-pos-main" id="rateb-pos-app">
    <?php echo $pageContent; ?>
</main>
<script type="application/json" id="rateb-pos-register-config"><?php echo $configJson ?: '{}'; ?></script>
<script src="<?php echo rateb_asset('js/theme.js'); ?>" defer></script>
<script src="<?php echo rateb_pos_asset('js/pos-module.js'); ?>" defer></script>
<script src="<?php echo rateb_pos_asset('js/pos-offline-sync.js'); ?>" defer></script>
<script src="<?php echo rateb_pos_asset('js/pos-offline-print.js'); ?>" defer></script>
<script src="<?php echo rateb_pos_asset('js/pos-register.js'); ?>" defer></script>
<script src="<?php echo rateb_pos_asset('js/pos-register-tiles.js'); ?>" defer></script>
<script src="<?php echo rateb_pos_asset('js/pos-keyboard.js'); ?>" defer></script>
<script src="<?php echo rateb_pos_asset('js/pos-register-checkout.js'); ?>" defer></script>
<script src="<?php echo rateb_pos_asset('js/pos-register-ops.js'); ?>" defer></script>
<script src="<?php echo rateb_pos_asset('js/pos-capabilities.js'); ?>" defer></script>
<script src="<?php echo rateb_pos_asset('js/pos-supervisor-approval.js'); ?>" defer></script>
<?php if (!$posAuthLockIdle) { ?>
<script src="<?php echo $posAuthLockUrl; ?>" defer></script>
<?php } ?>
<script src="<?php echo rateb_pos_asset('js/pos-biometric-gate.js'); ?>" defer></script>
<script>
(function () {
    var queue = <?php echo $deferScriptsJson; ?>;
    var idx = 0;
    function loadNext() {
        if (idx >= queue.length) {
            return;
        }
        var s = document.createElement('script');
        s.src = queue[idx++];
        s.defer = true;
        s.onload = loadNext;
        s.onerror = loadNext;
        document.body.appendChild(s);
    }
    function start() {
        // Phase PI — start idle queue soon after load; auth-lock (when queued) is first.
        if (window.requestIdleCallback) {
            window.requestIdleCallback(loadNext, { timeout: 1200 });
        } else {
            setTimeout(loadNext, 0);
        }
    }
    if (document.readyState === 'complete') {
        start();
    } else {
        window.addEventListener('load', start, { once: true });
    }
})();
</script>
</body>
</html>
