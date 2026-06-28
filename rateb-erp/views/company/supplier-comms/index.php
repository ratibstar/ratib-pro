<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<string, mixed> $filters */
/** @var array<string, mixed> $stats */
/** @var \Rateb\App\Services\SupplierCommService $commSvc */
$listUrl = rateb_app_url('supplier-comms');
$routePrefix = $routePrefix ?? rateb_app_route('supplier-comms');
$canManage = $canManage ?? rateb_can_manage_entity('supplier-comms');
$columns = $columns ?? [];
$colspan = count($columns) + ($actionsEnabled ?? true ? 1 : 0);
$stats = $stats ?? ['total' => 0, 'this_month' => 0, 'pending_followups' => 0, 'by_supplier' => 0, 'distinct_suppliers' => 0];
$upcomingFollowUps = $upcomingFollowUps ?? [];
$topSuppliers = $topSuppliers ?? [];
$supplierHistory = $supplierHistory ?? [];
$commSvc = $commSvc ?? new \Rateb\App\Services\SupplierCommService();
$lookups = $lookups ?? [];
$formFields = $formFields ?? [];

$channelLabel = static function (string $ch): string {
    $key = 'comm_channel_' . $ch;
    $t = __($key);
    return $t !== $key ? $t : $ch;
};
$channelIcon = static function (string $ch): string {
    return match ($ch) {
        'email' => 'fa-envelope',
        'sms' => 'fa-sms',
        'whatsapp' => 'fa-brands fa-whatsapp',
        'phone' => 'fa-phone',
        'meeting' => 'fa-handshake',
        'field_visit' => 'fa-map-location-dot',
        default => 'fa-comment',
    };
};
?>
<?php if (!empty($moduleCss)) { ?>
<link href="<?php echo Rateb\App\Core\View::escape($moduleCss); ?>" rel="stylesheet">
<?php } ?>

