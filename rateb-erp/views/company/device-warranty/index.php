<?php
use Rateb\App\Services\FormLookupService;

Rateb\App\Core\View::partial('workflow-index', [
    'title' => $title ?? __('device_warranty'),
    'entitySlug' => 'device-warranty',
    'routePrefix' => rateb_app_route('device-warranty'),
    'formFields' => null,
    'items' => $items ?? [],
    'columns' => [
        ['name' => 'device_name', 'label' => 'medical_devices'],
        ['name' => 'serial_no', 'label' => 'serial_no'],
        ['name' => 'warranty_expiry', 'label' => 'warranty_expiry'],
        ['name' => 'maintenance_due', 'label' => 'maintenance_due'],
        ['name' => 'status', 'label' => 'status', 'type' => 'status'],
    ],
    'exportRoute' => $exportRoute ?? rateb_app_url('device-warranty/export'),
    'exportEnabled' => $exportEnabled ?? true,
    'csrf' => $csrf,
    'canManage' => $canManage ?? null,
    'approvalEnabled' => false,
]);
?>
<?php if (!empty($due)) { ?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header text-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo __('warranty_expiring_soon'); ?></div>
    <div class="rateb-card-body">
        <?php Rateb\App\Core\View::partial('workflow-list', [
            'items' => $due,
            'columns' => [
                ['name' => 'device_name', 'label' => 'medical_devices'],
                ['name' => 'serial_no', 'label' => 'serial_no'],
                ['name' => 'warranty_expiry', 'label' => 'warranty_expiry'],
            ],
            'viewActionsEnabled' => true,
            'routePrefix' => rateb_app_route('device-warranty'),
            'csrf' => $csrf ?? '',
        ]); ?>
    </div>
</div>
<?php } ?>
<?php if ($canManage ?? rateb_can_manage_entity('device-warranty')) { ?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('update_warranty'); ?></div>
    <div class="rateb-card-body">
        <?php foreach ($devices ?? [] as $d) { ?>
        <form method="post" action="<?php echo rateb_app_url('device-warranty/' . (int) $d['id']); ?>" class="row g-2 align-items-end mb-2">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-4"><strong><?php echo Rateb\App\Core\View::escape($d['device_name'] ?? ''); ?></strong></div>
            <div class="col-md-3">
                <input class="form-control form-control-sm" type="date" name="warranty_expiry" value="<?php echo Rateb\App\Core\View::escape($d['warranty_expiry'] ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <a href="<?php echo rateb_app_url('device-warranty/' . (int) $d['id']); ?>" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i></a>
            </div>
            <div class="col-md-3"><button type="submit" class="btn btn-sm btn-primary"><?php echo __('save'); ?></button></div>
        </form>
        <?php } ?>
    </div>
</div>
<?php } ?>
