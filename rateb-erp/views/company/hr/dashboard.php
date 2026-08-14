<?php
/**
 * Phase N — HR Command Center (Admin overview).
 *
 * @var int $companyId
 * @var array<string, mixed> $stats
 * @var array<string, int> $inboxCounts
 * @var array<string, int> $approvalCenter
 * @var int $pendingDecisions
 * @var list<array<string, mixed>> $contractsExpiring
 * @var int $contractsExpiringCount
 * @var list<array<string, mixed>> $recentPayrolls
 * @var list<array<string, mixed>> $recentRequests
 * @var list<array<string, mixed>> $recentDecisions
 * @var list<array<string, mixed>> $upcomingLeaves
 * @var list<array<string, mixed>> $alerts
 * @var list<array<string, mixed>> $quickActions
 * @var list<array<string, mixed>> $hubLinks
 * @var string $lookupUrl
 */
$companyId = (int) ($companyId ?? 0);
$stats = $stats ?? [];
$approvalCenter = $approvalCenter ?? ($inboxCounts ?? []);
$pendingDecisions = (int) ($pendingDecisions ?? 0);
$contractsExpiring = $contractsExpiring ?? [];
$contractsExpiringCount = (int) ($contractsExpiringCount ?? 0);
$recentPayrolls = $recentPayrolls ?? [];
$recentRequests = $recentRequests ?? [];
$recentDecisions = $recentDecisions ?? [];
$upcomingLeaves = $upcomingLeaves ?? [];
$alerts = $alerts ?? [];
$quickActions = $quickActions ?? [];
$hubLinks = $hubLinks ?? [];
$lookupUrl = (string) ($lookupUrl ?? rateb_url(rateb_app_route('hr/employees/lookup')));
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
$approvalTotal = (int) ($approvalCenter['total'] ?? 0);

Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'overview']);
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/hr-module.css'); ?>">

<div class="rateb-hr-cc" data-hr-cc data-lookup-url="<?php echo $escape($lookupUrl); ?>">
<?php if ($companyId < 1 && function_exists('rateb_is_super_admin') && rateb_is_super_admin()) { ?>
<div class="alert alert-warning mb-3">
    <i class="fas fa-building me-1"></i> <?php echo __('hr_select_company_hint'); ?>
</div>
<?php } ?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?php echo __('hr_command_center'); ?></h1>
        <p class="text-muted small mb-0"><?php echo __('hr_cc_subtitle'); ?></p>
    </div>
    <a href="<?php echo rateb_url(rateb_app_route('hr/approvals-inbox')); ?>" class="btn btn-sm btn-warning">
        <i class="fas fa-inbox me-1"></i> <?php echo __('hr_open_approval_inbox'); ?>
        <?php if ($approvalTotal > 0) { ?>
            <span class="badge text-bg-dark ms-1 rateb-ltr-num"><?php echo $approvalTotal; ?></span>
        <?php } ?>
    </a>
</div>

<div class="rateb-card mb-3">
    <div class="rateb-card-body">
        <label class="form-label fw-semibold mb-1" for="hrCcSearch"><?php echo __('hr_cc_employee_search'); ?></label>
        <div class="rateb-hr-cc-search">
            <input type="search" id="hrCcSearch" class="form-control" autocomplete="off"
                   placeholder="<?php echo $escape(__('hr_cc_employee_search_placeholder')); ?>"
                   data-hr-cc-search
                   data-empty-label="<?php echo $escape(__('no_records')); ?>">
            <div class="rateb-hr-cc-search-results d-none" data-hr-cc-results role="listbox"></div>
        </div>
        <p class="text-muted small mb-0 mt-1"><?php echo __('hr_cc_employee_search_hint'); ?></p>
    </div>
</div>

