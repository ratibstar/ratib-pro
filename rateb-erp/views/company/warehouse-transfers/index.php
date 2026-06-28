<?php
/** @var array<int,array<string,mixed>> $items */
/** @var string $csrf */
/** @var bool $canManage */
/** @var string|null $exportRoute */
/** @var bool|null $exportEnabled */
?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? __('warehouse_transfers')); ?></span>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php if (!empty($exportRoute) && ($exportEnabled ?? true)) {
                Rateb\App\Core\View::partial('export-toolbar', [
                    'exportRoute' => $exportRoute,
                    'exportEnabled' => true,
                ]);
            } ?>
            <?php if ($canManage) { ?>
            <a href="<?php echo rateb_app_url('warehouse-transfers/create'); ?>" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> <?php echo __('create'); ?>
            </a>
            <?php } ?>
        </div>
    </div>
    <div class="rateb-card-body">
        <?php Rateb\App\Core\View::partial('table-search', ['mode' => 'client']); ?>
        <div class="table-responsive" data-rateb-table-search-host="1">
            <table class="table table-hover rateb-table mb-0">
                <thead>
                    <tr>
                        <th><?php echo __('record_id'); ?></th>
                        <th><?php echo __('item_name'); ?></th>
                        <th><?php echo __('from'); ?></th>
                        <th><?php echo __('to'); ?></th>
                        <th><?php echo __('quantity'); ?></th>
                        <th><?php echo __('status'); ?></th>
                        <th><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($items)) { ?>
                    <tr><td colspan="7" class="text-muted text-center py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else {
                    foreach ($items as $row) { ?>
                    <tr>
                        <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($row['transfer_no'] ?? '')); ?></td>
                        <td><?php echo Rateb\App\Core\View::escape((string) ($row['item_name'] ?? '')); ?></td>
                        <td><?php echo Rateb\App\Core\View::escape((string) ($row['source_name'] ?? '')); ?></td>
                        <td><?php echo Rateb\App\Core\View::escape((string) ($row['dest_name'] ?? '')); ?></td>
                        <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($row['quantity'] ?? '')); ?></td>
                        <td><?php echo Rateb\App\Core\View::escape(rateb_enum_label((string) ($row['status'] ?? ''))); ?></td>
                        <td class="rateb-actions text-nowrap">
                        <?php if ($canManage && ($row['status'] ?? '') === 'pending') { ?>
                            <form method="post" action="<?php echo rateb_app_url('warehouse-transfers/' . (int) $row['id'] . '/approve'); ?>" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-check"></i> <?php echo __('approve'); ?></button>
                            </form>
                        <?php } else { ?>
                            <span class="text-muted">—</span>
                        <?php } ?>
                        </td>
                    </tr>
                    <?php }
                } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
