<?php
$profile = $profile ?? [];
$readiness = $readiness ?? ['checks' => [], 'ready' => false];
$invoices = $invoices ?? [];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
?>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="rateb-stat-card">
            <div class="rateb-stat-label"><?php echo __('zatca_readiness'); ?></div>
            <div class="rateb-stat-value"><?php echo ($readiness['ready'] ?? false) ? __('ready') : __('not_ready'); ?></div>
        </div>
    </div>
</div>
<?php if ($canManage ?? false) { ?>
<form method="post" action="<?php echo rateb_app_url('accounting/zatca-settings'); ?>" class="rateb-card mb-4">
    <div class="rateb-card-header"><?php echo __('zatca_settings'); ?></div>
    <div class="rateb-card-body">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label"><?php echo __('vat_number'); ?></label>
                <input type="text" name="vat_number" class="form-control" maxlength="15"
                       value="<?php echo Rateb\App\Core\View::escape((string) ($profile['vat_number'] ?? '')); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('cr_number'); ?></label>
                <input type="text" name="cr_number" class="form-control" maxlength="20"
                       value="<?php echo Rateb\App\Core\View::escape((string) ($profile['cr_number'] ?? '')); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('zatca_environment'); ?></label>
                <select name="zatca_environment" class="form-select">
                    <option value="sandbox"<?php echo ($profile['zatca_environment'] ?? '') === 'sandbox' ? ' selected' : ''; ?>><?php echo __('sandbox'); ?></option>
                    <option value="production"<?php echo ($profile['zatca_environment'] ?? '') === 'production' ? ' selected' : ''; ?>><?php echo __('production'); ?></option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('legal_name_ar'); ?></label>
                <input type="text" name="legal_name_ar" class="form-control"
                       value="<?php echo Rateb\App\Core\View::escape((string) ($profile['legal_name_ar'] ?? '')); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo __('legal_name_en'); ?></label>
                <input type="text" name="legal_name_en" class="form-control"
                       value="<?php echo Rateb\App\Core\View::escape((string) ($profile['legal_name_en'] ?? '')); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo __('street'); ?></label>
                <input type="text" name="street" class="form-control" value="<?php echo Rateb\App\Core\View::escape((string) ($profile['street'] ?? '')); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('building_no'); ?></label>
                <input type="text" name="building_no" class="form-control" value="<?php echo Rateb\App\Core\View::escape((string) ($profile['building_no'] ?? '')); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('city'); ?></label>
                <input type="text" name="city" class="form-control" value="<?php echo Rateb\App\Core\View::escape((string) ($profile['city'] ?? '')); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo __('postal_code'); ?></label>
                <input type="text" name="postal_code" class="form-control" value="<?php echo Rateb\App\Core\View::escape((string) ($profile['postal_code'] ?? '')); ?>">
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="zatca_enabled" value="1" id="zatca_enabled"
                           <?php echo !empty($profile['zatca_enabled']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="zatca_enabled"><?php echo __('zatca_enabled'); ?></label>
                </div>
            </div>
        </div>
    </div>
    <div class="rateb-card-footer">
        <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
    </div>
</form>
<?php } ?>
<p class="text-muted small mb-3"><?php echo __('zatca_settings_help'); ?></p>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('zatca_invoices'); ?></div>
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0">
            <thead>
            <tr>
                <th><?php echo __('invoice_no'); ?></th>
                <th><?php echo __('issued_at'); ?></th>
                <th class="text-end"><?php echo __('total'); ?></th>
                <th><?php echo __('zatca_status'); ?></th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($invoices)) { ?>
            <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else { foreach ($invoices as $inv) { ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape($inv['invoice_no']); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($inv['issued_at']); ?></td>
                <td class="text-end"><?php echo number_format((float) ($inv['total_amount'] ?? 0), 2); ?></td>
                <td><?php echo __((string) ($inv['zatca_status'] ?? 'not_applicable')); ?></td>
                <td>
                    <?php if (($canManage ?? false) && !empty($inv['zatca_qr'])) { ?>
                    <span class="badge bg-success"><?php echo __('zatca_qr_ready'); ?></span>
                    <?php } elseif ($canManage ?? false) { ?>
                    <form method="post" action="<?php echo rateb_app_url('accounting/zatca-qr/' . (int) $inv['id']); ?>" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-primary"><?php echo __('generate_qr'); ?></button>
                    </form>
                    <?php } ?>
                </td>
            </tr>
            <?php } } ?>
            </tbody>
        </table>
    </div>
</div>
