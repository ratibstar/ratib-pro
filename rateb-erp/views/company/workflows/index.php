<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo __('pending_approvals'); ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead><tr><th><?php echo __('workflows'); ?></th><th><?php echo __('entity_type'); ?></th><th><?php echo __('status'); ?></th><th><?php echo __('actions'); ?></th></tr></thead>
                <tbody>
                <?php if (empty($pending)) { ?>
                <tr><td colspan="4" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($pending as $row) { ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($row['workflow_name'] ?? ''); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['entity_type'] ?? ''); ?> #<?php echo (int) ($row['entity_id'] ?? 0); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape($row['status'] ?? ''); ?></td>
                    <td class="d-flex gap-1">
                        <form method="post" action="<?php echo rateb_url('company/workflows/' . (int) $row['id'] . '/approve'); ?>">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-success"><?php echo __('approve'); ?></button>
                        </form>
                        <form method="post" action="<?php echo rateb_url('company/workflows/' . (int) $row['id'] . '/reject'); ?>">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo __('reject'); ?></button>
                        </form>
                    </td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('workflows'); ?></div>
    <div class="rateb-card-body p-0">
        <?php Rateb\App\Core\View::partial('crud-index', [
            'title' => '',
            'items' => $workflows ?? [],
            'fields' => [
                ['name' => 'name', 'label' => 'name'],
                ['name' => 'entity_type', 'label' => 'entity_type'],
                ['name' => 'is_active', 'label' => 'active'],
            ],
            'csrf' => $csrf,
            'routePrefix' => 'company/workflows',
            'bulkEnabled' => false,
            'createEnabled' => false,
            'actionsEnabled' => false,
        ]); ?>
    </div>
</div>
