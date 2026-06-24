<?php
/** @var array<int, array<string, mixed>> $workflows */
/** @var array<int, array<string, mixed>> $pending */
/** @var array<int, array<string, mixed>> $companies */
/** @var array{company_id:int,status:string,date_from:string,date_to:string} $filters */
/** @var array<string, string> $entityTypes */
$canManage = !empty($canManage);
Rateb\App\Core\View::partial('admin-company-portal-banner');
?>
<div class="row g-3">
    <?php Rateb\App\Core\View::partial('admin-oversight-filters', [
        'companies' => $companies ?? [],
        'filters' => $filters ?? [],
        'statusOptions' => [],
        'formAction' => $formAction ?? rateb_url('admin/oversight/workflows'),
        'hideStatus' => true,
        'hideDates' => true,
    ]); ?>

    <?php if ($canManage) { ?>
    <div class="col-12">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('create'); ?> <?php echo __('workflows'); ?></div>
            <div class="rateb-card-body">
                <form method="post" action="<?php echo rateb_url('admin/oversight/workflows'); ?>" class="row g-3">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <div class="col-md-4">
                        <label class="form-label rateb-form-label" for="wf_name"><?php echo __('name'); ?></label>
                        <input class="form-control rateb-form-control" id="wf_name" name="name" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label rateb-form-label" for="wf_entity_type"><?php echo __('entity_type'); ?></label>
                        <select class="form-select rateb-form-control" id="wf_entity_type" name="entity_type">
                            <?php foreach ($entityTypes as $value => $labelKey) { ?>
                            <option value="<?php echo Rateb\App\Core\View::escape($value); ?>"><?php echo __($labelKey); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label rateb-form-label" for="wf_company_id"><?php echo __('companies'); ?></label>
                        <select class="form-select rateb-form-control" id="wf_company_id" name="company_id">
                            <option value=""><?php echo __('all_companies_global'); ?></option>
                            <?php foreach ($companies as $c) { ?>
                            <option value="<?php echo (int) ($c['id'] ?? 0); ?>"><?php echo Rateb\App\Core\View::escape((string) ($c['name'] ?? '')); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><?php echo __('save'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php } ?>

    <div class="col-12">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('workflows'); ?></div>
            <div class="rateb-card-body p-0">
                <div class="table-responsive rateb-oversight-table-wrap">
                    <table class="table rateb-table rateb-oversight-table mb-0">
                        <thead>
                        <tr>
                            <th><?php echo __('name'); ?></th>
                            <th><?php echo __('companies'); ?></th>
                            <th><?php echo __('entity_type'); ?></th>
                            <th><?php echo __('step_count'); ?></th>
                            <th><?php echo __('active'); ?></th>
                            <?php if ($canManage) { ?><th><?php echo __('actions'); ?></th><?php } ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($workflows)) { ?>
                        <tr><td colspan="<?php echo $canManage ? 6 : 5; ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                        <?php } else { foreach ($workflows as $row) {
                            $active = (int) ($row['is_active'] ?? 0) === 1;
                            ?>
                        <tr>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></td>
                            <td><?php echo Rateb\App\Core\View::escape((string) (($row['company_name'] ?? '') !== '' ? $row['company_name'] : __('all_companies_global'))); ?></td>
                            <td><?php echo Rateb\App\Core\View::escape(\Rateb\App\Services\WorkflowService::entityTypeLabel((string) ($row['entity_type'] ?? ''))); ?></td>
                            <td class="rateb-ltr-num"><?php echo (int) ($row['step_count'] ?? 0); ?></td>
                            <td><span class="badge bg-<?php echo $active ? 'success' : 'secondary'; ?>"><?php echo $active ? __('active') : __('inactive'); ?></span></td>
                            <?php if ($canManage) { ?>
                            <td class="rateb-actions-cell text-nowrap">
                                <div class="rateb-actions">
                                    <a href="<?php echo rateb_url('admin/oversight/workflows/' . (int) ($row['id'] ?? 0) . '/edit'); ?>" class="btn btn-sm btn-outline-primary" title="<?php echo __('edit'); ?>"><i class="fas fa-edit"></i></a>
                                    <form method="post" action="<?php echo rateb_url('admin/oversight/workflows/' . (int) ($row['id'] ?? 0) . '/toggle'); ?>" class="d-inline">
                                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" title="<?php echo __('toggle'); ?>"><i class="fas fa-power-off"></i></button>
                                    </form>
                                    <form method="post" action="<?php echo rateb_url('admin/oversight/workflows/' . (int) ($row['id'] ?? 0) . '/delete'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_delete')); ?>">
                                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo __('delete'); ?>"><i class="fas fa-trash"></i></button>
                                    </form>
                                </div>
                            </td>
                            <?php } ?>
                        </tr>
                        <?php } } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="rateb-card">
            <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><?php echo __('pending_approvals'); ?></span>
                <a href="<?php echo rateb_url(rateb_app_route('workflows')); ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-check-double"></i> <?php echo __('open_operations'); ?>
                </a>
            </div>
            <div class="rateb-card-body p-0">
                <div class="table-responsive rateb-oversight-table-wrap">
                    <table class="table rateb-table rateb-oversight-table mb-0">
                        <thead>
                        <tr>
                            <th><?php echo __('companies'); ?></th>
                            <th><?php echo __('workflows'); ?></th>
                            <th><?php echo __('entity_type'); ?></th>
                            <th><?php echo __('created_at'); ?></th>
                            <th><?php echo __('status'); ?></th>
                            <th><?php echo __('actions'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($pending)) { ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                        <?php } else { foreach ($pending as $row) {
                            $entityType = (string) ($row['entity_type'] ?? '');
                            $entityId = (int) ($row['entity_id'] ?? 0);
                            $companyId = (int) ($row['company_id'] ?? 0);
                            $docUrl = \Rateb\App\Services\WorkflowService::entityDocumentUrl($entityType, $entityId, $companyId);
                            $status = (string) ($row['status'] ?? 'pending');
                            ?>
                        <tr>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($row['company_name'] ?? '')); ?></td>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($row['workflow_name'] ?? '')); ?></td>
                            <td>
                                <?php echo Rateb\App\Core\View::escape(\Rateb\App\Services\WorkflowService::entityTypeLabel($entityType)); ?>
                                <span class="text-muted rateb-ltr-num">#<?php echo $entityId; ?></span>
                            </td>
                            <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($row['created_at'] ?? '')); ?></td>
                            <td><span class="badge bg-warning text-dark"><?php echo __('workflow_status_' . $status); ?></span></td>
                            <td>
                                <?php if ($docUrl !== '') { ?>
                                <a href="<?php echo Rateb\App\Core\View::escape($docUrl); ?>" class="btn btn-sm btn-outline-info" target="_blank" rel="noopener">
                                    <i class="fas fa-eye"></i> <?php echo __('view'); ?>
                                </a>
                                <?php } else { ?>
                                <span class="text-muted">—</span>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