<div class="rateb-card mb-3 rateb-hr-cc-approval">
    <div class="rateb-card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div>
                <div class="fw-semibold">
                    <i class="fas fa-inbox me-1"></i>
                    <?php echo __('hr_cc_approval_you_have', ['count' => $approvalTotal]); ?>
                </div>
                <div class="text-muted small"><?php echo __('hr_cc_approval_hint'); ?></div>
            </div>
            <a href="<?php echo rateb_url(rateb_app_route('hr/approvals-inbox')); ?>" class="btn btn-sm btn-primary">
                <?php echo __('hr_open_approval_inbox'); ?>
            </a>
        </div>
        <div class="row g-2">
            <?php
            $buckets = [
                'leave' => ['label' => __('hr_leaves'), 'type' => 'leave'],
                'request' => ['label' => __('hr_employee_requests'), 'type' => 'request'],
                'decision' => ['label' => __('hr_decisions'), 'type' => 'decision'],
                'permission' => ['label' => __('hr_permission_requests'), 'type' => 'permission'],
            ];
            foreach ($buckets as $key => $meta) {
                $n = (int) ($approvalCenter[$key] ?? 0);
                $href = rateb_url(rateb_app_route('hr/approvals-inbox')) . '?type=' . urlencode($meta['type']);
                ?>
                <div class="col-6 col-md-3">
                    <a href="<?php echo $escape($href); ?>" class="rateb-hr-cc-bucket text-decoration-none text-reset">
                        <div class="rateb-hr-cc-bucket-label"><?php echo $escape($meta['label']); ?></div>
                        <div class="rateb-hr-cc-bucket-value rateb-ltr-num"><?php echo $n; ?></div>
                    </a>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="row g-2 g-md-3 mb-3">
    <?php
    $statTiles = [
        ['label' => __('hr_employees'), 'value' => (int) ($stats['employees'] ?? 0), 'href' => rateb_url(rateb_app_route('hr/employees'))],
        ['label' => __('hr_present_today'), 'value' => (int) ($stats['present_today'] ?? 0), 'href' => rateb_url(rateb_app_route('hr/attendance'))],
        ['label' => __('hr_absent_today'), 'value' => (int) ($stats['absent_today'] ?? 0), 'href' => rateb_url(rateb_app_route('hr/attendance'))],
        ['label' => __('hr_cc_late_today'), 'value' => (int) ($stats['late_today'] ?? 0), 'href' => rateb_url(rateb_app_route('hr/attendance'))],
        ['label' => __('hr_cc_on_leave_today'), 'value' => (int) ($stats['on_leave_today'] ?? 0), 'href' => rateb_url(rateb_app_route('hr/leaves'))],
        ['label' => __('hr_pending_actions'), 'value' => $approvalTotal, 'href' => rateb_url(rateb_app_route('hr/approvals-inbox'))],
        ['label' => __('hr_cc_pending_decisions'), 'value' => $pendingDecisions, 'href' => rateb_url(rateb_app_route('hr/approvals-inbox')) . '?type=decision'],
        ['label' => __('hr_cc_contracts_expiring'), 'value' => $contractsExpiringCount, 'href' => rateb_url(rateb_app_route('hr/employment-contracts'))],
    ];
    foreach ($statTiles as $tile) { ?>
        <div class="col-6 col-md-4 col-xl-3">
            <a href="<?php echo $escape($tile['href']); ?>" class="text-decoration-none text-reset">
                <div class="rateb-stat-card rateb-hr-cc-stat">
                    <div class="rateb-stat-label"><?php echo $escape($tile['label']); ?></div>
                    <div class="rateb-stat-value rateb-ltr-num"><?php echo (int) $tile['value']; ?></div>
                </div>
            </a>
        </div>
    <?php } ?>
</div>

<div class="rateb-card mb-3">
    <div class="rateb-card-header"><i class="fas fa-bolt me-1"></i> <?php echo __('hr_cc_quick_actions'); ?></div>
    <div class="rateb-card-body">
        <div class="row g-2">
            <?php foreach ($quickActions as $qa) {
                $route = (string) ($qa['route'] ?? '');
                $href = $route !== '' ? rateb_url(rateb_app_route($route)) : '#';
                $icon = (string) ($qa['icon'] ?? 'fa-circle');
                ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <a class="rateb-hr-cc-qa" href="<?php echo $escape($href); ?>">
                        <i class="fas <?php echo $escape($icon); ?>"></i>
                        <span><?php echo $escape(__((string) ($qa['label'] ?? ''))); ?></span>
                    </a>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<?php if ($alerts !== []) { ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header"><i class="fas fa-bell me-1"></i> <?php echo __('hr_cc_alerts'); ?></div>
    <div class="rateb-card-body p-0">
        <ul class="list-group list-group-flush">
            <?php foreach ($alerts as $alert) {
                $type = (string) ($alert['type'] ?? 'info');
                $url = $alert['url'] ?? null;
                ?>
                <li class="list-group-item d-flex justify-content-between align-items-start gap-2">
                    <div>
                        <div class="fw-semibold"><?php echo $escape((string) ($alert['title'] ?? '')); ?></div>
                        <div class="small text-muted"><?php echo $escape((string) ($alert['message'] ?? '')); ?></div>
                    </div>
                    <?php if (is_string($url) && $url !== '') { ?>
                        <a class="btn btn-sm btn-outline-secondary flex-shrink-0" href="<?php echo $escape($url); ?>"><?php echo __('view'); ?></a>
                    <?php } ?>
                </li>
            <?php } ?>
        </ul>
    </div>
</div>
<?php } ?>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header d-flex justify-content-between align-items-center">
                <span><?php echo __('hr_cc_recent_requests'); ?></span>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_url(rateb_app_route('hr/requests')); ?>"><?php echo __('all'); ?></a>
            </div>
            <div class="rateb-card-body p-0">
                <?php if ($recentRequests === []) { ?>
                    <p class="text-muted small p-3 mb-0"><?php echo __('hr_cc_empty_requests'); ?></p>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table rateb-table table-sm mb-0">
                        <thead><tr>
                            <th><?php echo __('employee'); ?></th>
                            <th><?php echo __('type'); ?></th>
                            <th><?php echo __('status'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($recentRequests as $row) { ?>
                            <tr>
                                <td>
                                    <?php echo $escape((string) ($row['employee_name'] ?? '')); ?>
                                    <div class="small text-muted rateb-ltr-num"><?php echo $escape((string) ($row['employee_code'] ?? '')); ?></div>
                                </td>
                                <td><?php echo $escape((string) ($row['request_type'] ?? '')); ?></td>
                                <td><?php echo $escape((string) ($row['status'] ?? '')); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card h-100">
            <div class="rateb-card-header d-flex justify-content-between align-items-center">
                <span><?php echo __('hr_cc_recent_decisions'); ?></span>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_url(rateb_app_route('hr/decisions')); ?>"><?php echo __('all'); ?></a>
            </div>
            <div class="rateb-card-body p-0">
                <?php if ($recentDecisions === []) { ?>
                    <p class="text-muted small p-3 mb-0"><?php echo __('hr_cc_empty_decisions'); ?></p>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table rateb-table table-sm mb-0">
                        <thead><tr>
                            <th><?php echo __('hr_decision_no'); ?></th>
                            <th><?php echo __('employee'); ?></th>
                            <th><?php echo __('status'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($recentDecisions as $row) { ?>
                            <tr>
                                <td class="rateb-ltr-num"><?php echo $escape((string) ($row['decision_no'] ?? '')); ?></td>
                                <td><?php echo $escape((string) ($row['employee_name'] ?? '')); ?></td>
                                <td><?php echo $escape((string) ($row['status'] ?? '')); ?></td>
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

<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('hr_cc_recent_payrolls'); ?></div>
            <div class="rateb-card-body p-0">
                <?php if ($recentPayrolls === []) { ?>
                    <p class="text-muted small p-3 mb-0"><?php echo __('hr_cc_empty_payrolls'); ?></p>
                <?php } else { ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($recentPayrolls as $row) {
                        $pid = (int) ($row['id'] ?? 0);
                        $label = sprintf('%04d-%02d', (int) ($row['period_year'] ?? 0), (int) ($row['period_month'] ?? 0));
                        ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <a href="<?php echo rateb_url(rateb_app_route('hr/payroll/' . $pid)); ?>" class="rateb-ltr-num"><?php echo $escape($label); ?></a>
                            <span class="small"><?php echo $escape((string) ($row['status'] ?? '')); ?></span>
                        </li>
                    <?php } ?>
                </ul>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('hr_cc_contracts_expiring'); ?></div>
            <div class="rateb-card-body p-0">
                <?php if ($contractsExpiring === []) { ?>
                    <p class="text-muted small p-3 mb-0"><?php echo __('hr_cc_empty_contracts'); ?></p>
                <?php } else { ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($contractsExpiring as $row) {
                        $eid = (int) ($row['employee_id'] ?? 0);
                        ?>
                        <li class="list-group-item">
                            <a href="<?php echo rateb_url(rateb_app_route('hr/employees/' . $eid)); ?>">
                                <?php echo $escape((string) ($row['employee_name'] ?? '')); ?>
                            </a>
                            <div class="small text-muted rateb-ltr-num">
                                <?php echo $escape((string) ($row['contract_no'] ?? '')); ?> · <?php echo $escape((string) ($row['end_date'] ?? '')); ?>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('hr_cc_upcoming_leaves'); ?></div>
            <div class="rateb-card-body p-0">
                <?php if ($upcomingLeaves === []) { ?>
                    <p class="text-muted small p-3 mb-0"><?php echo __('hr_cc_empty_leaves'); ?></p>
                <?php } else { ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($upcomingLeaves as $row) { ?>
                        <li class="list-group-item">
                            <?php echo $escape((string) ($row['employee_name'] ?? '')); ?>
                            <div class="small text-muted rateb-ltr-num">
                                <?php echo $escape((string) ($row['start_date'] ?? '')); ?> → <?php echo $escape((string) ($row['end_date'] ?? '')); ?>
                            </div>
                        </li>
                    <?php } ?>
                </ul>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="rateb-card mb-3">
    <div class="rateb-card-header"><i class="fas fa-user me-1"></i> <?php echo __('hr_cc_360_hub'); ?></div>
    <div class="rateb-card-body">
        <p class="text-muted small"><?php echo __('hr_cc_360_hub_hint'); ?></p>
        <div class="row g-2">
            <?php foreach ($hubLinks as $hub) {
                $tab = (string) ($hub['tab'] ?? '');
                $icon = (string) ($hub['icon'] ?? 'fa-circle');
                ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="rateb-hr-cc-hub-item">
                        <i class="fas <?php echo $escape($icon); ?>"></i>
                        <span><?php echo $escape(__((string) ($hub['label'] ?? ''))); ?></span>
                    </div>
                </div>
            <?php } ?>
        </div>
        <p class="small text-muted mb-0 mt-2"><?php echo __('hr_cc_360_hub_search_note'); ?></p>
    </div>
</div>
</div>

<script src="<?php echo rateb_asset('js/hr-module.js'); ?>" defer></script>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
