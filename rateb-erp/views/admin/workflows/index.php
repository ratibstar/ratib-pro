<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo __('create'); ?> <?php echo __('workflows'); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_url('admin/workflows'); ?>" class="row g-3">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-4">
                <label class="form-label"><?php echo __('name'); ?></label>
                <input class="form-control" name="name" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('entity_type'); ?></label>
                <select class="form-select" name="entity_type">
                    <option value="purchase_request">purchase_request</option>
                    <option value="purchase_order">purchase_order</option>
                    <option value="contract">contract</option>
                    <option value="supplier">supplier</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('company_id'); ?></label>
                <input class="form-control" type="number" name="company_id" placeholder="<?php echo __('optional'); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><?php echo __('save'); ?></button>
            </div>
        </form>
    </div>
</div>
<?php Rateb\App\Core\View::partial('crud-index', [
    'title' => __('workflows'),
    'items' => $workflows ?? [],
    'fields' => [
        ['name' => 'name', 'label' => 'name'],
        ['name' => 'entity_type', 'label' => 'entity_type'],
        ['name' => 'is_active', 'label' => 'active'],
    ],
    'csrf' => $csrf,
    'routePrefix' => 'admin/workflows',
    'bulkEnabled' => false,
    'createEnabled' => false,
    'actionsEnabled' => false,
]); ?>
<div class="rateb-card mt-3">
    <div class="rateb-card-header"><?php echo __('pending_approvals'); ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead><tr><th><?php echo __('companies'); ?></th><th><?php echo __('workflows'); ?></th><th><?php echo __('entity_type'); ?></th><th><?php echo __('status'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($pending)) { ?>
                <tr><td colspan="4" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($pending as $row) { ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($row['company_name'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['workflow_name'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['entity_type'] ?? ''); ?> #<?php echo (int) ($row['entity_id'] ?? 0); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['status'] ?? ''); ?></td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
