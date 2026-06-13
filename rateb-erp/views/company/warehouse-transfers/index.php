<?php
/** @var array<int,array<string,mixed>> $items */
/** @var string $csrf */
/** @var bool $canManage */
?>
<h1><?php echo __('warehouse_transfers'); ?></h1>
<?php if ($canManage) { ?>
<p><a href="<?php echo rateb_app_url('warehouse-transfers/create'); ?>" class="btn btn-primary btn-sm"><?php echo __('create'); ?></a></p>
<?php } ?>
<table class="table table-sm">
    <thead><tr><th>No</th><th><?php echo __('item_name'); ?></th><th><?php echo __('from'); ?></th><th><?php echo __('to'); ?></th><th><?php echo __('quantity'); ?></th><th><?php echo __('status'); ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($items as $row) { ?>
        <tr>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['transfer_no'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['item_name'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['source_name'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['dest_name'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['quantity'] ?? '')); ?></td>
            <td><?php echo Rateb\App\Core\View::escape((string) ($row['status'] ?? '')); ?></td>
            <td>
            <?php if ($canManage && ($row['status'] ?? '') === 'pending') { ?>
                <form method="post" action="<?php echo rateb_app_url('warehouse-transfers/' . (int) $row['id'] . '/approve'); ?>" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <button type="submit" class="btn btn-sm btn-success"><?php echo __('approve'); ?></button>
                </form>
            <?php } ?>
            </td>
        </tr>
    <?php } ?>
    </tbody>
</table>
