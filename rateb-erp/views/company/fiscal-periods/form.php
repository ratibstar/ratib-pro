<?php
use Rateb\App\Services\FormLookupService;

$formFields = FormLookupService::fiscalPeriodFormFields();
$lookups = (new FormLookupService())->forFields($formFields);
?>
<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']); ?>
<form method="post" action="<?php echo rateb_app_url('fiscal-periods'); ?>" class="rateb-card" data-fiscal-period-form>
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <?php Rateb\App\Core\View::partial('accounting-form', [
            'formFields' => $formFields,
            'item' => $item ?? null,
            'lookups' => $lookups,
        ]); ?>
        <p class="text-muted small mb-0"><?php echo __('fiscal_year_auto_dates_hint'); ?></p>
    </div>
    <div class="rateb-card-footer d-flex gap-2">
        <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
        <a href="<?php echo rateb_app_url('fiscal-periods'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
    </div>
</form>
