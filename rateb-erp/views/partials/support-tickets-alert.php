<?php
declare(strict_types=1);

use Rateb\App\Core\View;
use Rateb\App\Services\SupportTicketAlertService;

if (!class_exists(SupportTicketAlertService::class)) {
    return;
}

$svc = new SupportTicketAlertService();
$openCount = $svc->openCountForViewer();
if ($openCount < 1) {
    return;
}

$url = $svc->supportTicketsListUrl();
$isPlatform = function_exists('rateb_is_platform_oversight_host') && rateb_is_platform_oversight_host();
?>
<div class="rateb-st-alert-wrap" data-support-tickets-alert="1" role="status">
    <div class="rateb-st-alert">
        <div class="rateb-st-alert__icon" aria-hidden="true">
            <i class="fas fa-life-ring"></i>
        </div>
        <div class="rateb-st-alert__body">
            <div class="rateb-st-alert__title">
                <?php echo View::escape(__('support_ticket_banner_title', ['count' => (string) $openCount])); ?>
            </div>
            <div class="rateb-st-alert__meta">
                <?php echo View::escape($isPlatform
                    ? __('support_ticket_banner_platform_hint')
                    : __('support_ticket_banner_tenant_hint')); ?>
            </div>
        </div>
        <a href="<?php echo View::escape($url); ?>" class="rateb-st-alert__action btn btn-sm btn-light" data-rateb-full-nav="1">
            <?php echo View::escape(__('support_ticket_banner_action')); ?>
        </a>
    </div>
</div>
<style id="rateb-st-alert-css">
.rateb-st-alert-wrap {
    position: sticky;
    top: 0;
    z-index: 1044;
    display: flex;
    justify-content: center;
    width: 100%;
    margin: 0 0 1rem;
    padding: .35rem .25rem 0;
}
.rateb-st-alert {
    display: flex;
    align-items: center;
    gap: .85rem;
    width: min(100%, 42rem);
    margin-inline: auto;
    padding: .85rem 1rem;
    border-radius: .75rem;
    border: 1px solid rgba(56, 189, 248, .45);
    background: linear-gradient(135deg, rgba(14, 116, 144, .35), rgba(15, 23, 42, .95));
    color: #e0f2fe;
    box-shadow: 0 10px 28px rgba(0, 0, 0, .28);
    animation: rateb-st-alert-in .35s ease-out;
}
.rateb-st-alert__icon {
    width: 2.35rem;
    height: 2.35rem;
    border-radius: .65rem;
    display: grid;
    place-items: center;
    background: rgba(56, 189, 248, .18);
    color: #38bdf8;
    flex: 0 0 auto;
}
.rateb-st-alert__body { flex: 1 1 auto; min-width: 0; }
.rateb-st-alert__title { font-weight: 650; font-size: .95rem; line-height: 1.35; }
.rateb-st-alert__meta { font-size: .78rem; opacity: .88; margin-top: .2rem; }
.rateb-st-alert__action { flex: 0 0 auto; white-space: nowrap; }
@keyframes rateb-st-alert-in {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}
@media (max-width: 575.98px) {
    .rateb-st-alert { flex-wrap: wrap; }
    .rateb-st-alert__action { width: 100%; }
}
</style>
