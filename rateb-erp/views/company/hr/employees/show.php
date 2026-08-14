<?php
/**
 * Phase I — Employee Master 360 (canonical show route).
 *
 * @var array<string, mixed> $shell
 * @var string $routePrefix
 * @var string $tabEndpoint
 * @var string $activeTab
 * @var bool $canManage
 * @var int $employeeId
 */
$shell = $shell ?? [];
$header = is_array($shell['header'] ?? null) ? $shell['header'] : [];
$overview = is_array($shell['overview'] ?? null) ? $shell['overview'] : [];
$kpis = is_array($shell['kpis'] ?? null) ? $shell['kpis'] : [];
$actions = is_array($shell['actions'] ?? null) ? $shell['actions'] : [];
$tabs = is_array($shell['tabs'] ?? null) ? $shell['tabs'] : \Rateb\App\Services\HrEmployee360Service::TABS;
$activeTab = (string) ($activeTab ?? 'overview');
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/employees'));
$tabEndpoint = (string) ($tabEndpoint ?? '');
$employeeId = (int) ($employeeId ?? ($header['id'] ?? 0));
$essLinked = !empty($shell['ess_linked']);
$canManage = (bool) ($canManage ?? false);

$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
$status = (string) ($header['status'] ?? 'active');
$statusClass = $status === 'active' ? 'success' : ($status === 'terminated' ? 'danger' : 'secondary');

$tabLabels = [
    'overview' => __('hr_360_tab_overview'),
    'employment' => __('hr_360_tab_employment'),
    'salary' => __('hr_360_tab_salary'),
    'attendance' => __('hr_360_tab_attendance'),
    'leaves' => __('hr_360_tab_leaves'),
    'requests' => __('hr_360_tab_requests'),
    'letters' => __('hr_360_tab_letters'),
    'payroll' => __('hr_360_tab_payroll'),
    'documents' => __('hr_360_tab_documents'),
    'decisions' => __('hr_360_tab_decisions'),
    'violations' => __('hr_360_tab_violations'),
    'timeline' => __('hr_360_tab_timeline'),
];

Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'employees']);
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/hr-module.css'); ?>">

