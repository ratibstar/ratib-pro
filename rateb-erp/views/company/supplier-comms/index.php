<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<string, mixed> $filters */
/** @var array<int, array{value:string|int,label:string}> $supplierOptions */
/** @var array<int, array{value:string,label:string}> $channelOptions */
/** @var array<int, array{name:string,label:string,type?:string}> $columns */
$listUrl = rateb_app_url('supplier-comms');
$routePrefix = $routePrefix ?? rateb_app_route('supplier-comms');
$canManage = $canManage ?? rateb_can_manage_entity('supplier-comms');
$bulkEnabled = $bulkEnabled ?? false;
$actionsEnabled = $actionsEnabled ?? true;
$columns = $columns ?? [];
$colspan = count($columns) + ($bulkEnabled ? 1 : 0) + ($actionsEnabled ? 1 : 0);

$channelLabel = static function (string $ch): string {
    $key = 'comm_channel_' . $ch;
    $t = __($key);
    return $t !== $key ? $t : $ch;
};
$channelIcon = static function (string $ch): string {
    if ($ch === 'email') {
        return 'fa-envelope';
    }
    if ($ch === 'sms') {
        return 'fa-sms';
    }
    if ($ch === 'whatsapp') {
        return 'fa-brands fa-whatsapp';
    }
    if ($ch === 'phone') {
        return 'fa-phone';
    }
    if ($ch === 'meeting') {
        return 'fa-handshake';
    }
    return 'fa-comment';
};
$formatCell = static function ($val, array $col) use ($channelLabel, $channelIcon): string {
    $type = (string) ($col['type'] ?? '');
    $name = (string) ($col['name'] ?? '');
    if ($type === 'channel' || $name === 'channel') {
        $ch = (string) $val;
        $icon = $channelIcon($ch);
        $label = $channelLabel($ch);
        return '<span class="rateb-sc-channel-badge"><i class="fas ' . Rateb\App\Core\View::escape($icon) . '"></i> '
            . Rateb\App\Core\View::escape($label) . '</span>';
    }
    if ($val === null || $val === '') {
        return '—';
    }
    return Rateb\App\Core\View::escape((string) $val);
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
    </div>

    <?php if ($canManage) { ?>
    <div class="rateb-sc-card rateb-sc-form-card" id="rateb-sc-form">
        <div class="rateb-sc-card-header">
            <span><i class="fas fa-comments text-primary"></i> <?php echo __('supplier_comms_create'); ?></span>
        </div>
        <div class="rateb-sc-card-body">
            <?php if (empty($supplierOptions)) { ?>
            <div class="alert alert-warning mb-0"><?php echo __('no_records'); ?> — <?php echo __('suppliers'); ?></div>
            <?php } else { ?>
            <form method="post" action="<?php echo rateb_app_url('supplier-comms'); ?>" class="rateb-sc-form-grid">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label rateb-form-label"><?php echo __('suppliers'); ?></label>
                        <select class="form-select rateb-form-control" name="supplier_id" required>
                            <option value=""><?php echo __('select'); ?>…</option>
                            <?php foreach ($supplierOptions as $opt) { ?>
                            <option value="<?php echo Rateb\App\Core\View::escape((string) $opt['value']); ?>"><?php echo Rateb\App\Core\View::escape($opt['label']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label rateb-form-label"><?php echo __('comm_channel'); ?></label>
                        <select class="form-select rateb-form-control" name="channel" required>
                            <?php foreach ($channelOptions as $opt) { ?>
                            <option value="<?php echo Rateb\App\Core\View::escape($opt['value']); ?>"<?php echo $opt['value'] === 'email' ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($opt['label']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label rateb-form-label"><?php echo __('subject'); ?></label>
                        <input class="form-control rateb-form-control" type="text" name="subject" required maxlength="255" placeholder="<?php echo __('subject'); ?>…">
                    </div>
                    <div class="col-12">
                        <label class="form-label rateb-form-label"><?php echo __('notes'); ?></label>
                        <textarea class="form-control rateb-form-control" name="body" rows="4" placeholder="<?php echo __('notes'); ?>…"></textarea>
                    </div>
                </div>
                <div class="rateb-sc-form-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo __('save'); ?></button>
                    <button type="reset" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></button>
                </div>
            </form>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

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
                            <label class="form-label rateb-form-label"><?php echo __('date_from'); ?></label>
                            <input class="form-control rateb-form-control" type="date" name="date_from" value="<?php echo Rateb\App\Core\View::escape((string) ($filters['date_from'] ?? '')); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label rateb-form-label"><?php echo __('date_to'); ?></label>
                            <input class="form-control rateb-form-control" type="date" name="date_to" value="<?php echo Rateb\App\Core\View::escape((string) ($filters['date_to'] ?? '')); ?>">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-grow-1"><i class="fas fa-search"></i> <?php echo __('search'); ?></button>
                            <a href="<?php echo Rateb\App\Core\View::escape($listUrl); ?>" class="btn btn-outline-secondary"><?php echo __('reset'); ?></a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="rateb-sc-card">
        <div class="rateb-sc-card-header">
            <span><i class="fas fa-list text-primary"></i> <?php echo __('supplier_comms_log'); ?></span>
        </div>
        <div class="rateb-sc-card-body">
            <?php if ($canManage) { ?>
            <div class="rateb-sc-table-toolbar">
                <a href="#rateb-sc-form" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> <?php echo __('supplier_comms_create'); ?></a>
            </div>
            <?php } ?>

            <?php Rateb\App\Core\View::partial('table-search', [
                'mode' => 'server',
                'search' => $search ?? '',
                'routePrefix' => $routePrefix,
            ]); ?>

            <div class="rateb-table-wrap" data-rateb-table-search-host="1">
                <table class="table table-hover rateb-table mb-0" data-rateb-bulk-table="<?php echo $bulkEnabled ? '1' : '0'; ?>">
                    <thead>
                    <tr>
                        <?php foreach ($columns as $col) { ?>
                        <th><?php echo Rateb\App\Core\View::escape(rateb_label((string) ($col['label'] ?? $col['name']))); ?></th>
                        <?php } ?>
                        <?php if ($actionsEnabled) { ?>
                        <th class="rateb-th-actions"><?php echo __('actions'); ?></th>
                        <?php } ?>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($items)) { ?>
                    <tr><td colspan="<?php echo $colspan; ?>" class="text-muted text-center py-4"><?php echo __('no_records'); ?></td></tr>
                    <?php } else {
                        foreach ($items as $row) {
                            $id = (int) ($row['id'] ?? 0);
                            ?>
                    <tr>
                        <?php foreach ($columns as $col) {
                            $type = (string) ($col['type'] ?? '');
                            $val = $row[$col['name']] ?? '';
                            if ($type === 'channel') {
                                $ch = (string) $val;
                                $label = $channelLabel($ch);
                                $icon = $channelIcon($ch);
                                $title = Rateb\App\Core\View::escape($label);
                                echo '<td class="rateb-cell-clip" title="' . $title . '"><span class="rateb-sc-channel-badge"><i class="fas ' . Rateb\App\Core\View::escape($icon) . '"></i> ' . $title . '</span></td>';
                                continue;
                            }
                            if ($type === 'datetime') {
                                $col['type'] = 'clip';
                            }
                            Rateb\App\Core\View::partial('table-cell', ['value' => $val, 'col' => $col]);
                        } ?>
                        <?php if ($actionsEnabled) { ?>
                        <td class="rateb-actions-cell text-nowrap">
                            <div class="rateb-actions">
                                <?php if ($canManage) { ?>
                                <a href="<?php echo rateb_url($routePrefix . '/' . $id . '/edit'); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo __('edit'); ?>"><i class="fas fa-edit"></i></a>
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

            <?php Rateb\App\Core\View::partial('pagination', [
                'page' => $page ?? 1,
                'total' => $total ?? 0,
                'limit' => $limit ?? 20,
                'routePrefix' => $routePrefix,
            ]); ?>
        </div>
    </div>
</div>
