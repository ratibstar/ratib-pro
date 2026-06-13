<?php
/** @var array<int,array<string,mixed>> $inventory */
/** @var array<int,array<string,mixed>> $warehouses */
/** @var string $csrf */
?>
<h1><?php echo __('create'); ?> <?php echo __('warehouse_transfers'); ?></h1>
<form method="post" action="<?php echo rateb_app_url('warehouse-transfers'); ?>">
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <div class="mb-3">
        <label class="form-label"><?php echo __('inventory'); ?></label>
        <select name="inventory_id" class="form-select" required>
            <?php foreach ($inventory as $i) { ?>
                <option value="<?php echo (int) $i['id']; ?>"><?php echo Rateb\App\Core\View::escape((string) ($i['item_name'] ?? '')); ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label"><?php echo __('from'); ?></label>
        <select name="source_warehouse_id" class="form-select" required>
            <?php foreach ($warehouses as $w) { ?>
                <option value="<?php echo (int) $w['id']; ?>"><?php echo Rateb\App\Core\View::escape((string) ($w['name'] ?? '')); ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label"><?php echo __('to'); ?></label>
        <select name="destination_warehouse_id" class="form-select" required>
            <?php foreach ($warehouses as $w) { ?>
                <option value="<?php echo (int) $w['id']; ?>"><?php echo Rateb\App\Core\View::escape((string) ($w['name'] ?? '')); ?></option>
            <?php } ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label"><?php echo __('quantity'); ?></label>
        <input type="number" step="0.01" name="quantity" class="form-control" required>
    </div>
    <div class="mb-3">
        <label class="form-label"><?php echo __('notes'); ?></label>
        <textarea name="notes" class="form-control" rows="2"></textarea>
    </div>
    <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
</form>
