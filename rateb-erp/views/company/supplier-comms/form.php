<?php
/** @var array<string, mixed> $item */
$routePrefix = $routePrefix ?? rateb_app_route('supplier-comms');
$isEdit = !empty($item['id']);
$commId = $isEdit ? (int) $item['id'] : 0;
$commSvc = $commSvc ?? new \Rateb\App\Services\SupplierCommService();
$supplierHistory = $supplierHistory ?? [];
$archived = (int) ($item['is_archived'] ?? 0) === 1;
?>
<?php if (!empty($moduleCss)) { ?>
<link href="<?php echo Rateb\App\Core\View::escape($moduleCss); ?>" rel="stylesheet">
<?php } ?>

<div class="rateb-sc-page">
    <div class="rateb-sc-page-header">
        <div>
            <nav class="rateb-sc-breadcrumb" aria-label="breadcrumb">
                <a href="<?php echo rateb_app_url('dashboard'); ?>"><?php echo __('dashboard'); ?></a>
                <span class="mx-1">/</span>
                <a href="<?php echo rateb_app_url('supplier-comms'); ?>"><?php echo __('supplier_comms'); ?></a>
                <span class="mx-1">/</span>
                <span><?php echo __('edit'); ?></span>
            </nav>
            <h2 class="h4 mb-0"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h2>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo rateb_url($routePrefix . '/' . $commId . '/print'); ?>" class="btn btn-outline-secondary btn-sm" target="_blank"><i class="fas fa-print"></i> <?php echo __('print'); ?></a>
            <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $commId . '/archive'); ?>" class="d-inline">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-outline-warning btn-sm"><i class="fas fa-box-archive"></i> <?php echo $archived ? __('comm_unarchive') : __('comm_archive'); ?></button>
            </form>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="rateb-sc-card">
                <div class="rateb-sc-card-header">
                    <span><i class="fas fa-edit text-primary"></i> <?php echo __('edit'); ?> <?php echo __('supplier_comms'); ?></span>
                    <?php if ($archived) { ?><span class="badge bg-secondary"><?php echo __('archived'); ?></span><?php } ?>
                </div>
                <div class="rateb-sc-card-body">
                    <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $commId); ?>" enctype="multipart/form-data"
                        data-supplier-comm-form="1"
                        data-history-url="<?php echo Rateb\App\Core\View::escape($historyUrl ?? ''); ?>"
                        data-comm-id="<?php echo $commId; ?>">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <?php Rateb\App\Core\View::partial('company/supplier-comms/_form-fields', [
                            'item' => $item,
                            'fields' => $fields,
                            'lookups' => $lookups,
                            'responsibleDefault' => $responsibleDefault ?? '',
                            'showAttachments' => true,
                            'existingDocuments' => $existingDocuments ?? [],
                        ]); ?>
                        <div class="rateb-sc-form-actions">
                            <button type="submit" name="form_action" value="save" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo __('save'); ?></button>
                            <button type="submit" name="form_action" value="save_send" class="btn btn-outline-primary"><i class="fas fa-paper-plane"></i> <?php echo __('save_and_send'); ?></button>
                            <a href="<?php echo rateb_app_url('supplier-comms'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="rateb-sc-card h-100" id="sc_supplier_history"
                data-empty="<?php echo Rateb\App\Core\View::escape(__('no_records')); ?>"
                data-col-date="<?php echo Rateb\App\Core\View::escape(__('comm_date')); ?>"
                data-col-subject="<?php echo Rateb\App\Core\View::escape(__('subject')); ?>"
                data-col-status="<?php echo Rateb\App\Core\View::escape(__('comm_status')); ?>">
                <div class="rateb-sc-card-header"><?php echo __('comm_supplier_history'); ?></div>
                <div class="rateb-sc-card-body p-0">
                    <?php if ($supplierHistory === []) { ?>
                    <p class="text-muted small p-3 mb-0"><?php echo __('no_records'); ?></p>
                    <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm rateb-table mb-0">
                            <thead><tr>
                                <th><?php echo __('comm_date'); ?></th>
                                <th><?php echo __('subject'); ?></th>
                                <th><?php echo __('comm_status'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($supplierHistory as $hist) {
                                $st = (string) ($hist['comm_status'] ?? 'new'); ?>
                            <tr>
                                <td><?php echo Rateb\App\Core\View::escape((string) ($hist['comm_date'] ?? '')); ?></td>
                                <td class="rateb-cell-clip"><?php echo Rateb\App\Core\View::escape((string) ($hist['subject'] ?? '')); ?></td>
                                <td><span class="badge bg-<?php echo $commSvc->statusBadgeClass($st); ?>"><?php echo Rateb\App\Core\View::escape(__('comm_status_' . $st)); ?></span></td>
                            </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if (!empty($moduleJs)) { ?>
<script src="<?php echo Rateb\App\Core\View::escape($moduleJs); ?>"></script>
<?php } ?>
