<?php
use Rateb\App\Services\FormLookupService;

$formFields = FormLookupService::inventoryAuditFormFields();
$lookups = (new FormLookupService())->forFields($formFields);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_app_url('inventory-audits'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('audit_no'); ?></label>
                    <input class="form-control" type="text" name="audit_no" value="<?php echo Rateb\App\Core\View::escape($auditNo ?? ''); ?>" readonly>
                </div>
                <?php foreach ($formFields as $field) {
                    Rateb\App\Core\View::partial('form-field', [
                        'field' => $field,
                        'value' => '',
                        'lookups' => $lookups,
                    ]);
                } ?>
            </div>
            <h6 class="mb-3"><?php echo __('cycle_count_lines'); ?></h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th><?php echo __('item_name'); ?></th><th><?php echo __('counted_qty'); ?></th></tr></thead>
                    <tbody>
                        <?php foreach ($inventory ?? [] as $i => $inv) { ?>
                        <tr>
                            <td>
                                <?php echo Rateb\App\Core\View::escape($inv['item_name'] ?? ''); ?>
                                <input type="hidden" name="inventory_id[<?php echo $i; ?>]" value="<?php echo (int) $inv['id']; ?>">
                            </td>
                            <td><input class="form-control form-control-sm" type="number" step="0.001" name="counted_qty[<?php echo $i; ?>]" value="<?php echo Rateb\App\Core\View::escape((string) ($inv['quantity'] ?? 0)); ?>"></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_app_url('inventory-audits'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
