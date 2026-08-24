<?php
declare(strict_types=1);

use Rateb\App\Core\View;
use Rateb\App\Services\ErpSystemAlertService;

if (!class_exists(ErpSystemAlertService::class)) {
    return;
}

$alerts = (new ErpSystemAlertService())->alertsForLayout();
if ($alerts === []) {
    return;
}
?>
<div class="rateb-system-flash-stack" data-rateb-system-flash="1" role="region" aria-label="<?php echo View::escape(__('system_flash_alerts_region')); ?>">
    <?php foreach ($alerts as $alert) {
        $severity = (string) ($alert['severity'] ?? 'info');
        $pulse = !empty($alert['pulse']);
        $persistent = !empty($alert['persistent']);
        $icon = (string) ($alert['icon'] ?? 'fa-bell');
        $url = (string) ($alert['url'] ?? '#');
        ?>
    <div class="rateb-system-flash-alert rateb-system-flash-alert--<?php echo View::escape($severity); ?><?php echo $pulse ? ' rateb-system-flash-alert--pulse' : ''; ?>"
         data-alert-key="<?php echo View::escape((string) ($alert['key'] ?? '')); ?>"
         role="alert">
        <div class="rateb-system-flash-alert__icon" aria-hidden="true">
            <i class="fas <?php echo View::escape($icon); ?>"></i>
        </div>
        <div class="rateb-system-flash-alert__body">
            <div class="rateb-system-flash-alert__title"><?php echo View::escape((string) ($alert['title'] ?? '')); ?></div>
            <?php if (!empty($alert['message'])) { ?>
            <div class="rateb-system-flash-alert__message"><?php echo View::escape((string) $alert['message']); ?></div>
            <?php } ?>
        </div>
        <?php if ($url !== '' && $url !== '#') { ?>
        <a href="<?php echo View::escape($url); ?>" class="rateb-system-flash-alert__action btn btn-sm btn-light" data-rateb-full-nav="1">
            <?php echo View::escape((string) ($alert['action_label'] ?? __('system_flash_alert_view'))); ?>
        </a>
        <?php } ?>
        <?php if (!$persistent) { ?>
        <button type="button" class="rateb-system-flash-alert__close btn-close btn-close-white" aria-label="<?php echo View::escape(__('dismiss')); ?>"></button>
        <?php } ?>
    </div>
    <?php } ?>
</div>
<script>
(function () {
    if (window.__RATEB_SYSTEM_FLASH__) return;
    window.__RATEB_SYSTEM_FLASH__ = 1;
    document.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('.rateb-system-flash-alert__close') : null;
        if (!btn) return;
        var box = btn.closest('.rateb-system-flash-alert');
        if (box) box.remove();
    });
})();
</script>
