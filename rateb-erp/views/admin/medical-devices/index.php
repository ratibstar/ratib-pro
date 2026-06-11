<?php if (!empty($companies)) { ?>
<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <select class="form-select" name="company_id" onchange="this.form.submit()">
            <option value=""><?php echo __('all_companies'); ?></option>
            <?php foreach ($companies as $c) { ?>
            <option value="<?php echo (int) $c['id']; ?>"<?php echo (int)($_GET['company_id'] ?? 0) === (int)$c['id'] ? ' selected' : ''; ?>><?php echo Rateb\App\Core\View::escape($c['name'] ?? ''); ?></option>
            <?php } ?>
        </select>
    </div>
</form>
<?php } ?>
<?php Rateb\App\Core\View::partial('crud-index', [
    'title' => $title ?? __('medical_devices'),
    'items' => $items ?? [],
    'fields' => [
        ['name' => 'company_name', 'label' => 'company_name'],
        ['name' => 'device_name', 'label' => 'medical_devices'],
        ['name' => 'serial_no', 'label' => 'serial_no'],
        ['name' => 'calibration_due', 'label' => 'calibration_due'],
        ['name' => 'status', 'label' => 'status'],
    ],
    'csrf' => $csrf,
    'routePrefix' => 'admin/medical-devices',
    'bulkEnabled' => false,
    'createEnabled' => false,
    'actionsEnabled' => false,
]); ?>
