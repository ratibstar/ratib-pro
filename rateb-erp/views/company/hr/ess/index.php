<?php
/** @var int $companyId */
/** @var array<string,mixed>|null $payload */
/** @var string|null $error */
/** @var string $routePrefix */
/** @var list<string> $certificateTypes */
/** @var string $csrf */
$companyId = (int) ($companyId ?? 0);
$payload = $payload ?? null;
$error = $error ?? null;
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/ess'));
$certificateTypes = $certificateTypes ?? [];
$csrf = (string) ($csrf ?? '');
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'ess-portal']);
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/hr-module.css'); ?>">

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?php echo __('hr_ess_portal'); ?></h1>
        <p class="text-muted small mb-0"><?php echo __('hr_ess_portal_hint'); ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?php echo rateb_url(rateb_app_route('hr/manager')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('hr_manager_my_team'); ?></a>
        <a href="<?php echo rateb_url(rateb_app_route('hr')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('hr_command_center'); ?></a>
    </div>
</div>

<?php if ($error !== null) { ?>
<div class="alert alert-warning"><?php echo $escape($error); ?></div>
<?php return; } ?>

<?php
$profile = is_array($payload['profile'] ?? null) ? $payload['profile'] : [];
$balances = is_array($payload['leave_balances'] ?? null) ? $payload['leave_balances'] : [];
$leaves = is_array($payload['leave_history'] ?? null) ? $payload['leave_history'] : [];
$requests = is_array($payload['requests'] ?? null) ? $payload['requests'] : [];
$letters = is_array($payload['letters'] ?? null) ? $payload['letters'] : [];
$decisions = is_array($payload['decisions'] ?? null) ? $payload['decisions'] : [];
$docs = is_array($payload['documents'] ?? null) ? $payload['documents'] : [];
$payslips = is_array($payload['payslips'] ?? null) ? $payload['payslips'] : [];
$notifications = is_array($payload['notifications'] ?? null) ? $payload['notifications'] : [];
?>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('hr_ess_my_360'); ?></div>
            <div class="rateb-card-body">
                <div class="fw-semibold"><?php echo $escape((string) ($profile['name'] ?? $payload['employee']['name'] ?? '')); ?></div>
                <div class="small text-muted rateb-ltr-num"><?php echo $escape((string) ($profile['employee_code'] ?? '')); ?></div>
                <div class="small mt-2"><?php echo $escape((string) ($profile['department_name'] ?? '')); ?> · <?php echo $escape((string) ($profile['job_title'] ?? '')); ?></div>
                <div class="small"><?php echo __('status'); ?>: <?php echo $escape((string) ($profile['status'] ?? '')); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('hr_leave_balances'); ?></div>
            <div class="rateb-card-body">
                <?php if ($balances === []) { echo '<p class="text-muted small mb-0">' . $escape(__('no_records')) . '</p>'; }
                foreach (array_slice($balances, 0, 5) as $b) { ?>
                    <div class="d-flex justify-content-between small border-bottom py-1">
                        <span><?php echo $escape((string) ($b['leave_type_name'] ?? $b['leave_type_code'] ?? '')); ?></span>
                        <span class="rateb-ltr-num"><?php echo $escape((string) ($b['remaining'] ?? $b['balance'] ?? '')); ?></span>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="rateb-card h-100">
            <div class="rateb-card-header"><?php echo __('notifications'); ?></div>
            <div class="rateb-card-body">
                <div class="mb-2"><?php echo __('notifications'); ?>: <strong class="rateb-ltr-num"><?php echo (int) ($notifications['unread'] ?? 0); ?></strong></div>
                <?php foreach (array_slice(is_array($notifications['recent'] ?? null) ? $notifications['recent'] : [], 0, 4) as $n) { ?>
                    <div class="small text-muted mb-1"><?php echo $escape((string) ($n['title'] ?? $n['message'] ?? '')); ?></div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo __('hr_ess_request_certificate'); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_url($routePrefix . '/certificates'); ?>" class="row g-2 align-items-end">
            <input type="hidden" name="_csrf" value="<?php echo $escape($csrf); ?>">
            <div class="col-md-4">
                <label class="form-label"><?php echo __('certificate'); ?></label>
                <select name="request_type" class="form-select form-select-sm" required>
                    <?php foreach ($certificateTypes as $t) {
                        echo '<option value="' . $escape($t) . '">' . $escape(__($t)) . '</option>';
                    } ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label"><?php echo __('notes'); ?></label>
                <input type="text" name="notes" class="form-control form-control-sm" maxlength="500">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-sm btn-primary w-100"><?php echo __('save'); ?></button>
            </div>
        </form>
        <p class="small text-muted mt-2 mb-0"><?php echo __('hr_ess_certificate_hint'); ?></p>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="rateb-card mb-3">
            <div class="rateb-card-header"><?php echo __('hr_leave_requests'); ?></div>
            <div class="rateb-card-body p-0">
                <div class="table-responsive">
                    <table class="table rateb-table table-sm mb-0">
                        <thead><tr><th><?php echo __('from'); ?></th><th><?php echo __('to'); ?></th><th><?php echo __('status'); ?></th></tr></thead>
                        <tbody>
                        <?php if ($leaves === []) { echo '<tr><td colspan="3" class="text-muted">' . $escape(__('no_records')) . '</td></tr>'; }
                        foreach (array_slice($leaves, 0, 8) as $row) { ?>
                            <tr>
                                <td class="rateb-ltr-num"><?php echo $escape((string) ($row['start_date'] ?? '')); ?></td>
                                <td class="rateb-ltr-num"><?php echo $escape((string) ($row['end_date'] ?? '')); ?></td>
                                <td><?php echo $escape((string) ($row['status'] ?? '')); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="rateb-card mb-3">
            <div class="rateb-card-header"><?php echo __('hr_employee_requests'); ?> / <?php echo __('hr_letters'); ?></div>
            <div class="rateb-card-body p-0">
                <div class="table-responsive">
                    <table class="table rateb-table table-sm mb-0">
                        <thead><tr><th><?php echo __('type'); ?></th><th><?php echo __('status'); ?></th><th>#</th></tr></thead>
                        <tbody>
                        <?php
                        $reqShow = $letters !== [] ? $letters : $requests;
                        if ($reqShow === []) { echo '<tr><td colspan="3" class="text-muted">' . $escape(__('no_records')) . '</td></tr>'; }
                        foreach (array_slice($reqShow, 0, 8) as $row) { ?>
                            <tr>
                                <td><?php echo $escape((string) ($row['request_type'] ?? '')); ?></td>
                                <td><?php echo $escape((string) ($row['status'] ?? '')); ?></td>
                                <td class="rateb-ltr-num"><?php echo $escape((string) ($row['request_no'] ?? $row['id'] ?? '')); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="rateb-card mb-3">
            <div class="rateb-card-header"><?php echo __('payroll_payslips'); ?></div>
            <div class="rateb-card-body p-0">
                <div class="table-responsive">
                    <table class="table rateb-table table-sm mb-0">
                        <thead><tr><th><?php echo __('date'); ?></th><th><?php echo __('net'); ?></th><th><?php echo __('status'); ?></th></tr></thead>
                        <tbody>
                        <?php if ($payslips === []) { echo '<tr><td colspan="3" class="text-muted">' . $escape(__('no_records')) . '</td></tr>'; }
                        foreach (array_slice($payslips, 0, 8) as $row) { ?>
                            <tr>
                                <td class="rateb-ltr-num"><?php echo $escape((string) ($row['period'] ?? '')); ?></td>
                                <td class="rateb-ltr-num"><?php echo $escape((string) ($row['net_amount'] ?? '')); ?></td>
                                <td><?php echo $escape((string) ($row['status'] ?? '')); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="rateb-card mb-3">
            <div class="rateb-card-header"><?php echo __('hr_documents'); ?> / <?php echo __('hr_decisions'); ?></div>
            <div class="rateb-card-body">
                <div class="small mb-2"><?php echo __('hr_documents'); ?>: <strong class="rateb-ltr-num"><?php echo count($docs); ?></strong></div>
                <?php foreach (array_slice($decisions, 0, 5) as $d) { ?>
                    <div class="small border-bottom py-1">
                        <?php echo $escape((string) ($d['decision_no'] ?? '')); ?>
                        · <?php echo $escape((string) ($d['decision_type'] ?? '')); ?>
                        · <?php echo $escape((string) ($d['status'] ?? '')); ?>
                    </div>
                <?php } ?>
                <?php if ($decisions === []) { echo '<p class="text-muted small mb-0">' . $escape(__('no_records')) . '</p>'; } ?>
            </div>
        </div>
    </div>
</div>
