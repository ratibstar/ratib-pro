<?php
/** @var int $companyId */
/** @var array<string,mixed>|null $team */
/** @var list<array<string,mixed>> $approvals */
/** @var list<array<string,mixed>> $pendingLeave */
/** @var array<string,mixed> $saudiFoundation */
/** @var string|null $error */
/** @var string $routePrefix */
/** @var string $csrf */
$companyId = (int) ($companyId ?? 0);
$team = $team ?? null;
$approvals = $approvals ?? [];
$pendingLeave = $pendingLeave ?? [];
$saudiFoundation = $saudiFoundation ?? [];
$error = $error ?? null;
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/manager'));
$csrf = (string) ($csrf ?? '');
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'manager-team']);
$members = is_array($team['members'] ?? null) ? $team['members'] : [];
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/hr-module.css'); ?>">

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?php echo __('hr_manager_my_team'); ?></h1>
        <p class="text-muted small mb-0"><?php echo __('hr_manager_my_team_hint'); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo rateb_url(rateb_app_route('hr/ess')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('hr_ess_portal'); ?></a>
        <a href="<?php echo rateb_url(rateb_app_route('hr/approvals-inbox')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('hr_pending_actions'); ?></a>
    </div>
</div>

<?php if ($error !== null) { ?>
<div class="alert alert-warning"><?php echo $escape($error); ?></div>
<?php return; } ?>

<div class="alert alert-light border small mb-3">
    <?php echo __('hr_manager_reporting_note'); ?>
    · <?php echo __('hr_employees'); ?>: <strong class="rateb-ltr-num"><?php echo (int) ($team['team_count'] ?? 0); ?></strong>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-7">
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('hr_manager_my_team'); ?></div>
            <div class="rateb-card-body p-0">
                <div class="table-responsive">
                    <table class="table rateb-table table-sm mb-0">
                        <thead><tr>
                            <th><?php echo __('employee'); ?></th>
                            <th><?php echo __('job_title'); ?></th>
                            <th><?php echo __('department'); ?></th>
                            <th><?php echo __('status'); ?></th>
                        </tr></thead>
                        <tbody>
                        <?php if ($members === []) { echo '<tr><td colspan="4" class="text-muted">' . $escape(__('hr_manager_no_team')) . '</td></tr>'; }
                        foreach ($members as $m) { ?>
                            <tr>
                                <td>
                                    <a href="<?php echo $escape((string) ($m['360_url'] ?? '#')); ?>">
                                        <?php echo $escape((string) ($m['name'] ?? '')); ?>
                                    </a>
                                    <div class="small text-muted rateb-ltr-num"><?php echo $escape((string) ($m['employee_code'] ?? '')); ?></div>
                                </td>
                                <td><?php echo $escape((string) ($m['job_title'] ?? '')); ?></td>
                                <td><?php echo $escape((string) ($m['department_name'] ?? '')); ?></td>
                                <td><?php echo $escape((string) ($m['status'] ?? '')); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="rateb-card mb-3">
            <div class="rateb-card-header"><?php echo __('hr_manager_team_approvals'); ?></div>
            <div class="rateb-card-body">
                <?php if ($approvals === []) { echo '<p class="text-muted small mb-0">' . $escape(__('no_records')) . '</p>'; }
                foreach (array_slice($approvals, 0, 8) as $item) { ?>
                    <div class="border rounded p-2 mb-2">
                        <div class="small fw-semibold"><?php echo $escape((string) ($item['employee_name'] ?? '')); ?> · <?php echo $escape((string) ($item['type'] ?? '')); ?></div>
                        <div class="small text-muted"><?php echo $escape((string) ($item['summary'] ?? '')); ?></div>
                        <?php if (!empty($item['can_act'])) { ?>
                        <form method="post" action="<?php echo rateb_url($routePrefix . '/decide'); ?>" class="d-flex gap-1 mt-2">
                            <input type="hidden" name="_csrf" value="<?php echo $escape($csrf); ?>">
                            <input type="hidden" name="source_key" value="<?php echo $escape((string) ($item['source_key'] ?? '')); ?>">
                            <input type="hidden" name="record_id" value="<?php echo (int) ($item['id'] ?? 0); ?>">
                            <button name="action" value="approve" class="btn btn-sm btn-success"><?php echo __('approve'); ?></button>
                            <button name="action" value="reject" class="btn btn-sm btn-outline-danger"><?php echo __('reject'); ?></button>
                        </form>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="rateb-card mb-3">
            <div class="rateb-card-header"><?php echo __('hr_manager_team_leave'); ?></div>
            <div class="rateb-card-body p-0">
                <div class="table-responsive">
                    <table class="table rateb-table table-sm mb-0">
                        <thead><tr><th><?php echo __('employee'); ?></th><th><?php echo __('from'); ?></th><th><?php echo __('status'); ?></th></tr></thead>
                        <tbody>
                        <?php if ($pendingLeave === []) { echo '<tr><td colspan="3" class="text-muted">' . $escape(__('no_records')) . '</td></tr>'; }
                        foreach (array_slice($pendingLeave, 0, 8) as $row) { ?>
                            <tr>
                                <td><?php echo $escape((string) ($row['employee_name'] ?? '')); ?></td>
                                <td class="rateb-ltr-num"><?php echo $escape((string) ($row['start_date'] ?? '')); ?></td>
                                <td><?php echo $escape((string) ($row['status'] ?? '')); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="rateb-card">
            <div class="rateb-card-header"><?php echo __('hr_saudi_foundation'); ?></div>
            <div class="rateb-card-body small">
                <div><?php echo __('status'); ?>: <strong><?php echo !empty($saudiFoundation['schema_ready']) ? __('hr_saudi_foundation_ready') : __('hr_saudi_foundation_pending_migration'); ?></strong></div>
                <div class="text-muted mt-1"><?php echo $escape((string) ($saudiFoundation['policy'] ?? '')); ?></div>
                <div class="text-muted">GOSI/WPS: <?php echo __('hr_saudi_no_external_send'); ?></div>
            </div>
        </div>
    </div>
</div>
