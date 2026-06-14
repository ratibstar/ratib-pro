<?php
$isEdit = !empty($item);
$action = $isEdit
    ? rateb_app_url('bank-accounts/' . (int) $item['id'])
    : rateb_app_url('bank-accounts');
$coaOptions = $lookups ?? [];
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<form method="post" action="<?php echo $action; ?>" class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label"><?php echo __('name'); ?> <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control" required maxlength="120"
                       value="<?php echo Rateb\App\Core\View::escape((string) ($item['name'] ?? '')); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('bank_name'); ?></label>
                <input type="text" name="bank_name" class="form-control" maxlength="120"
                       value="<?php echo Rateb\App\Core\View::escape((string) ($item['bank_name'] ?? '')); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('account_number'); ?></label>
                <input type="text" name="account_number" class="form-control" maxlength="50"
                       value="<?php echo Rateb\App\Core\View::escape((string) ($item['account_number'] ?? '')); ?>">
            </div>
            <?php if ($isEdit && $coaOptions !== []) { ?>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('chart_of_accounts'); ?></label>
                <select name="chart_account_id" class="form-select" disabled>
                    <option value=""><?php echo __('select'); ?></option>
                    <?php foreach ($coaOptions as $opt) { ?>
                    <option value="<?php echo (int) $opt['value']; ?>"<?php echo (int)($item['chart_account_id'] ?? 0) === (int)$opt['value'] ? ' selected' : ''; ?>>
                        <?php echo Rateb\App\Core\View::escape($opt['label']); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <?php } ?>
            <?php if (!$isEdit) { ?>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('opening_balance'); ?></label>
                <input type="number" step="0.01" name="opening_balance" class="form-control" value="0">
            </div>
            <?php } ?>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_default" value="1" id="is_default"
                           <?php echo !empty($item['is_default']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="is_default"><?php echo __('default_bank_account'); ?></label>
                </div>
            </div>
        </div>
    </div>
    <div class="rateb-card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
        <a href="<?php echo rateb_app_url('bank-accounts'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
    </div>
</form>
