<?php
/** @var array<string, mixed> $workflow */
/** @var array<int, array<string, mixed>> $steps */
/** @var array<int, array<string, mixed>> $companies */
/** @var array<string, string> $entityTypes */
/** @var array<int, array{id:int,name:string}> $roles */
$workflow = $workflow ?? [];
$canManage = !empty($canManage);
$workflowId = (int) ($workflow['id'] ?? 0);
$isActive = (int) ($workflow['is_active'] ?? 0) === 1;
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo __('edit'); ?> <?php echo __('workflows'); ?></span>
        <a href="<?php echo rateb_url('admin/oversight/workflows'); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('back'); ?></a>
    </div>
    <div class="rateb-card-body">
        <?php if ($canManage) { ?>
        <form method="post" action="<?php echo rateb_url('admin/oversight/workflows/' . $workflowId); ?>" class="row g-3 mb-4">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-4">
                <label class="form-label rateb-form-label" for="wf_edit_name"><?php echo __('name'); ?></label>
                <input class="form-control rateb-form-control" id="wf_edit_name" name="name" required
                       value="<?php echo Rateb\App\Core\View::escape((string) ($workflow['name'] ?? '')); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label rateb-form-label" for="wf_edit_entity"><?php echo __('entity_type'); ?></label>
                <select class="form-select rateb-form-control" id="wf_edit_entity" name="entity_type">
                    <?php foreach ($entityTypes as $value => $labelKey) { ?>
                    <option value="<?php echo Rateb\App\Core\View::escape($value); ?>"<?php echo (string) ($workflow['entity_type'] ?? '') === $value ? ' selected' : ''; ?>>
                        <?php echo __($labelKey); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label rateb-form-label" for="wf_edit_company"><?php echo __('companies'); ?></label>
                <select class="form-select rateb-form-control" id="wf_edit_company" name="company_id">
                    <option value=""><?php echo __('all_companies_global'); ?></option>
                    <?php foreach ($companies as $c) { ?>
                    <option value="<?php echo (int) ($c['id'] ?? 0); ?>"<?php echo (int) ($workflow['company_id'] ?? 0) === (int) ($c['id'] ?? 0) ? ' selected' : ''; ?>>
                        <?php echo Rateb\App\Core\View::escape((string) ($c['name'] ?? '')); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label rateb-form-label" for="wf_edit_active"><?php echo __('active'); ?></label>
                <select class="form-select rateb-form-control" id="wf_edit_active" name="is_active">
                    <option value="1"<?php echo $isActive ? ' selected' : ''; ?>><?php echo __('active'); ?></option>
                    <option value="0"<?php echo !$isActive ? ' selected' : ''; ?>><?php echo __('inactive'); ?></option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
            </div>
        </form>
        <?php } else { ?>
        <dl class="row mb-4">
            <dt class="col-sm-3"><?php echo __('name'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape((string) ($workflow['name'] ?? '')); ?></dd>
            <dt class="col-sm-3"><?php echo __('entity_type'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape(\Rateb\App\Services\WorkflowService::entityTypeLabel((string) ($workflow['entity_type'] ?? ''))); ?></dd>
        </dl>
        <?php } ?>

        <h6 class="mb-3"><?php echo __('workflow_steps'); ?></h6>
        <div class="table-responsive rateb-oversight-table-wrap mb-3">
            <table class="table rateb-table rateb-oversight-table mb-0">
                <thead>
                <tr>
                    <th>#</th>
                    <th><?php echo __('name'); ?></th>
                    <th><?php echo __('role'); ?></th>
                    <?php if ($canManage) { ?><th><?php echo __('actions'); ?></th><?php } ?>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($steps)) { ?>
                <tr><td colspan="<?php echo $canManage ? 4 : 3; ?>" class="text-center text-muted py-3"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($steps as $step) { ?>
                <tr>
                    <td class="rateb-ltr-num"><?php echo (int) ($step['step_order'] ?? 0); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($step['label'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($step['role_name'] ?? '—')); ?></td>
                    <?php if ($canManage) { ?>
                    <td>
                        <form method="post" action="<?php echo rateb_url('admin/oversight/workflows/' . $workflowId . '/steps/' . (int) ($step['id'] ?? 0) . '/delete'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_delete')); ?>">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                    <?php } ?>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>

        <?php if ($canManage) { ?>
        <form method="post" action="<?php echo rateb_url('admin/oversight/workflows/' . $workflowId . '/steps'); ?>" class="row g-3">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="col-md-5">
                <label class="form-label rateb-form-label" for="step_label"><?php echo __('step_label'); ?></label>
                <input class="form-control rateb-form-control" id="step_label" name="label" required placeholder="<?php echo __('step_label_placeholder'); ?>">
            </div>
            <div class="col-md-5">
                <label class="form-label rateb-form-label" for="step_role"><?php echo __('role'); ?></label>
                <select class="form-select rateb-form-control" id="step_role" name="role_id">
                    <option value=""><?php echo __('optional'); ?></option>
                    <?php foreach ($roles as $role) { ?>
                    <option value="<?php echo (int) ($role['id'] ?? 0); ?>"><?php echo Rateb\App\Core\View::escape((string) ($role['name'] ?? '')); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary w-100"><?php echo __('add'); ?></button>
            </div>
        </form>
        <?php } ?>
    </div>
</div>
