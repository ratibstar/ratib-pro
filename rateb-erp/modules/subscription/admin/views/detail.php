<?php
/** @var array<string, mixed> $detail */
/** @var int $companyId */
/** @var bool $canManage */
/** @var string $csrf */
use Rateb\App\Core\View;

$esc = static fn ($v): string => View::escape((string) $v);
$tenant = $detail['tenant'] ?? [];
$timeline = $detail['timeline'] ?? [];
$notifications = $detail['notifications'] ?? [];
$renewals = $detail['renewals'] ?? [];
$suspensions = $detail['suspensions'] ?? [];
$defaultExpiry = (string) ($tenant['subscription_end'] ?? gmdate('Y-m-d', strtotime('+30 days')));
?>
<div class="rateb-page-header mb-3 d-flex flex-wrap justify-content-between gap-2 align-items-start">
    <div>
        <a class="small text-muted text-decoration-none" href="<?php echo $esc(rateb_url('admin/subscription-engine')); ?>">
            ← Back to list
        </a>
        <h1 class="h4 mb-1 mt-1"><?php echo $esc((string) ($tenant['company_name'] ?? ('#' . $companyId))); ?></h1>
        <p class="text-muted small mb-0">Company #<?php echo (int) $companyId; ?> · Lifecycle ops only</p>
    </div>
    <div>
        <span class="badge bg-secondary fs-6"><?php echo $esc((string) ($tenant['status'] ?? '')); ?></span>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-body">
                <h2 class="h6">Subscription</h2>
                <dl class="row small mb-0">
                    <dt class="col-5">Start</dt><dd class="col-7"><?php echo $esc((string) ($tenant['subscription_start'] ?? '—')); ?></dd>
                    <dt class="col-5">Expiry</dt><dd class="col-7"><?php echo $esc((string) ($tenant['subscription_end'] ?? '—')); ?></dd>
                    <dt class="col-5">Days remaining</dt><dd class="col-7"><?php echo $esc((string) ($tenant['days_remaining'] ?? '—')); ?></dd>
                    <dt class="col-5">Grace</dt><dd class="col-7"><?php echo $esc((string) ($tenant['grace_status'] ?? '—')); ?></dd>
                    <dt class="col-5">Suspension</dt><dd class="col-7"><?php echo $esc((string) ($tenant['suspension_status'] ?? '—')); ?></dd>
                    <dt class="col-5">Last renewal</dt><dd class="col-7"><?php echo $esc((string) ($tenant['renewed_at'] ?? '—')); ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <?php if ($canManage) { ?>
        <div class="rateb-card mb-3">
            <div class="rateb-card-body">
                <h2 class="h6">Manual renewal</h2>
                <form method="post" action="<?php echo $esc(rateb_url('admin/subscription-engine/' . $companyId . '/renew')); ?>" class="row g-2">
                    <input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>">
                    <div class="col-md-6">
                        <label class="form-label small">New expiry</label>
                        <input type="date" name="new_expiry_date" class="form-control form-control-sm" required
                               value="<?php echo $esc($defaultExpiry); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small">Period token</label>
                        <input type="text" name="renewal_period" class="form-control form-control-sm"
                               value="30d" placeholder="30d / 12m / manual">
                    </div>
                    <div class="col-12">
                        <label class="form-label small">Reference (optional)</label>
                        <input type="text" name="reference" class="form-control form-control-sm" maxlength="190"
                               placeholder="Ticket / invoice ref">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-primary">Confirm renewal → ACTIVE</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="rateb-card">
            <div class="rateb-card-body">
                <h2 class="h6">Extend expiry</h2>
                <form method="post" action="<?php echo $esc(rateb_url('admin/subscription-engine/' . $companyId . '/extend')); ?>" class="row g-2">
                    <input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>">
                    <div class="col-md-8">
                        <label class="form-label small">New expiry date</label>
                        <input type="date" name="new_expiry_date" class="form-control form-control-sm" required
                               value="<?php echo $esc($defaultExpiry); ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-sm btn-outline-primary w-100">Extend</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="rateb-card mt-3">
            <div class="rateb-card-body">
                <h2 class="h6">Push to agency ERP</h2>
                <p class="small text-muted">
                    Platform and agency use separate databases. Push lifecycle dates to the linked agency
                    (e.g. test.rateb.sa) so the in-app alert appears on the tenant dashboard.
                </p>
                <form method="post" action="<?php echo $esc(rateb_url('admin/subscription-engine/' . $companyId . '/push-agency')); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>">
                    <button type="submit" class="btn btn-sm btn-warning">Push to linked agency now</button>
                </form>
            </div>
        </div>
        <?php } else { ?>
        <div class="alert alert-secondary small mb-0">View-only. Renewal / extend require <code>subscriptions.manage</code>.</div>
        <?php } ?>
    </div>
