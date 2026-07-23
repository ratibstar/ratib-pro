<?php
/** @var \Rateb\App\Subscription\Admin\SubscriptionAdminDashboard $dashboard */
/** @var list<array<string, mixed>> $tenants */
/** @var int $total */
/** @var int $page */
/** @var int $limit */
/** @var string $statusFilter */
/** @var string $search */
/** @var bool $canManage */
use Rateb\App\Core\View;

$esc = static fn ($v): string => View::escape((string) $v);
$statusBadge = static function (string $status): string {
    $map = [
        'ACTIVE' => 'success',
        'WARNING' => 'warning',
        'CRITICAL' => 'danger',
        'GRACE' => 'info',
        'SUSPENSION_PENDING' => 'secondary',
        'SUSPENDED' => 'dark',
    ];
    $cls = $map[$status] ?? 'secondary';
    return '<span class="badge bg-' . $cls . '">' . View::escape($status) . '</span>';
};
?>
<div class="rateb-page-header mb-3">
    <h1 class="h4 mb-1"><i class="fas fa-heartbeat me-2"></i>Subscription Engine Admin</h1>
    <p class="text-muted small mb-0">Operational console for tenant subscription lifecycle. No payment or auto-billing.</p>
    <p class="small text-muted mb-0 mt-1">Companies are auto-synced into the engine on open (insert-only; existing engine dates are not overwritten).</p>
</div>

<?php if (!empty($syncInserted) && (int) $syncInserted > 0) { ?>
<div class="alert alert-success py-2">Synced <?php echo (int) $syncInserted; ?> compan<?php echo (int) $syncInserted === 1 ? 'y' : 'ies'; ?> into the subscription engine.</div>
<?php } ?>

