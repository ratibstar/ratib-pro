<?php
declare(strict_types=1);

/**
 * In-app subscription alert banner (Phase 5 — read-only display).
 *
 * @var \Rateb\App\Subscription\SubscriptionAlertViewModel|null $subscriptionAlert
 */
use Rateb\App\Core\View;

$subscriptionAlert = $subscriptionAlert ?? null;
if ($subscriptionAlert === null && function_exists('subscription_alert')) {
    $subscriptionAlert = subscription_alert();
}
if ($subscriptionAlert === null) {
    return;
}

$css = $subscriptionAlert->cssClass();
$msg = $subscriptionAlert->message();
$days = $subscriptionAlert->daysRemaining();
$expiry = $subscriptionAlert->expirationDate();
$status = $subscriptionAlert->subscriptionStatus();
$created = $subscriptionAlert->createdAt();
$dismissible = $subscriptionAlert->isDismissible();
$historyId = $subscriptionAlert->historyId();

$dismissUrl = '';
if ($dismissible) {
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '');
    $query = [];
    parse_str((string) (parse_url($uri, PHP_URL_QUERY) ?? ''), $query);
    $query['dismiss_subscription_alert'] = (string) $historyId;
    $dismissUrl = $path . '?' . http_build_query($query);
}
?>
<div class="alert <?php echo View::escape($css); ?> py-2 mb-3 rateb-subscription-alert d-flex flex-wrap align-items-start justify-content-between gap-2"
     role="status"
     data-subscription-alert="1"
     data-severity="<?php echo View::escape($subscriptionAlert->severity()); ?>"
     data-type="<?php echo View::escape($subscriptionAlert->notificationType()); ?>">
    <div class="small">
        <div class="fw-semibold mb-1">
            <i class="fas fa-calendar-exclamation me-1" aria-hidden="true"></i>
            <?php echo View::escape($msg); ?>
        </div>
        <div class="text-opacity-75">
            <?php if ($expiry !== null && $expiry !== '') { ?>
                <span class="me-3"><?php echo View::escape(__('date') ?: 'Expiry'); ?>: <strong><?php echo View::escape($expiry); ?></strong></span>
            <?php } ?>
            <span class="me-3"><?php echo View::escape(__('status') ?: 'Status'); ?>: <strong><?php echo View::escape($status); ?></strong></span>
            <span class="me-3"><?php echo View::escape(__('days') ?: 'Days'); ?>: <strong><?php echo (int) $days; ?></strong></span>
            <?php if ($created !== null && $created !== '') { ?>
                <span><?php echo View::escape(__('created_at') ?: 'Created'); ?>: <strong><?php echo View::escape($created); ?></strong></span>
            <?php } ?>
        </div>
    </div>
    <?php if ($dismissible && $dismissUrl !== '') { ?>
        <a href="<?php echo View::escape($dismissUrl); ?>"
           class="btn btn-sm btn-outline-secondary flex-shrink-0"
           data-rateb-full-nav="1"><?php echo View::escape(__('dismiss') ?: 'Dismiss'); ?></a>
    <?php } ?>
</div>
<style>
.rateb-sub-alert--high { border-width: 2px; }
.rateb-sub-alert--critical { border-width: 2px; font-weight: 600; }
</style>
