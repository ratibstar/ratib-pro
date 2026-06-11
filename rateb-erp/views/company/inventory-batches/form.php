<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_url('company/inventory-batches'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('inventory'); ?></label>
                    <select class="form-select" name="inventory_id" required>
                        <option value=""><?php echo __('select'); ?></option>
                        <?php foreach ($inventory ?? [] as $inv) { ?>
                        <option value="<?php echo (int) $inv['id']; ?>"><?php echo Rateb\App\Core\View::escape($inv['item_name'] ?? ''); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('batch_no'); ?></label>
                    <input class="form-control" type="text" name="batch_no" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('quantity'); ?></label>
                    <input class="form-control" type="number" step="0.001" min="0" name="quantity" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('expiry_date'); ?></label>
                    <input class="form-control" type="date" name="expiry_date">
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('warehouses'); ?></label>
                    <select class="form-select" name="warehouse_id">
                        <option value="">—</option>
                        <?php foreach ($warehouses ?? [] as $wh) { ?>
                        <option value="<?php echo (int) $wh['id']; ?>"><?php echo Rateb\App\Core\View::escape($wh['name'] ?? ''); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url('company/inventory-batches'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
