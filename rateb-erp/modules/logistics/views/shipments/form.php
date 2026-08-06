<?php
declare(strict_types=1);

use Rateb\App\Core\View;

\Rateb\App\Core\View::partial('crud-form', get_defined_vars());

/** @var array<string,mixed> $item */
/** @var list<string> $nextStatuses */
/** @var array<int,array<string,mixed>> $history */
$item = $item ?? [];
$nextStatuses = $nextStatuses ?? [];
$history = $history ?? [];
$isEdit = (int) ($item['id'] ?? 0) > 0;
if (!$isEdit) {
    return;
}
?>
<?php
$isDispatched = !empty($isDispatched);
$canDispatch = !empty($canDispatch);
$statusNow = (string) ($item['status'] ?? '');
$dispatchBlocked = $isDispatched || in_array($statusNow, ['out_for_delivery', 'delivered', 'failed'], true);
if ($canDispatch && !$dispatchBlocked) { ?>
<div class="rateb-card mt-3">
    <div class="rateb-card-header"><?php echo __('logistics_dispatch'); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_app_url('logistics/shipments/' . (int) $item['id'] . '/dispatch'); ?>" class="row g-2 align-items-end">
            <input type="hidden" name="_csrf" value="<?php echo View::escape((string) ($csrf ?? '')); ?>">
            <div class="col-md-4">
                <label class="form-label"><?php echo __('inventory'); ?></label>
                <input type="number" name="inventory_id" class="form-control" min="1" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('quantity'); ?></label>
                <input type="number" name="quantity" class="form-control" min="0.01" step="0.01" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('warehouses'); ?></label>
                <input type="number" name="warehouse_id" class="form-control" min="0">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-success w-100"><?php echo __('logistics_dispatch'); ?></button>
            </div>
        </form>
        <p class="small text-muted mt-2 mb-0"><?php echo __('logistics_dispatch_hint'); ?></p>
    </div>
</div>
<?php } elseif ($isDispatched) { ?>
<div class="alert alert-info mt-3 mb-0"><?php echo __('logistics_dispatch_already_done'); ?></div>
<?php } ?>

<div class="rateb-card mt-3">
    <div class="rateb-card-header"><?php echo __('logistics_status_actions'); ?></div>
    <div class="rateb-card-body">
        <?php if ($nextStatuses === []) { ?>
            <p class="text-muted mb-0"><?php echo __('logistics_no_transitions'); ?></p>
        <?php } else { ?>
            <form method="post" action="<?php echo rateb_app_url('logistics/shipments/' . (int) $item['id'] . '/transition'); ?>" class="row g-2 align-items-end">
                <input type="hidden" name="_csrf" value="<?php echo View::escape((string) ($csrf ?? '')); ?>">
                <div class="col-md-3">
                    <label class="form-label"><?php echo __('status'); ?></label>
                    <select name="to_status" class="form-select" required>
                        <?php foreach ($nextStatuses as $status) { ?>
                            <option value="<?php echo View::escape($status); ?>"><?php echo View::escape(__('logistics_status_' . $status)); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?php echo __('logistics_receiver_name'); ?></label>
                    <input type="text" name="receiver_name" class="form-control" maxlength="160">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?php echo __('notes'); ?></label>
                    <input type="text" name="reason" class="form-control" maxlength="255">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><?php echo __('logistics_apply_transition'); ?></button>
                </div>
            </form>
        <?php } ?>
    </div>
</div>

<?php if ($history !== []) { ?>
<div class="rateb-card mt-3">
    <div class="rateb-card-header"><?php echo __('logistics_status_history'); ?></div>
    <div class="rateb-card-body p-0">
        <table class="table table-sm mb-0">
            <thead><tr><th><?php echo __('from'); ?></th><th><?php echo __('to'); ?></th><th><?php echo __('notes'); ?></th><th><?php echo __('created_at'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($history as $row) { ?>
                <tr>
                    <td><?php echo View::escape((string) ($row['from_status'] ?? '')); ?></td>
                    <td><?php echo View::escape((string) ($row['to_status'] ?? '')); ?></td>
                    <td><?php echo View::escape((string) ($row['reason'] ?? '')); ?></td>
                    <td><?php echo View::escape((string) ($row['created_at'] ?? '')); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php } ?>
