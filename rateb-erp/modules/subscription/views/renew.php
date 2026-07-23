<?php
declare(strict_types=1);

/**
 * Tenant subscription renewal / status placeholders (Phase 7B).
 * @var string $title
 * @var string $page
 * @var string $companyName
 * @var int $companyId
 * @var \Rateb\App\Subscription\SubscriptionContext|null $context
 * @var string|null $expiryDate
 * @var string|null $graceEndDate
 * @var string $status
 */

use Rateb\App\Core\View;

$companyName = $companyName ?? '';
$expiryDate = $expiryDate ?? null;
$graceEndDate = $graceEndDate ?? null;
$status = $status ?? '';
$page = $page ?? 'renew';
?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body p-4">
        <h1 class="h4 mb-3">
            <i class="fas fa-exclamation-triangle text-danger me-2" aria-hidden="true"></i>
            <?php echo View::escape($title ?? 'Subscription expired'); ?>
        </h1>

        <?php if ($page === 'renew') { ?>
            <p class="lead mb-3">Subscription expired</p>
            <p class="text-muted mb-4">
                Your company subscription is no longer active. Renew to restore full ERP access.
                Payment processing is not available on this page yet.
            </p>
        <?php } elseif ($page === 'invoices') { ?>
            <p class="text-muted mb-4">Invoice history will appear here in a later phase.</p>
        <?php } elseif ($page === 'payment-status') { ?>
            <p class="text-muted mb-4">Payment status tracking will appear here in a later phase.</p>
        <?php } else { ?>
            <p class="text-muted mb-4">Contact support for help with your subscription.</p>
        <?php } ?>

        <dl class="row mb-0">
            <dt class="col-sm-4">Company</dt>
            <dd class="col-sm-8"><?php echo View::escape($companyName !== '' ? $companyName : ('#' . (int) ($companyId ?? 0))); ?></dd>

            <dt class="col-sm-4">Expiry date</dt>
            <dd class="col-sm-8"><?php echo View::escape($expiryDate ?: '—'); ?></dd>

            <dt class="col-sm-4">Grace period ended</dt>
            <dd class="col-sm-8"><?php echo View::escape($graceEndDate ?: '—'); ?></dd>

            <dt class="col-sm-4">Status</dt>
            <dd class="col-sm-8"><strong><?php echo View::escape($status); ?></strong></dd>
        </dl>

        <?php if ($page === 'renew') { ?>
            <div class="mt-4 d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary" disabled title="Payment not enabled yet">
                    Renew subscription
                </button>
                <a class="btn btn-outline-secondary" href="<?php echo View::escape(function_exists('rateb_url') ? rateb_url(function_exists('rateb_app_route') ? rateb_app_route('subscription/support') : 'admin/subscription/support') : '#'); ?>">
                    Contact support
                </a>
                <a class="btn btn-outline-danger" href="<?php echo View::escape(function_exists('rateb_url') ? rateb_url('admin/logout') : '#'); ?>" data-rateb-full-nav="1">
                    Logout
                </a>
            </div>
        <?php } ?>
    </div>
</div>
