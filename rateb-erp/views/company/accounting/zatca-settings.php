<?php
use Rateb\App\Services\FormLookupService;

$profile = $profile ?? [];
$readiness = $readiness ?? ['checks' => [], 'ready' => false];
$invoices = $invoices ?? [];
$formFields = FormLookupService::zatcaSettingsFormFields();
$lookups = (new FormLookupService())->forFields($formFields);
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
?>
<?php if ($canManage ?? false) { ?>
<form method="post" action="<?php echo rateb_app_url('accounting/zatca-settings'); ?>" class="rateb-card mb-4">
    <div class="rateb-card-header"><?php echo __('zatca_settings'); ?></div>
    <div class="rateb-card-body">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <?php Rateb\App\Core\View::partial('accounting-form', [
            'formFields' => $formFields,
            'item' => $profile,
            'lookups' => $lookups,
        ]); ?>
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
                <td><?php echo Rateb\App\Core\View::formatDate($inv['issued_at']); ?></td>
                <td class="text-end"><?php echo number_format((float) ($inv['total_amount'] ?? 0), 2); ?></td>
                <td><?php
                    $st = preg_replace('/[^a-z_]/', '', (string) ($inv['zatca_status'] ?? 'not_applicable'));
                    echo __('zatca_invoice_' . ($st !== '' ? $st : 'not_applicable'));
                ?></td>
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