<div class="rateb-emp360" data-rateb-emp360 data-tab-endpoint="<?php echo $escape($tabEndpoint); ?>" data-active-tab="<?php echo $escape($activeTab); ?>">
    <div class="rateb-emp360-header rateb-card mb-3">
        <div class="rateb-card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="d-flex gap-3 align-items-start">
                    <div class="rateb-emp360-avatar" aria-hidden="true"><?php echo $escape((string) ($header['initials'] ?? '?')); ?></div>
                    <div>
                        <h1 class="h4 mb-1"><?php echo $escape((string) ($header['name'] ?? '')); ?></h1>
                        <div class="text-muted small mb-2">
                            <span class="rateb-ltr-num"><?php echo $escape((string) ($header['employee_code'] ?? '')); ?></span>
                            <?php if (($header['department'] ?? '') !== '') { ?>
                                · <?php echo $escape((string) $header['department']); ?>
                            <?php } ?>
                            <?php if (($header['position'] ?? '') !== '') { ?>
                                · <?php echo $escape((string) $header['position']); ?>
                            <?php } ?>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <span class="badge text-bg-<?php echo $escape($statusClass); ?>"><?php echo $escape(__($status)); ?></span>
                            <?php if (($header['hire_date'] ?? '') !== '') { ?>
                                <span class="small text-muted"><?php echo __('hire_date'); ?>: <?php echo \Rateb\App\Core\View::formatDate((string) $header['hire_date']); ?></span>
                            <?php } ?>
                            <span class="badge <?php echo $essLinked ? 'text-bg-info' : 'text-bg-light text-dark'; ?>">
                                <?php echo $essLinked ? __('hr_360_ess_linked') : __('hr_360_ess_not_linked'); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('back'); ?></a>
                    <?php if (!empty($actions['edit'])) { ?>
                    <a href="<?php echo rateb_url($routePrefix . '/' . $employeeId . '/edit'); ?>" class="btn btn-primary btn-sm">
                        <i class="fas fa-edit"></i> <?php echo __('edit'); ?>
                    </a>
                    <?php } ?>
                    <?php if (!empty($actions['new_leave'])) { ?>
                    <a href="<?php echo rateb_url(rateb_app_route('hr/leaves') . '/create'); ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-plane"></i> <?php echo __('hr_leave_submit'); ?>
                    </a>
                    <?php } ?>
                    <?php if (!empty($actions['new_request'])) { ?>
                    <a href="<?php echo rateb_url(rateb_app_route('hr/requests') . '/create'); ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-file-lines"></i> <?php echo __('hr_360_request_letter'); ?>
                    </a>
                    <?php } ?>
                    <?php if (!empty($actions['view_attendance'])) { ?>
                    <a href="<?php echo rateb_url(rateb_app_route('hr/attendance')); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('hr_attendance'); ?></a>
                    <?php } ?>
                    <?php if (!empty($actions['view_payroll'])) { ?>
                    <a href="<?php echo rateb_url(rateb_app_route('hr/payroll')); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('hr_payroll'); ?></a>
                    <?php } ?>
                    <a href="<?php echo rateb_url($routePrefix . '/' . $employeeId . '/documents'); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('hr_documents'); ?></a>
                    <?php if (!empty($actions['view_requests'])) { ?>
                    <a href="<?php echo rateb_url(rateb_app_route('hr/requests')); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('hr_employee_requests'); ?></a>
                    <?php } ?>
                </div>
            </div>

            <div class="row g-2 mt-3">
                <div class="col-6 col-md-3 col-lg-2">
                    <div class="rateb-emp360-kpi">
                        <div class="rateb-emp360-kpi-label"><?php echo __('hr_360_kpi_leave_remaining'); ?></div>
                        <div class="rateb-emp360-kpi-value rateb-ltr-num"><?php echo $escape(number_format((float) ($kpis['leave_remaining'] ?? 0), 1)); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <div class="rateb-emp360-kpi">
                        <div class="rateb-emp360-kpi-label"><?php echo __('hr_360_kpi_leave_used'); ?></div>
                        <div class="rateb-emp360-kpi-value rateb-ltr-num"><?php echo $escape(number_format((float) ($kpis['leave_used'] ?? 0), 1)); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <div class="rateb-emp360-kpi">
                        <div class="rateb-emp360-kpi-label"><?php echo __('hr_pending_leaves'); ?></div>
                        <div class="rateb-emp360-kpi-value rateb-ltr-num"><?php echo (int) ($kpis['pending_leaves'] ?? 0); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <div class="rateb-emp360-kpi">
                        <div class="rateb-emp360-kpi-label"><?php echo __('hr_360_kpi_absent_ytd'); ?></div>
                        <div class="rateb-emp360-kpi-value rateb-ltr-num"><?php echo (int) (($kpis['attendance_ytd']['absent'] ?? 0)); ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3 col-lg-2">
                    <div class="rateb-emp360-kpi">
                        <div class="rateb-emp360-kpi-label"><?php echo __('hr_360_kpi_present_ytd'); ?></div>
                        <div class="rateb-emp360-kpi-value rateb-ltr-num"><?php echo (int) (($kpis['attendance_ytd']['present'] ?? 0)); ?></div>
                    </div>
                </div>
                <?php if ($kpis['salary_base'] !== null) { ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <div class="rateb-emp360-kpi">
                        <div class="rateb-emp360-kpi-label"><?php echo __('salary_base'); ?></div>
                        <div class="rateb-emp360-kpi-value rateb-ltr-num"><?php echo $escape(number_format((float) $kpis['salary_base'], 2)); ?></div>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs rateb-emp360-tabs mb-3" role="tablist">
        <?php foreach ($tabs as $tabKey) {
            $label = $tabLabels[$tabKey] ?? $tabKey;
            $isActive = $activeTab === $tabKey;
            ?>
        <li class="nav-item" role="presentation">
            <button type="button"
                class="nav-link<?php echo $isActive ? ' active' : ''; ?>"
                data-rateb-emp360-tab="<?php echo $escape((string) $tabKey); ?>"
                role="tab"
                aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>">
                <?php echo $escape((string) $label); ?>
            </button>
        </li>
        <?php } ?>
    </ul>

    <div class="rateb-card">
        <div class="rateb-card-body" data-rateb-emp360-panel>
            <?php
            // Overview is server-rendered for first paint; other tabs lazy-load.
            $ov = $overview;
            $personal = is_array($ov['personal'] ?? null) ? $ov['personal'] : [];
            $employment = is_array($ov['employment'] ?? null) ? $ov['employment'] : [];
            $ovKpis = is_array($ov['kpis'] ?? null) ? $ov['kpis'] : [];
            ?>
            <div data-rateb-emp360-overview <?php echo $activeTab !== 'overview' ? 'hidden' : ''; ?>>
                <div class="row g-3">
                    <div class="col-lg-6">
                        <h2 class="h6"><?php echo __('hr_360_personal'); ?></h2>
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4"><?php echo __('name'); ?></dt>
                            <dd class="col-sm-8"><?php echo $escape((string) ($personal['name'] ?? '')); ?></dd>
                            <dt class="col-sm-4"><?php echo __('employee_code'); ?></dt>
                            <dd class="col-sm-8 rateb-ltr-num"><?php echo $escape((string) ($personal['employee_code'] ?? '')); ?></dd>
                            <dt class="col-sm-4"><?php echo __('email'); ?></dt>
                            <dd class="col-sm-8"><?php echo $escape((string) (($personal['email'] ?? '') !== '' ? $personal['email'] : '—')); ?></dd>
                            <dt class="col-sm-4"><?php echo __('phone'); ?></dt>
                            <dd class="col-sm-8 rateb-ltr-num"><?php echo $escape((string) (($personal['phone'] ?? '') !== '' ? $personal['phone'] : '—')); ?></dd>
                            <dt class="col-sm-4"><?php echo __('national_id'); ?></dt>
                            <dd class="col-sm-8 rateb-ltr-num"><?php echo $escape((string) (($personal['national_id'] ?? '') !== '' ? $personal['national_id'] : '—')); ?></dd>
                        </dl>
                    </div>
                    <div class="col-lg-6">
                        <h2 class="h6"><?php echo __('hr_360_employment_summary'); ?></h2>
                        <dl class="row mb-0 small">
                            <dt class="col-sm-4"><?php echo __('department'); ?></dt>
                            <dd class="col-sm-8"><?php echo $escape((string) (($employment['department'] ?? '') !== '' ? $employment['department'] : '—')); ?></dd>
                            <dt class="col-sm-4"><?php echo __('job_title'); ?></dt>
                            <dd class="col-sm-8"><?php echo $escape((string) (($employment['position'] ?? '') !== '' ? $employment['position'] : '—')); ?></dd>
                            <dt class="col-sm-4"><?php echo __('branches'); ?></dt>
                            <dd class="col-sm-8"><?php echo $escape((string) (($employment['branch'] ?? '') !== '' ? $employment['branch'] : '—')); ?></dd>
                            <dt class="col-sm-4"><?php echo __('hr_360_manager'); ?></dt>
                            <dd class="col-sm-8"><?php echo $escape((string) (($employment['manager'] ?? '') !== '' ? $employment['manager'] : '—')); ?></dd>
                            <dt class="col-sm-4"><?php echo __('hire_date'); ?></dt>
                            <dd class="col-sm-8"><?php echo ($employment['hire_date'] ?? '') !== '' ? \Rateb\App\Core\View::formatDate((string) $employment['hire_date']) : '—'; ?></dd>
                            <dt class="col-sm-4"><?php echo __('status'); ?></dt>
                            <dd class="col-sm-8"><?php echo $escape(__((string) ($employment['status'] ?? 'active'))); ?></dd>
                        </dl>
                        <p class="small text-muted mt-3 mb-0"><?php echo __('hr_360_contracts_deferred'); ?></p>
                    </div>
                </div>
            </div>
            <div data-rateb-emp360-lazy class="rateb-emp360-lazy" <?php echo $activeTab === 'overview' ? 'hidden' : ''; ?>>
                <div class="text-muted small py-4 text-center" data-rateb-emp360-loading><?php echo __('loading'); ?>…</div>
                <div data-rateb-emp360-content hidden></div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo rateb_asset('js/hr-employee-360.js'); ?>" defer></script>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