</div>

<div class="rateb-card mb-3">
    <div class="rateb-card-body">
        <h2 class="h6">Lifecycle timeline</h2>
        <?php if ($timeline === []) { ?>
            <p class="small text-muted mb-0">No timeline events yet.</p>
        <?php } else { ?>
            <ul class="list-unstyled small mb-0">
                <?php foreach ($timeline as $ev) { ?>
                <li class="border-start border-2 ps-3 mb-2">
                    <div class="text-muted"><?php echo $esc((string) ($ev['at'] ?? '')); ?></div>
                    <strong><?php echo $esc((string) ($ev['label'] ?? $ev['type'] ?? '')); ?></strong>
                    <?php if (!empty($ev['meta']) && is_array($ev['meta'])) { ?>
                        <div class="text-muted"><?php echo $esc(json_encode($ev['meta'], JSON_UNESCAPED_UNICODE) ?: ''); ?></div>
                    <?php } ?>
                </li>
                <?php } ?>
            </ul>
        <?php } ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-body">
                <h2 class="h6">Notifications history</h2>
                <?php if ($notifications === []) { ?>
                    <p class="small text-muted mb-0">None</p>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Type</th><th>Day</th><th>Status</th><th>At</th></tr></thead>
                            <tbody>
                            <?php foreach ($notifications as $n) { ?>
                                <tr>
                                    <td><?php echo $esc((string) ($n['notification_type'] ?? '')); ?></td>
                                    <td><?php echo $esc((string) ($n['trigger_day'] ?? '')); ?></td>
                                    <td><?php echo $esc((string) ($n['status'] ?? '')); ?></td>
                                    <td class="small"><?php echo $esc((string) ($n['generated_at'] ?? $n['created_at'] ?? '')); ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-body">
                <h2 class="h6">Renewal history</h2>
                <?php if ($renewals === []) { ?>
                    <p class="small text-muted mb-0">None</p>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Previous</th><th>New</th><th>Period</th><th>At</th></tr></thead>
                            <tbody>
                            <?php foreach ($renewals as $r) { ?>
                                <tr>
                                    <td><?php echo $esc((string) ($r['previous_expiry_date'] ?? '')); ?></td>
                                    <td><?php echo $esc((string) ($r['new_expiry_date'] ?? '')); ?></td>
                                    <td><?php echo $esc((string) ($r['period'] ?? '')); ?></td>
                                    <td class="small"><?php echo $esc((string) ($r['created_at'] ?? '')); ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-body">
                <h2 class="h6">Suspension audit</h2>
                <?php if ($suspensions === []) { ?>
                    <p class="small text-muted mb-0">None</p>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Decision</th><th>Reason</th><th>At</th></tr></thead>
                            <tbody>
                            <?php foreach ($suspensions as $s) { ?>
                                <tr>
                                    <td><?php echo $esc((string) ($s['decision'] ?? '')); ?></td>
                                    <td><?php echo $esc((string) ($s['reason'] ?? '')); ?></td>
                                    <td class="small"><?php echo $esc((string) ($s['created_at'] ?? '')); ?></td>
                                </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
