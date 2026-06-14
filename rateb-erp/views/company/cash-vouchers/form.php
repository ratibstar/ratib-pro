<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<form method="post" action="<?php echo rateb_app_url('cash-vouchers'); ?>" class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label"><?php echo __('voucher_type'); ?></label>
                <select name="voucher_type" class="form-select" required>
                    <option value="receipt"><?php echo __('receipt_voucher'); ?></option>
                    <option value="payment"><?php echo __('payment_voucher'); ?></option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('evaluation_date'); ?></label>
                <input type="date" name="voucher_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('amount'); ?></label>
                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('party_name'); ?></label>
                <input type="text" name="party_name" class="form-control" maxlength="200">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('counter_account'); ?></label>
                <select name="counter_account_id" class="form-select" required>
                    <option value=""><?php echo __('select_account'); ?></option>
                    <?php foreach ($accounts as $acc) {
                        $label = $acc['code'] . ' — ' . (rateb_locale() === 'ar' && !empty($acc['name_ar']) ? $acc['name_ar'] : $acc['name']);
                        ?>
                    <option value="<?php echo (int) $acc['id']; ?>"><?php echo Rateb\App\Core\View::escape($label); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('bank_account'); ?></label>
                <select name="bank_account_id" class="form-select">
                    <option value=""><?php echo __('petty_cash_default'); ?></option>
                    <?php foreach ($bankAccounts ?? [] as $ba) { ?>
                    <option value="<?php echo (int) $ba['id']; ?>">
                        <?php echo Rateb\App\Core\View::escape($ba['name'] . ' (' . ($ba['account_code'] ?? '') . ')'); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('description'); ?> (EN)</label>
                <input type="text" name="description" class="form-control" maxlength="500">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('description'); ?> (AR)</label>
                <input type="text" name="description_ar" class="form-control" maxlength="500">
            </div>
        </div>
    </div>
    <div class="rateb-card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
        <a href="<?php echo rateb_app_url('cash-vouchers'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
    </div>
</form>
