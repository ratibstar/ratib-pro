<?php
$items = $items ?? [];
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? __('rfq')); ?></span>
        <?php if ($createEnabled ?? true) { ?>
        <a href="<?php echo rateb_url($routePrefix . '/create'); ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo __('create'); ?>
        </a>
        <?php } ?>
    </div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                    <tr>
                        <th><?php echo __('rfq_no'); ?></th>
                        <th><?php echo __('title'); ?></th>
                        <th><?php echo __('status'); ?></th>
                        <th><?php echo __('deadline'); ?></th>
                        <th><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)) { ?>
                    <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                    <?php } else { foreach ($items as $row) { ?>
                    <tr>
                        <td><?php echo Rateb\App\Core\View::escape($row['rfq_no'] ?? ''); ?></td>
                        <td><?php echo Rateb\App\Core\View::escape($row['title'] ?? ''); ?></td>
                        <td><?php echo Rateb\App\Core\View::escape($row['status'] ?? ''); ?></td>
                        <td><?php echo Rateb\App\Core\View::escape($row['deadline'] ?? ''); ?></td>
                        <td class="text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_app_url('rfq/' . (int) $row['id'] . '/compare'); ?>"><?php echo __('quotation_compare'); ?></a>
                            <?php if ($actionsEnabled ?? true) { ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_url($routePrefix . '/' . (int) $row['id'] . '/edit'); ?>"><?php echo __('edit'); ?></a>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
