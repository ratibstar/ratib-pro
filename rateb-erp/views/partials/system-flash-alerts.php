<?php
declare(strict_types=1);

use Rateb\App\Core\View;
use Rateb\App\Services\ErpSystemAlertService;

$pollUrl = function_exists('rateb_url') ? rateb_url('admin/api/support-ticket-alerts') : '';
$markSeenUrl = function_exists('rateb_url') ? rateb_url('admin/api/support-ticket-alerts/seen') : '';
$canPoll = $pollUrl !== ''
    && function_exists('rateb_nav_can')
    && rateb_nav_can('settings.manage');

$alerts = [];
if ($canPoll && class_exists(ErpSystemAlertService::class)) {
    $alerts = (new ErpSystemAlertService())->alertsForLayout();
}
?>
<div class="rateb-system-flash-stack<?php echo $alerts === [] ? ' rateb-system-flash-stack--empty' : ''; ?>"
     data-rateb-system-flash="1"
     data-rateb-system-flash-poll="<?php echo View::escape($pollUrl); ?>"
     data-rateb-system-flash-mark-seen="<?php echo View::escape($markSeenUrl); ?>"
     data-rateb-system-flash-enabled="<?php echo $canPoll ? '1' : '0'; ?>"
     role="region"
     aria-label="<?php echo View::escape(__('system_flash_alerts_region')); ?>">
<script>window.__RATEB_FLASH_COL_TICKET__=<?php echo json_encode(__('ticket_no'), JSON_UNESCAPED_UNICODE); ?>;window.__RATEB_FLASH_COL_COMPANY__=<?php echo json_encode(__('companies'), JSON_UNESCAPED_UNICODE); ?>;window.__RATEB_FLASH_COL_SUBJECT__=<?php echo json_encode(__('subject'), JSON_UNESCAPED_UNICODE); ?>;</script>
    <?php foreach ($alerts as $alert) {
        $severity = (string) ($alert['severity'] ?? 'info');
        $pulse = !empty($alert['pulse']);
        $persistent = !empty($alert['persistent']);
        $icon = (string) ($alert['icon'] ?? 'fa-bell');
        $url = (string) ($alert['url'] ?? '#');
        $count = (int) ($alert['count'] ?? 0);
        ?>
    <div class="rateb-system-flash-alert rateb-system-flash-alert--<?php echo View::escape($severity); ?><?php echo $pulse ? ' rateb-system-flash-alert--pulse' : ''; ?>"
         data-alert-key="<?php echo View::escape((string) ($alert['key'] ?? '')); ?>"
         data-alert-count="<?php echo View::escape((string) $count); ?>"
         role="alert">
        <div class="rateb-system-flash-alert__icon" aria-hidden="true">
            <i class="fas <?php echo View::escape($icon); ?>"></i>
            <?php if ($count > 0) { ?>
            <span class="rateb-system-flash-alert__badge"><?php echo View::escape((string) $count); ?></span>
            <?php } ?>
        </div>
        <div class="rateb-system-flash-alert__body">
            <div class="rateb-system-flash-alert__title"><?php echo View::escape((string) ($alert['title'] ?? '')); ?></div>
            <?php View::partial('system-flash-alert-body', ['alert' => $alert]); ?>
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
