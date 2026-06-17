<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $fields */
/** @var string $routePrefix */
/** @var string $csrf */
/** @var array<string, list<array{value: string|int, label: string}>> $lookups */
$isEdit = !empty($item);
$action = $isEdit ? rateb_url($routePrefix . '/' . (int) $item['id']) : rateb_url($routePrefix);
$lookups = $lookups ?? (new \Rateb\App\Services\FormLookupService())->forFields($fields);
$metrics = $evaluationMetrics ?? ['overall' => 0, 'percent' => 0, 'tier' => 'weak'];
$tierLabels = $tierLabels ?? [];
$svc = new \Rateb\App\Services\SupplierEvaluationService();
$evalId = $isEdit ? (int) ($item['id'] ?? 0) : 0;
$approval = $isEdit ? (string) ($item['manager_approval'] ?? 'pending') : 'pending';
?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
            <div class="rateb-card-body">
                <form method="post" action="<?php echo $action; ?>" enctype="multipart/form-data"
                    data-supplier-evaluation-form="1"
                    data-history-url="<?php echo Rateb\App\Core\View::escape($historyUrl ?? ''); ?>"
                    data-evaluation-id="<?php echo $evalId; ?>"
                    data-tier-labels="<?php echo Rateb\App\Core\View::escape(json_encode($tierLabels, JSON_UNESCAPED_UNICODE)); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                    <input type="hidden" name="rating_tier" id="eval_tier_input" value="<?php echo Rateb\App\Core\View::escape((string) ($metrics['tier'] ?? 'weak')); ?>">
                    <div class="row g-3">
                        <?php foreach ($fields as $field) {
                            $name = $field['name'];
                            $value = $item[$name] ?? ($field['default'] ?? '');
                            Rateb\App\Core\View::partial('form-field', [
                                'field' => $field,
                                'value' => $value,
                                'lookups' => $lookups,
                            ]);
                        } ?>
                        <div class="col-md-4">
                            <label class="form-label rateb-form-label"><?php echo __('evaluator_name'); ?></label>
                            <input class="form-control rateb-form-control" type="text" readonly
                                value="<?php echo Rateb\App\Core\View::escape((string) ($evaluatorName ?? '')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label rateb-form-label"><?php echo __('overall_score'); ?></label>
                            <div class="form-control rateb-form-control rateb-readonly-display" id="eval_overall_display"><?php echo number_format((float) ($metrics['overall'] ?? 0), 2); ?></div>
                            <small class="text-muted"><?php echo __('overall_score_auto'); ?></small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label rateb-form-label"><?php echo __('evaluation_percent'); ?></label>
                            <div class="form-control rateb-form-control rateb-readonly-display" id="eval_percent_display"><?php echo number_format((float) ($metrics['percent'] ?? 0), 1); ?>%</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label rateb-form-label"><?php echo __('supplier_rating_tier'); ?></label>
                            <div id="eval_tier_display">
                                <span class="badge bg-<?php echo Rateb\App\Core\View::escape($svc->tierBadgeClass((string) ($metrics['tier'] ?? 'weak'))); ?>">
                                    <?php echo Rateb\App\Core\View::escape($svc->tierLabel((string) ($metrics['tier'] ?? 'weak'))); ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($isEdit) { ?>
                        <div class="col-md-6">
                            <label class="form-label rateb-form-label"><?php echo __('manager_approval'); ?></label>
                            <div>
                                <span class="badge bg-<?php echo $approval === 'approved' ? 'success' : ($approval === 'rejected' ? 'danger' : 'warning'); ?>">
                                    <?php echo __('manager_approval_' . $approval); ?>
                                </span>
                                <?php if (!empty($item['approved_at'])) { ?>
                                <small class="text-muted d-block mt-1"><?php echo Rateb\App\Core\View::escape((string) $item['approved_at']); ?></small>
                                <?php } ?>
                            </div>
                        </div>
                        <?php } ?>
                        <div class="col-12">
                            <label class="form-label rateb-form-label" for="f_evaluation_attachments">
                                <i class="fas fa-paperclip"></i> <?php echo __('evaluation_attachments'); ?>
                            </label>
                            <input class="form-control rateb-form-control" type="file" id="f_evaluation_attachments"
                                name="evaluation_attachments[]" multiple
                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                            <small class="text-muted d-block mt-1"><?php echo __('evaluation_attachments_hint'); ?></small>
                            <?php if (!empty($existingDocuments)) { ?>
                            <ul class="list-unstyled small mt-2 mb-0">
                                <?php foreach ($existingDocuments as $doc) {
                                    $docId = (int) ($doc['id'] ?? 0); ?>
                                <li class="py-1">
                                    <a href="<?php echo rateb_url('documents/view/' . $docId); ?>" target="_blank" rel="noopener"><?php echo Rateb\App\Core\View::escape((string) ($doc['file_name'] ?? '')); ?></a>
                                </li>
                                <?php } ?>
                            </ul>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="mt-4 d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                        <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
                        <?php if ($isEdit) { ?>
                        <a href="<?php echo rateb_url($routePrefix . '/' . $evalId . '/documents'); ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-folder-open"></i> <?php echo __('view_files'); ?>
                        </a>
                        <?php } ?>
                    </div>
                </form>
                <?php if ($isEdit && $approval === 'pending') { ?>
                <div class="mt-3 border-top pt-3">
                    <p class="text-muted small mb-2"><?php echo __('evaluation_pending_go_approvals'); ?></p>
                    <a href="<?php echo rateb_url($approvalsRoute ?? rateb_app_url('supplier-evaluations/approvals')); ?>" class="btn btn-outline-warning btn-sm">
                        <i class="fas fa-clipboard-check"></i> <?php echo __('evaluation_approvals'); ?>
                    </a>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('supplier_evaluation_history'); ?></div>
            <div class="rateb-card-body" id="eval_supplier_history"
                data-empty="<?php echo Rateb\App\Core\View::escape(__('no_records')); ?>"
                data-col-date="<?php echo Rateb\App\Core\View::escape(__('evaluation_date')); ?>"
                data-col-overall="<?php echo Rateb\App\Core\View::escape(__('overall_score')); ?>"
                data-col-tier="<?php echo Rateb\App\Core\View::escape(__('supplier_rating_tier')); ?>"
                data-col-approval="<?php echo Rateb\App\Core\View::escape(__('manager_approval')); ?>">
                <?php if (empty($supplierHistory)) { ?>
                <p class="text-muted small mb-0"><?php echo __('no_records'); ?></p>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-sm rateb-table mb-0">
                        <thead>
                        <tr>
                            <th><?php echo __('evaluation_date'); ?></th>
                            <th><?php echo __('overall_score'); ?></th>
                            <th><?php echo __('supplier_rating_tier'); ?></th>
                            <th><?php echo __('manager_approval'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($supplierHistory as $hist) {
                            $tier = (string) ($hist['rating_tier'] ?? 'weak');
                            $approval = (string) ($hist['manager_approval'] ?? 'pending'); ?>
                        <tr>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($hist['evaluation_date'] ?? '')); ?></td>
                            <td><?php echo Rateb\App\Core\View::escape((string) ($hist['overall_score'] ?? '')); ?></td>
                            <td><span class="badge bg-<?php echo Rateb\App\Core\View::escape($svc->tierBadgeClass($tier)); ?>"><?php echo Rateb\App\Core\View::escape($svc->tierLabel($tier)); ?></span></td>
                            <td><span class="badge bg-secondary"><?php echo Rateb\App\Core\View::escape(__('manager_approval_' . $approval)); ?></span></td>
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
<?php if (!empty($evaluationFormJs)) { ?>
<script src="<?php echo Rateb\App\Core\View::escape($evaluationFormJs); ?>"></script>
<?php } ?>
