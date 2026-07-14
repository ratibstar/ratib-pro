<?php
declare(strict_types=1);

$locale = rateb_locale();
$dir = rateb_is_rtl() ? 'rtl' : 'ltr';
$configJson = json_encode($registerConfig ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$deferScripts = [
    rateb_pos_asset('js/pos-register-motion.js'),
    rateb_pos_asset('js/pos-register-cashier.js'),
    rateb_pos_asset('js/pos-offline-bootstrap.js'),
];
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
<script src="<?php echo rateb_pos_asset('js/pos-auth-lock.js'); ?>" defer></script>
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
        if (window.requestIdleCallback) {
            window.requestIdleCallback(loadNext, { timeout: 2500 });
        } else {
            setTimeout(loadNext, 400);
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
