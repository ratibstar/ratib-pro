<?php
declare(strict_types=1);

use Rateb\App\Core\View;

/** @var list<array<string, mixed>> $parts */
/** @var list<array<string, mixed>> $inventory_choices */
$parts = $parts ?? [];
$inventoryChoices = $inventory_choices ?? [];
$canManage = !empty($can_manage);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?php echo View::escape((string) ($title ?? __('auto_parts'))); ?></h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_app_url('automotive'); ?>"><?php echo View::escape(__('automotive_dashboard')); ?></a>
</div>

<?php if ($canManage): ?>
<form method="post" action="<?php echo rateb_app_url('automotive/parts'); ?>" class="card mb-4">
    <?php echo rateb_csrf_field(); ?>
    <div class="card-body row g-3">
        <div class="col-md-4">
            <label class="form-label"><?php echo View::escape(__('auto_inventory_item')); ?></label>
            <select class="form-select" name="inventory_id" required>
                <option value=""><?php echo View::escape(__('auto_choose_inventory')); ?></option>
                <?php foreach ($inventoryChoices as $item): ?>
                    <option value="<?php echo (int) $item['id']; ?>">
                        <?php echo View::escape((string) $item['item_name'] . ' #' . $item['id']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label"><?php echo View::escape(__('auto_manufacturer')); ?></label>
            <input class="form-control" name="manufacturer" maxlength="150">
        </div>
        <div class="col-md-2">
            <label class="form-label"><?php echo View::escape(__('auto_brand')); ?></label>
            <input class="form-control" name="brand" maxlength="150">
        </div>
        <div class="col-md-2">
            <label class="form-label"><?php echo View::escape(__('auto_oem_number')); ?></label>
            <input class="form-control" name="oem_number" maxlength="80">
        </div>
        <div class="col-md-2">
            <label class="form-label"><?php echo View::escape(__('status')); ?></label>
            <select class="form-select" name="status">
                <option value="active"><?php echo View::escape(__('auto_status_active')); ?></option>
                <option value="superseded"><?php echo View::escape(__('auto_status_superseded')); ?></option>
                <option value="obsolete"><?php echo View::escape(__('auto_status_obsolete')); ?></option>
            </select>
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit"><?php echo View::escape(__('save')); ?></button>
        </div>
    </div>
</form>
<?php endif; ?>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
            <tr>
                <th><?php echo View::escape(__('auto_inventory_item')); ?></th>
                <th><?php echo View::escape(__('auto_manufacturer')); ?></th>
                <th><?php echo View::escape(__('auto_brand')); ?></th>
                <th><?php echo View::escape(__('auto_oem_number')); ?></th>
                <th><?php echo View::escape(__('status')); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($parts === []): ?>
                <tr><td colspan="5" class="text-muted"><?php echo View::escape(__('no_records')); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($parts as $part): ?>
                <tr>
                    <td><a href="<?php echo rateb_app_url('automotive/parts/' . (int) $part['id']); ?>"><?php echo View::escape((string) ($part['item_name'] ?? '')); ?></a></td>
                    <td><?php echo View::escape((string) ($part['manufacturer'] ?? '')); ?></td>
                    <td><?php echo View::escape((string) ($part['brand'] ?? '')); ?></td>
                    <td><?php echo View::escape((string) ($part['oem_number'] ?? '')); ?></td>
                    <td><?php echo View::escape((string) ($part['status'] ?? '')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
