<?php
/** @var int $companyId */
/** @var list<array<string, mixed>> $items */
/** @var array<string, int> $counts */
/** @var list<string> $deferred */
/** @var string $typeFilter */
/** @var bool $isSuperAdmin */
/** @var string $routePrefix */
/** @var string $decideUrl */
/** @var string $csrf */
$companyId = (int) ($companyId ?? 0);
$items = $items ?? [];
$counts = $counts ?? [];
$deferred = $deferred ?? [];
$typeFilter = (string) ($typeFilter ?? 'all');
$isSuperAdmin = (bool) ($isSuperAdmin ?? false);
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/approvals-inbox'));
$decideUrl = (string) ($decideUrl ?? rateb_url(rateb_app_route('hr/approvals-inbox/decide')));
$csrf = (string) ($csrf ?? \Rateb\App\Core\Csrf::token());
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'approvals-inbox']);
?>
<?php if ($companyId < 1) { ?>
<div class="alert alert-warning mb-3">
    <i class="fas fa-building me-1"></i> <?php echo __('hr_select_company_hint'); ?>
</div>
<?php } ?>

<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>
            <i class="fas fa-inbox me-1"></i>
            <?php echo __('hr_approval_inbox'); ?>
            <span class="badge bg-warning text-dark ms-2"><?php echo (int) ($counts['total'] ?? 0); ?></span>
        </span>
        <div class="d-flex flex-wrap gap-2">
            <?php if ($isSuperAdmin) { ?>
            <a href="<?php echo rateb_url('admin/oversight/approvals'); ?>?type=hr" class="btn btn-sm btn-outline-warning">
                <i class="fas fa-gavel"></i> <?php echo __('approvals_open_oversight'); ?>
            </a>
            <?php } ?>
            <a href="<?php echo rateb_url(rateb_app_route('hr')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
        </div>
    </div>
    <div class="rateb-card-body">
        <p class="text-muted small mb-3"><?php echo __('hr_approval_inbox_hint_j'); ?></p>

        <form method="get" action="<?php echo rateb_url($routePrefix); ?>" class="row g-2 mb-3">
            <div class="col-auto">
                <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php
                    $opts = [
                        'all' => __('all'),
                        'leave' => __('hr_leaves'),
                        'permission' => __('hr_permission_requests'),
                        'request' => __('hr_employee_requests'),
                        'decision' => __('hr_decisions'),
                        'payroll' => __('hr_payroll'),
                    ];
                    foreach ($opts as $val => $label) {
                        $sel = $typeFilter === $val ? ' selected' : '';
                        echo '<option value="' . $escape($val) . '"' . $sel . '>' . $escape($label) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </form>

        <div class="row g-2 mb-3">
            <div class="col-6 col-md-2"><div class="border rounded p-2 small"><?php echo __('hr_leaves'); ?>: <strong><?php echo (int) ($counts['leave'] ?? 0); ?></strong></div></div>
            <div class="col-6 col-md-2"><div class="border rounded p-2 small"><?php echo __('hr_permission_requests'); ?>: <strong><?php echo (int) ($counts['permission'] ?? 0); ?></strong></div></div>
            <div class="col-6 col-md-2"><div class="border rounded p-2 small"><?php echo __('hr_employee_requests'); ?>: <strong><?php echo (int) ($counts['request'] ?? 0); ?></strong></div></div>
            <div class="col-6 col-md-3"><div class="border rounded p-2 small"><?php echo __('hr_decisions'); ?>: <strong><?php echo (int) ($counts['decision'] ?? 0); ?></strong></div></div>
            <div class="col-6 col-md-3"><div class="border rounded p-2 small"><?php echo __('hr_payroll'); ?>: <strong><?php echo (int) ($counts['payroll'] ?? 0); ?></strong></div></div>
        </div>

        <div class="table-responsive">
            <table class="table rateb-table mb-0 align-middle">
                <thead>
                <tr>
                    <th><?php echo __('type'); ?></th>
                    <th><?php echo __('hr_employees'); ?></th>
                    <th><?php echo __('reference'); ?></th>
                    <th><?php echo __('hr_360_approval_stage'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th><?php echo __('date'); ?></th>
                    <th><?php echo __('actions'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($items === []) { ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?php echo __('hr_approval_inbox_empty'); ?></td></tr>
                <?php } else {
                    foreach ($items as $item) {
                        $title = (string) ($item['title'] ?? '');
                        $ref = (string) ($item['reference'] ?? '');
                        $url = (string) ($item['source_url'] ?? '');
                        $age = $item['age_hours'] ?? null;
                        $canAct = !empty($item['can_act']);
                        $canReject = !empty($item['can_reject']);
                        $actionable = !empty($item['actionable']);
                        $stage = (string) ($item['stage_name'] ?? '');
                        if ($stage !== '' && isset($item['stage_order'], $item['max_stage_order'])) {
                            $stage .= ' (' . (int) $item['stage_order'] . '/' . (int) $item['max_stage_order'] . ')';
                        }
                        $empLabel = trim((string) ($item['employee_name'] ?? ''));
                        if (($item['employee_code'] ?? '') !== '') {
                            $empLabel .= ($empLabel !== '' ? ' · ' : '') . (string) $item['employee_code'];
                        }
                        $summary = (string) ($item['summary'] ?? '');
                        $sourceKey = (string) ($item['source_key'] ?? '');
                        $recordId = (int) ($item['id'] ?? 0);
                        ?>
                <tr>
                    <td>
                        <div><?php echo $escape($title); ?></div>
                        <?php if ($summary !== '') { ?>
                        <div class="small text-muted"><?php echo $escape($summary); ?></div>
                        <?php } ?>
                    </td>
                    <td><?php echo $escape($empLabel !== '' ? $empLabel : '—'); ?></td>
                    <td class="rateb-ltr-num"><?php echo $escape($ref); ?></td>
                    <td class="small">
                        <?php echo $escape($stage !== '' ? $stage : ($actionable ? __('hr_inbox_single_shot') : '—')); ?>
                        <?php
                        $approverType = (string) ($item['approver_type'] ?? '');
                        if ($approverType !== '') {
                            echo '<div class="text-muted">' . $escape(__('hr_inbox_approver_type') . ': ' . $approverType) . '</div>';
                        }
                        $lastAt = (string) ($item['last_action_at'] ?? '');
                        $lastUid = (int) ($item['last_actor_user_id'] ?? 0);
                        if ($lastAt !== '' || $lastUid > 0) {
                            echo '<div class="text-muted">' . $escape(__('hr_inbox_last_decision') . ': #'
                                . ($lastUid > 0 ? (string) $lastUid : '—')
                                . ($lastAt !== '' ? ' · ' . $lastAt : '')) . '</div>';
                        }
                        $nextName = (string) ($item['next_stage_name'] ?? '');
                        $nextOutcome = (string) ($item['next_outcome'] ?? '');
                        if ($actionable && $nextOutcome === 'advance_stage' && $nextName !== '') {
                            echo '<div class="text-muted">' . $escape(__('hr_inbox_next_stage') . ': ' . $nextName) . '</div>';
                        } elseif ($actionable && $nextOutcome === 'domain_finalize') {
                            echo '<div class="text-muted">' . $escape(__('hr_inbox_next_finalize')) . '</div>';
                        }
                        ?>
                    </td>
                    <td>
                        <span class="badge bg-warning text-dark"><?php echo __('pending'); ?></span>
                        <?php if ($age !== null) { ?>
                        <span class="text-muted small ms-1"><?php echo (int) $age; ?>h</span>
                        <?php } ?>
                    </td>
                    <td class="rateb-ltr-num small"><?php echo $escape((string) ($item['created_at'] ?? '')); ?></td>
                    <td>
                        <div class="d-flex flex-wrap gap-1 align-items-center">
                            <?php if ($url !== '') { ?>
                            <a href="<?php echo $escape($url); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('view'); ?></a>
                            <?php } ?>

                            <?php if ($canAct) { ?>
                            <form method="post" action="<?php echo $escape($decideUrl); ?>" class="d-inline-flex flex-wrap gap-1 align-items-center">
                                <input type="hidden" name="_csrf" value="<?php echo $escape($csrf); ?>">
                                <input type="hidden" name="source_key" value="<?php echo $escape($sourceKey); ?>">
                                <input type="hidden" name="record_id" value="<?php echo (int) $recordId; ?>">
                                <input type="hidden" name="type_filter" value="<?php echo $escape($typeFilter); ?>">
                                <input type="text" name="comment" class="form-control form-control-sm" style="width:8rem" placeholder="<?php echo $escape(__('hr_inbox_comment_optional')); ?>" maxlength="500">
                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success"><?php echo __('approve'); ?></button>
                                <?php if ($canReject) { ?>
                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger"><?php echo __('reject'); ?></button>
                                <?php } ?>
                            </form>
                            <?php } elseif ($actionable) { ?>
                            <span class="badge bg-light text-dark border"><?php echo __('hr_inbox_awaiting_authorized_actor'); ?></span>
                            <?php } else { ?>
                            <span class="badge bg-light text-dark border"><?php echo __('hr_inbox_payroll_view_only'); ?></span>
                            <?php } ?>

                            <?php if ($isSuperAdmin) { ?>
                            <a href="<?php echo $escape((string) ($item['oversight_url'] ?? rateb_url('admin/oversight/approvals'))); ?>" class="btn btn-sm btn-outline-warning"><?php echo __('approvals_open_oversight'); ?></a>
                            <?php } ?>
                        </div>
                    </td>
                </tr>
                <?php }
                } ?>
                </tbody>
            </table>
        </div>

        <?php if ($deferred !== []) { ?>
        <div class="alert alert-light border mt-3 mb-0 small">
            <strong><?php echo __('hr_approval_inbox_deferred'); ?></strong>
            <ul class="mb-0 mt-1">
                <?php foreach ($deferred as $note) { ?>
                <li><?php echo $escape((string) $note); ?></li>
                <?php } ?>
            </ul>
        </div>
        <?php } ?>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