<div class="rateb-sc-page">
    <div class="rateb-sc-page-header">
        <div>
            <nav class="rateb-sc-breadcrumb" aria-label="breadcrumb">
                <a href="<?php echo rateb_app_url('dashboard'); ?>"><?php echo __('dashboard'); ?></a>
                <span class="mx-1">/</span>
                <a href="<?php echo rateb_app_url('suppliers'); ?>"><?php echo __('suppliers'); ?></a>
                <span class="mx-1">/</span>
                <span><?php echo __('supplier_comms'); ?></span>
            </nav>
            <h2 class="h4 mb-0"><?php echo __('supplier_comms'); ?></h2>
        </div>
        <?php if ($canManage) { ?>
        <a href="<?php echo rateb_app_url('supplier-comms/create'); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> <?php echo __('supplier_comms_create'); ?>
        </a>
        <?php } ?>
    </div>

    <div class="row g-3 rateb-sc-stats-row">
        <div class="col-6 col-md-3">
            <div class="rateb-sc-stat-card">
                <div class="rateb-sc-stat-value"><?php echo (int) $stats['total']; ?></div>
                <div class="rateb-sc-stat-label"><?php echo __('comm_stat_total'); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="rateb-sc-stat-card">
                <div class="rateb-sc-stat-value"><?php echo (int) $stats['this_month']; ?></div>
                <div class="rateb-sc-stat-label"><?php echo __('comm_stat_this_month'); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="rateb-sc-stat-card rateb-sc-stat-warn">
                <div class="rateb-sc-stat-value"><?php echo (int) $stats['pending_followups']; ?></div>
                <div class="rateb-sc-stat-label"><?php echo __('comm_stat_followups'); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="rateb-sc-stat-card">
                <div class="rateb-sc-stat-value"><?php echo (int) ($stats['distinct_suppliers'] ?? 0); ?></div>
                <div class="rateb-sc-stat-label"><?php echo __('comm_stat_active_suppliers'); ?></div>
            </div>
        </div>
    </div>

    <?php if ($upcomingFollowUps !== []) { ?>
    <div class="rateb-sc-card rateb-sc-alert-card">
        <div class="rateb-sc-card-header"><i class="fas fa-bell text-warning"></i> <?php echo __('comm_followup_alerts'); ?></div>
        <div class="rateb-sc-card-body py-2">
            <ul class="list-unstyled mb-0 small">
                <?php foreach ($upcomingFollowUps as $fu) { ?>
                <li class="py-1 border-bottom border-secondary-subtle">
                    <strong><?php echo Rateb\App\Core\View::escape((string) ($fu['supplier_name'] ?? '')); ?></strong>
                    — <?php echo Rateb\App\Core\View::escape((string) ($fu['subject'] ?? '')); ?>
                    <span class="text-muted">(<?php echo Rateb\App\Core\View::formatDate((string) ($fu['follow_up_date'] ?? '')); ?>)</span>
                    <a href="<?php echo rateb_url($routePrefix . '/' . (int) ($fu['id'] ?? 0) . '/edit'); ?>" class="ms-1"><?php echo __('view'); ?></a>
                </li>
                <?php } ?>
            </ul>
        </div>
    </div>
    <?php } ?>

    <div class="row g-3">
        <div class="col-lg-8">

            <div class="rateb-sc-card rateb-sc-filter-card">
                <div class="rateb-sc-card-header rateb-sc-filter-toggle" data-bs-toggle="collapse" data-bs-target="#rateb-sc-filter-body" aria-expanded="true">
                    <span><i class="fas fa-filter text-primary"></i> <?php echo __('supplier_comms_search_filter'); ?></span>
                    <i class="fas fa-chevron-down small text-muted"></i>
                </div>
                <div class="collapse show" id="rateb-sc-filter-body">
                    <div class="rateb-sc-card-body pt-3">
                        <form method="get" action="<?php echo Rateb\App\Core\View::escape($listUrl); ?>">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label rateb-form-label"><?php echo __('suppliers'); ?></label>
                                    <select class="form-select rateb-form-control" name="supplier_id">
                                        <option value=""><?php echo __('all'); ?></option>
                                        <?php foreach ($supplierOptions as $opt) { ?>
                                        <option value="<?php echo Rateb\App\Core\View::escape((string) $opt['value']); ?>"<?php echo (string) ($filters['supplier_id'] ?? '') === (string) $opt['value'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($opt['label']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label rateb-form-label"><?php echo __('comm_channel'); ?></label>
                                    <select class="form-select rateb-form-control" name="channel">
                                        <option value=""><?php echo __('all'); ?></option>
                                        <?php foreach ($channelOptions as $opt) { ?>
                                        <option value="<?php echo Rateb\App\Core\View::escape($opt['value']); ?>"<?php echo (string) ($filters['channel'] ?? '') === (string) $opt['value'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($opt['label']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label rateb-form-label"><?php echo __('comm_status'); ?></label>
                                    <select class="form-select rateb-form-control" name="comm_status">
                                        <option value=""><?php echo __('all'); ?></option>
                                        <?php foreach (($statusOptions ?? []) as $opt) { ?>
                                        <option value="<?php echo Rateb\App\Core\View::escape($opt['value']); ?>"<?php echo (string) ($filters['comm_status'] ?? '') === (string) $opt['value'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($opt['label']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label rateb-form-label"><?php echo __('date_from'); ?></label>
                                    <input class="form-control rateb-form-control" type="date" name="date_from" value="<?php echo Rateb\App\Core\View::escape((string) ($filters['date_from'] ?? '')); ?>">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label rateb-form-label"><?php echo __('date_to'); ?></label>
                                    <input class="form-control rateb-form-control" type="date" name="date_to" value="<?php echo Rateb\App\Core\View::escape((string) ($filters['date_to'] ?? '')); ?>">
                                </div>
                                <div class="col-md-1">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="show_archived" value="1" id="sc_show_archived"<?php echo !empty($filters['show_archived']) ? ' checked' : ''; ?>>
                                        <label class="form-check-label small" for="sc_show_archived"><?php echo __('archived'); ?></label>
                                    </div>
                                </div>
                                <div class="col-12 d-flex gap-2">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> <?php echo __('search'); ?></button>
                                    <a href="<?php echo Rateb\App\Core\View::escape($listUrl); ?>" class="btn btn-outline-secondary"><?php echo __('reset'); ?></a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="rateb-sc-card">
                <div class="rateb-sc-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="fas fa-list text-primary"></i> <?php echo __('supplier_comms_log'); ?></span>
                    <?php if ($canManage) { ?>
                    <a href="<?php echo rateb_app_url('supplier-comms/create'); ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> <?php echo __('supplier_comms_create'); ?>
                    </a>
                    <?php } ?>
                </div>
                <div class="rateb-sc-card-body">
                    <?php Rateb\App\Core\View::partial('table-search', ['mode' => 'server', 'search' => $search ?? '', 'routePrefix' => $routePrefix]); ?>
                    <div class="rateb-table-wrap" data-rateb-table-search-host="1">
                        <table class="table table-hover rateb-table mb-0">
                            <thead>
                            <tr>
                                <?php foreach ($columns as $col) { ?>
                                <th><?php echo Rateb\App\Core\View::escape(rateb_label((string) ($col['label'] ?? $col['name']))); ?></th>
                                <?php } ?>
                                <?php if ($actionsEnabled ?? true) { ?>
                                <th class="rateb-th-actions"><?php echo __('actions'); ?></th>
                                <?php } ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($items)) { ?>
                            <tr><td colspan="<?php echo $colspan; ?>" class="text-muted text-center py-4"><?php echo __('no_records'); ?></td></tr>
                            <?php } else {
                                foreach ($items as $row) {
                                    $id = (int) ($row['id'] ?? 0); ?>
                            <tr<?php echo (int) ($row['is_archived'] ?? 0) === 1 ? ' class="opacity-75"' : ''; ?>>
                                <?php foreach ($columns as $col) {
                                    $type = (string) ($col['type'] ?? '');
                                    $val = $row[$col['name']] ?? '';
                                    if ($type === 'channel') {
                                        $ch = (string) $val;
                                        $label = $channelLabel($ch);
                                        $icon = $channelIcon($ch);
                                        echo '<td class="rateb-cell-clip"><span class="rateb-sc-channel-badge"><i class="fas ' . Rateb\App\Core\View::escape($icon) . '"></i> ' . Rateb\App\Core\View::escape($label) . '</span></td>';
                                        continue;
                                    }
                                    Rateb\App\Core\View::partial('table-cell', ['value' => $val, 'col' => $col]);
                                } ?>
                                <?php if ($actionsEnabled ?? true) { ?>
                                <td class="rateb-actions-cell text-nowrap">
                                    <div class="rateb-actions">
                                        <?php if ($canManage) { ?>
                                        <a href="<?php echo rateb_url($routePrefix . '/' . $id . '/edit'); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo __('edit'); ?>"><i class="fas fa-edit"></i></a>
                                        <a href="<?php echo rateb_url($routePrefix . '/' . $id . '/print'); ?>" class="btn btn-sm btn-outline-secondary" title="<?php echo __('print'); ?>" target="_blank"><i class="fas fa-print"></i></a>
                                        <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $id . '/delete'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_delete')); ?>">
                                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo __('delete'); ?>"><i class="fas fa-trash"></i></button>
                                        </form>
                                        <?php } ?>
                                    </div>
                                </td>
                                <?php } ?>
                            </tr>
                            <?php }
                            } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php Rateb\App\Core\View::partial('pagination', ['page' => $page ?? 1, 'total' => $total ?? 0, 'limit' => $limit ?? rateb_list_per_page(), 'routePrefix' => $routePrefix]); ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="rateb-sc-card mb-3">
                <div class="rateb-sc-card-header"><?php echo __('comm_supplier_history'); ?></div>
                <div class="rateb-sc-card-body p-0" id="sc_supplier_history"
                    data-empty="<?php echo Rateb\App\Core\View::escape(__('no_records')); ?>"
                    data-col-date="<?php echo Rateb\App\Core\View::escape(__('comm_date')); ?>"
                    data-col-subject="<?php echo Rateb\App\Core\View::escape(__('subject')); ?>"
                    data-col-status="<?php echo Rateb\App\Core\View::escape(__('comm_status')); ?>"
                    data-initial-supplier="<?php echo (int) ($filters['supplier_id'] ?? 0); ?>">
                    <?php if (empty($supplierHistory)) { ?>
                    <p class="text-muted small p-3 mb-0"><?php echo __('comm_history_hint'); ?></p>
                    <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm rateb-table mb-0">
                            <thead><tr>
                                <th><?php echo __('comm_date'); ?></th>
                                <th><?php echo __('subject'); ?></th>
                                <th><?php echo __('comm_status'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($supplierHistory as $hist) {
                                $st = (string) ($hist['comm_status'] ?? 'new'); ?>
                            <tr>
                                <td><?php echo Rateb\App\Core\View::formatDate($hist['comm_date'] ?? substr((string) ($hist['created_at'] ?? ''), 0, 10)); ?></td>
                                <td class="rateb-cell-clip"><?php echo Rateb\App\Core\View::escape((string) ($hist['subject'] ?? '')); ?></td>
                                <td><span class="badge bg-<?php echo $commSvc->statusBadgeClass($st); ?>"><?php echo Rateb\App\Core\View::escape(__('comm_status_' . $st)); ?></span></td>
                            </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php if ($topSuppliers !== []) { ?>
            <div class="rateb-sc-card">
                <div class="rateb-sc-card-header"><?php echo __('comm_top_suppliers'); ?></div>
                <div class="rateb-sc-card-body py-2">
                    <ul class="list-unstyled mb-0 small">
                        <?php foreach ($topSuppliers as $ts) { ?>
                        <li class="d-flex justify-content-between py-1 border-bottom border-secondary-subtle">
                            <a href="<?php echo Rateb\App\Core\View::escape($listUrl . '?supplier_id=' . (int) ($ts['supplier_id'] ?? 0)); ?>" class="text-decoration-none">
                                <?php echo Rateb\App\Core\View::escape((string) ($ts['supplier_name'] ?? '')); ?>
                            </a>
                            <span class="badge bg-primary"><?php echo (int) ($ts['cnt'] ?? 0); ?></span>
                        </li>
                        <?php } ?>
                    </ul>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>

<?php
$mailto = \Rateb\App\Core\SessionManager::get('rateb_comm_mailto');
if (is_string($mailto) && $mailto !== '') {
    \Rateb\App\Core\SessionManager::set('rateb_comm_mailto', null);
}
?>
<?php if (!empty($mailto)) { ?>
<script>window.addEventListener('load', function () { window.location.href = <?php echo json_encode($mailto, JSON_UNESCAPED_UNICODE); ?>; });</script>
<?php } ?>
