<?php
declare(strict_types=1);

use Rateb\App\GuestMenu\Support\GuestMenuView;

/** @var string $title */
/** @var bool $rtl */
/** @var string $pageContent */
$dir = !empty($rtl) ? 'rtl' : 'ltr';
$lang = !empty($rtl) ? 'ar' : 'en';
$assetCss = function_exists('rateb_asset') ? rateb_asset('css/guest-menu-public.css') : '/assets/css/guest-menu-public.css';
$assetJs = function_exists('rateb_asset') ? rateb_asset('js/guest-menu-public.js') : '/assets/js/guest-menu-public.js';
?>
<!DOCTYPE html>
<html lang="<?php echo GuestMenuView::escape($lang); ?>" dir="<?php echo GuestMenuView::escape($dir); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b1f33">
    <title><?php echo GuestMenuView::escape($title ?? 'Menu'); ?></title>
    <link rel="stylesheet" href="<?php echo GuestMenuView::escape($assetCss); ?>">
</head>
<body class="gm-body">
<?php echo $pageContent ?? ''; ?>
<script>
(function () {
    if (!('serviceWorker' in navigator)) return;
    navigator.serviceWorker.getRegistrations().then(function (regs) {
        regs.forEach(function (reg) {
            try {
                var scope = String(reg.scope || '');
                if (scope.indexOf('/rateb-erp/public') !== -1) {
                    reg.unregister();
                }
            } catch (e) { /* ignore */ }
        });
    }).catch(function () { /* ignore */ });
})();
</script>
<script src="<?php echo GuestMenuView::escape($assetJs); ?>" defer></script>
</body>
</html>