<?php
$adminAlertItems = is_array($adminAlerts['items'] ?? null) ? $adminAlerts['items'] : [];
$adminAlertNotifs = (int) ($adminAlerts['notifications'] ?? 0);
if ($adminAlertItems !== []) {
    $panelTitle = function_exists('__') ? (string) __('subscription_admin_ops_panel_title') : 'Companies needing follow-up';
    $panelHelp = function_exists('__') ? (string) __('subscription_admin_ops_panel_help') : '';
    $openLabel = function_exists('__') ? (string) __('subscription_admin_ops_open') : 'Open';
    ?>
<div class="rateb-card mb-3 border-danger">
    <div class="rateb-card-body py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
            <div>
                <h2 class="h6 mb-1 text-danger">
                    <i class="fas fa-bell me-1"></i><?php echo $esc($panelTitle); ?>
                    <span class="badge bg-danger ms-1"><?php echo count($adminAlertItems); ?></span>
                </h2>
                <?php if ($panelHelp !== '') { ?>
                    <p class="small text-muted mb-0"><?php echo $esc($panelHelp); ?></p>
                <?php } ?>
                <?php if ($adminAlertNotifs > 0) { ?>
                    <p class="small text-success mb-0 mt-1">
                        <?php echo (int) $adminAlertNotifs; ?> notification(s) sent to super-admin inbox.
                    </p>
                <?php } ?>
            </div>
            <a class="btn btn-sm btn-outline-danger"
               href="<?php echo $esc(rateb_url_query('admin/subscription-engine', ['status' => 'expiring_soon', 'page' => 1])); ?>">
                Expiring soon
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Days left</th>
                    <th>Expiry</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($adminAlertItems, 0, 12) as $item) {
                    $cid = (int) ($item['company_id'] ?? 0);
                    $kind = (string) ($item['kind'] ?? 'warning');
                    $badge = match ($kind) {
                        'suspended' => 'dark',
                        'grace' => 'info',
                        'critical' => 'danger',
                        default => 'warning',
                    };
                    ?>
                    <tr>
                        <td><?php echo $esc((string) ($item['company_name'] ?? '')); ?> <span class="text-muted">(#<?php echo $cid; ?>)</span></td>
                        <td><span class="badge bg-<?php echo $esc($badge); ?>"><?php echo $esc(strtoupper((string) ($item['status'] ?? $kind))); ?></span></td>
                        <td><?php echo (int) ($item['days_remaining'] ?? 0); ?></td>
                        <td><?php echo $esc((string) ($item['expiry'] ?? '')); ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary"
                               href="<?php echo $esc(rateb_url('admin/subscription-engine/' . $cid)); ?>">
                                <?php echo $esc($openLabel); ?>
                            </a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<?php if ($canManage) {
    $defaultEnd = gmdate('Y-m-d', strtotime('+3 days') ?: time());
    $defaultStart = gmdate('Y-m-d', strtotime('-30 days') ?: time());
    ?>
<div class="rateb-card mb-3 border-primary">
    <div class="rateb-card-body py-3">
        <h2 class="h6 mb-2">Create / test engine record</h2>
        <p class="small text-muted mb-2">
            Companies auto-sync on page open. Use this form to create a missing company with a custom expiry
            and optionally seed an in-app alert (for testing).
        </p>
        <form method="post" action="<?php echo $esc(rateb_url('admin/subscription-engine/create')); ?>" class="row g-2 align-items-end">
            <input type="hidden" name="_csrf" value="<?php echo $esc((string) ($csrf ?? '')); ?>">
            <div class="col-md-2">
                <label class="form-label small mb-1">Company ID</label>
                <input type="number" min="1" name="company_id" class="form-control form-control-sm" required placeholder="29">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Start</label>
                <input type="date" name="subscription_start" class="form-control form-control-sm" value="<?php echo $esc($defaultStart); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Expiry</label>
                <input type="date" name="subscription_end" class="form-control form-control-sm" required value="<?php echo $esc($defaultEnd); ?>">
            </div>
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" name="seed_alert" value="1" id="seedAlert" checked>
                    <label class="form-check-label small" for="seedAlert">Seed in-app alert history</label>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>
<?php } ?>

<div class="row g-2 mb-3">
    <?php
    $cards = [
        ['Total tenants', $dashboard->totalTenants(), 'secondary', 'all'],
        ['Active', $dashboard->active(), 'success', 'ACTIVE'],
        ['Warning', $dashboard->warning(), 'warning', 'warning'],
        ['Grace', $dashboard->grace(), 'info', 'grace'],
        ['Suspended', $dashboard->suspended(), 'dark', 'SUSPENDED'],
        ['Expiring soon', $dashboard->expiringSoon(), 'danger', 'expiring_soon'],
    ];
    foreach ($cards as [$label, $count, $color, $filter]) {
        $href = rateb_url_query('admin/subscription-engine', ['status' => $filter, 'page' => 1]);
        ?>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?php echo $esc($href); ?>" class="text-decoration-none">
            <div class="rateb-card h-100">
                <div class="rateb-card-body py-3 text-center">
                    <div class="fs-4 fw-semibold text-<?php echo $esc($color); ?>"><?php echo (int) $count; ?></div>
                    <div class="small text-muted"><?php echo $esc($label); ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php } ?>
</div>

<div class="rateb-card mb-3">
    <div class="rateb-card-body py-3">
        <form method="get" action="<?php echo $esc(rateb_url('admin/subscription-engine')); ?>" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1" for="subAdminSearch">Search</label>
                <input type="text" class="form-control form-control-sm" id="subAdminSearch" name="q"
                       value="<?php echo $esc($search); ?>" placeholder="Company name or ID">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1" for="subAdminStatus">Status</label>
                <select class="form-select form-select-sm" id="subAdminStatus" name="status">
                    <?php
                    $opts = [
                        'all' => 'All',
                        'ACTIVE' => 'Active',
                        'warning' => 'Warning / Critical',
                        'grace' => 'Grace / Pending',
                        'SUSPENDED' => 'Suspended',
                        'expiring_soon' => 'Expiring soon',
                    ];
                    foreach ($opts as $val => $lab) {
                        $sel = $statusFilter === $val ? ' selected' : '';
                        echo '<option value="' . $esc($val) . '"' . $sel . '>' . $esc($lab) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter me-1"></i>Filter</button>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $esc(rateb_url('admin/subscription-engine')); ?>">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="rateb-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead>
            <tr>
                <th>Company</th>
                <th>Status</th>
                <th>Start</th>
                <th>Expiry</th>
                <th>Days left</th>
                <th>Grace</th>
                <th>Suspension</th>
                <th>Last renewal</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($tenants === []) { ?>
                <tr><td colspan="9" class="text-muted text-center py-4">No subscription engine records.</td></tr>
            <?php } ?>
            <?php foreach ($tenants as $t) {
                $cid = (int) ($t['company_id'] ?? 0);
                $days = $t['days_remaining'];
                $daysLabel = $days === null ? '—' : (string) (int) $days;
                ?>
            <tr class="<?php echo !empty($t['expiring_soon']) ? 'table-warning' : ''; ?>">
                <td>
                    <a href="<?php echo $esc(rateb_url('admin/subscription-engine/' . $cid)); ?>">
                        <?php echo $esc((string) $t['company_name']); ?>
                    </a>
                    <div class="small text-muted">#<?php echo $cid; ?></div>
                </td>
                <td><?php echo $statusBadge((string) $t['status']); ?></td>
                <td><?php echo $esc((string) ($t['subscription_start'] ?? '—')); ?></td>
                <td><?php echo $esc((string) ($t['subscription_end'] ?? '—')); ?></td>
                <td><?php echo $esc($daysLabel); ?></td>
                <td><span class="small"><?php echo $esc((string) $t['grace_status']); ?></span></td>
                <td><span class="small"><?php echo $esc((string) $t['suspension_status']); ?></span></td>
                <td class="small"><?php echo $esc((string) ($t['last_renewal'] ?? $t['renewed_at'] ?? '—')); ?></td>
                <td class="text-end">
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo $esc(rateb_url('admin/subscription-engine/' . $cid)); ?>">
                        View
                    </a>
                </td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    $routePrefix = 'admin/subscription-engine';
    $preserveQuery = array_filter([
        'status' => $statusFilter !== 'all' ? $statusFilter : null,
        'q' => $search !== '' ? $search : null,
    ], static fn ($v) => $v !== null && $v !== '');
    require RATEB_VIEWS_PATH . '/components/pagination.php';
    ?>
</div>
<?php if (!$canManage) { ?>
<p class="small text-muted mt-2 mb-0">Read-only access (<code>subscriptions.view</code>). Manage actions require <code>subscriptions.manage</code>.</p>
<?php } ?>
