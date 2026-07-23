<?php
declare(strict_types=1);

/**
 * In-app subscription alert — modern centered toast card (Phase 5).
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
$dismissible = $subscriptionAlert->isDismissible();
$historyId = $subscriptionAlert->historyId();
$severity = $subscriptionAlert->severity();

$dismissUrl = '';
if ($dismissible && $historyId > 0) {
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '');
    $query = [];
    parse_str((string) (parse_url($uri, PHP_URL_QUERY) ?? ''), $query);
    $query['dismiss_subscription_alert'] = (string) $historyId;
    $dismissUrl = $path . '?' . http_build_query($query);
}

$icon = match ($severity) {
    'critical', 'critical_warning' => 'fa-triangle-exclamation',
    'high' => 'fa-bell',
    default => 'fa-calendar-exclamation',
};
?>
<div class="rateb-sub-toast-wrap" data-subscription-alert="1" role="status"
     data-severity="<?php echo View::escape($severity); ?>"
     data-type="<?php echo View::escape($subscriptionAlert->notificationType()); ?>">
    <div class="rateb-sub-toast <?php echo View::escape($css); ?>">
        <div class="rateb-sub-toast__icon" aria-hidden="true">
            <i class="fas <?php echo View::escape($icon); ?>"></i>
        </div>
        <div class="rateb-sub-toast__body">
            <div class="rateb-sub-toast__title"><?php echo View::escape($msg); ?></div>
            <div class="rateb-sub-toast__meta">
                <?php if ($expiry !== null && $expiry !== '') { ?>
                    <span><?php echo View::escape(__('date') ?: 'Expiry'); ?>: <strong><?php echo View::escape($expiry); ?></strong></span>
                <?php } ?>
                <span><?php echo View::escape(__('status') ?: 'Status'); ?>: <strong><?php echo View::escape($status); ?></strong></span>
                <span><?php echo View::escape(__('days') ?: 'Days'); ?>: <strong><?php echo (int) $days; ?></strong></span>
            </div>
        </div>
        <?php if ($dismissible && $dismissUrl !== '') { ?>
            <a href="<?php echo View::escape($dismissUrl); ?>"
               class="rateb-sub-toast__dismiss"
               data-rateb-full-nav="1"
               aria-label="<?php echo View::escape(__('dismiss') ?: 'Dismiss'); ?>">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </a>
        <?php } ?>
    </div>
</div>
<style>
.rateb-sub-toast-wrap {
    display: flex;
    justify-content: center;
    width: 100%;
    margin: 0 0 1rem;
    padding: 0 .25rem;
    pointer-events: none;
}
.rateb-sub-toast {
    pointer-events: auto;
    display: flex;
    align-items: flex-start;
    gap: .85rem;
    width: min(100%, 36rem);
    padding: .9rem 1rem;
    border-radius: .75rem;
    border: 1px solid transparent;
    box-shadow: 0 10px 28px rgba(0, 0, 0, .28);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    animation: rateb-sub-toast-in .35s ease-out;
}
.rateb-sub-toast__icon {
    flex: 0 0 auto;
    width: 2.35rem;
    height: 2.35rem;
    border-radius: .65rem;
    display: grid;
    place-items: center;
    font-size: 1rem;
}
.rateb-sub-toast__body { flex: 1 1 auto; min-width: 0; }
.rateb-sub-toast__title {
    font-weight: 650;
    font-size: .95rem;
    line-height: 1.35;
    margin: 0 0 .35rem;
}
.rateb-sub-toast__meta {
    display: flex;
    flex-wrap: wrap;
    gap: .35rem .85rem;
    font-size: .78rem;
    opacity: .88;
}
.rateb-sub-toast__dismiss {
    flex: 0 0 auto;
    width: 1.85rem;
    height: 1.85rem;
    border-radius: .5rem;
    display: grid;
    place-items: center;
    color: inherit;
    text-decoration: none;
    opacity: .7;
    border: 1px solid transparent;
}
.rateb-sub-toast__dismiss:hover { opacity: 1; }

/* Warning / reminder */
.rateb-sub-toast--warn {
    background: linear-gradient(135deg, rgba(180, 120, 20, .22), rgba(40, 30, 10, .92));
    border-color: rgba(245, 180, 60, .45);
    color: #ffe8b8;
}
.rateb-sub-toast--warn .rateb-sub-toast__icon {
    background: rgba(245, 180, 60, .18);
    color: #ffc14d;
}

/* High warning */
.rateb-sub-toast--high {
    background: linear-gradient(135deg, rgba(200, 90, 20, .28), rgba(40, 22, 10, .94));
    border-color: rgba(255, 140, 60, .55);
    color: #ffd7b0;
}
.rateb-sub-toast--high .rateb-sub-toast__icon {
    background: rgba(255, 140, 60, .2);
    color: #ff9a45;
}

/* Critical / grace */
.rateb-sub-toast--critical {
    background: linear-gradient(135deg, rgba(180, 40, 50, .32), rgba(35, 12, 16, .95));
    border-color: rgba(255, 90, 100, .5);
    color: #ffd0d4;
}
.rateb-sub-toast--critical .rateb-sub-toast__icon {
    background: rgba(255, 90, 100, .2);
    color: #ff7a86;
}

@keyframes rateb-sub-toast-in {
    from { opacity: 0; transform: translateY(-10px) scale(.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

[data-bs-theme="light"] .rateb-sub-toast--warn,
html:not([data-bs-theme="dark"]) .rateb-content .rateb-sub-toast--warn {
    background: linear-gradient(135deg, #fff6e5, #ffffff);
    border-color: #f0c36a;
    color: #6a4a12;
    box-shadow: 0 8px 22px rgba(120, 80, 10, .12);
}
[data-bs-theme="light"] .rateb-sub-toast--high,
html:not([data-bs-theme="dark"]) .rateb-content .rateb-sub-toast--high {
    background: linear-gradient(135deg, #fff0e6, #ffffff);
    border-color: #f0a060;
    color: #7a3a10;
    box-shadow: 0 8px 22px rgba(140, 60, 10, .12);
}
[data-bs-theme="light"] .rateb-sub-toast--critical,
html:not([data-bs-theme="dark"]) .rateb-content .rateb-sub-toast--critical {
    background: linear-gradient(135deg, #ffe8ea, #ffffff);
    border-color: #ef8a94;
    color: #7a1f2a;
    box-shadow: 0 8px 22px rgba(140, 30, 40, .12);
}

@media (max-width: 575.98px) {
    .rateb-sub-toast { width: 100%; border-radius: .65rem; }
}
</style>
