<?php declare(strict_types=1); ?>
<div class="rateb-page-header mb-3"><h1 class="h4 mb-0"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1></div>
<form method="post" action="<?php echo rateb_url(rateb_app_route('branch-transfers')); ?>" class="rateb-card">
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf ?? ''); ?>">
    <div class="rateb-card-body row g-3">
        <div class="col-md-6">
            <label class="form-label"><?php echo __('transfer_type'); ?></label>
            <select name="transfer_type" class="form-select" required>
                <option value="inventory"><?php echo __('inventory'); ?></option>
                <option value="asset"><?php echo __('assets'); ?></option>
                <option value="employee"><?php echo __('employees'); ?></option>
                <option value="accounting"><?php echo __('accounting_module'); ?></option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label"><?php echo __('source_branch'); ?></label>
            <select name="source_branch_id" class="form-select" required>
                <?php foreach ($branches ?? [] as $b) { ?>
                <option value="<?php echo (int) $b['id']; ?>"><?php echo Rateb\App\Core\View::escape((string) ($b['name'] ?? '')); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label"><?php echo __('dest_branch'); ?></label>
            <select name="dest_branch_id" class="form-select" required>
                <?php foreach ($branches ?? [] as $b) { ?>
                <option value="<?php echo (int) $b['id']; ?>"><?php echo Rateb\App\Core\View::escape((string) ($b['name'] ?? '')); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="col-md-3"><label class="form-label"><?php echo __('quantity'); ?></label><input type="number" step="0.0001" name="quantity" class="form-control"></div>
        <div class="col-md-3"><label class="form-label"><?php echo __('amount'); ?></label><input type="number" step="0.01" name="amount" class="form-control"></div>
        <div class="col-12"><label class="form-label"><?php echo __('notes'); ?></label><textarea name="notes" class="form-control" rows="3"></textarea></div>
        <div class="col-12"><button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button></div>
    </div>
</form>
