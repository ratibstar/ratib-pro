<?php
/**
 * Phase I — Employee 360 tab fragment (lazy HTML).
 *
 * @var string $tab
 * @var array<string, mixed> $data
 */
$tab = (string) ($tab ?? '');
$data = is_array($data ?? null) ? $data : [];
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
$fmtDate = static function (string $d): string {
    return $d !== '' ? \Rateb\App\Core\View::formatDate($d) : '—';
};

if ($tab === 'employment') {
    $fields = is_array($data['fields'] ?? null) ? $data['fields'] : [];
    ?>
    <h2 class="h6"><?php echo __('hr_360_tab_employment'); ?></h2>
    <dl class="row small mb-3">
        <?php
        $map = [
            'employee_code' => __('employee_code'),
            'department' => __('department'),
            'position' => __('job_title'),
            'branch' => __('branches'),
            'manager' => __('hr_360_manager'),
            'hire_date' => __('hire_date'),
            'status' => __('status'),
            'national_id' => __('national_id'),
        ];
        foreach ($map as $key => $label) {
            $val = (string) ($fields[$key] ?? '');
            if ($key === 'status' && $val !== '') {
                $val = __($val);
            }
            if ($key === 'hire_date') {
                $val = $fmtDate($val);
            }
            echo '<dt class="col-sm-3">' . $escape($label) . '</dt>';
            echo '<dd class="col-sm-9">' . $escape($val !== '' ? $val : '—') . '</dd>';
        }
        ?>
    </dl>
    <h2 class="h6"><?php echo __('hr_employment_contracts'); ?></h2>
    <?php
    $contracts = is_array($data['contracts'] ?? null) ? $data['contracts'] : [];
    if ($contracts === []) {
        echo '<p class="text-muted small mb-2">' . $escape(__('hr_360_no_contracts')) . '</p>';
    } else {
        echo '<div class="table-responsive mb-2"><table class="table table-sm rateb-table mb-0"><thead><tr>';
        echo '<th>' . $escape(__('hr_contract_no')) . '</th>';
        echo '<th>' . $escape(__('start_date')) . '</th>';
        echo '<th>' . $escape(__('end_date')) . '</th>';
        echo '<th>' . $escape(__('salary')) . '</th>';
        echo '<th>' . $escape(__('status')) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($contracts as $c) {
            $st = (string) ($c['status'] ?? '');
            $stLabel = __('hr_contract_status_' . $st);
            if ($stLabel === 'hr_contract_status_' . $st) {
                $stLabel = $st;
            }
            echo '<tr>';
            echo '<td class="rateb-ltr-num"><a href="' . $escape(rateb_url(rateb_app_route('hr/employment-contracts/' . (int) ($c['id'] ?? 0)))) . '">'
                . $escape((string) ($c['contract_no'] ?? '')) . '</a></td>';
            echo '<td class="rateb-ltr-num">' . $escape($fmtDate((string) ($c['start_date'] ?? ''))) . '</td>';
            echo '<td class="rateb-ltr-num">' . $escape($fmtDate((string) ($c['end_date'] ?? ''))) . '</td>';
            echo '<td class="rateb-ltr-num">' . $escape(number_format((float) ($c['salary'] ?? 0), 2)) . '</td>';
            echo '<td>' . $escape($stLabel) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    $reg = (string) ($data['contracts_register_url'] ?? '');
    if ($reg !== '') {
        echo '<a class="btn btn-sm btn-outline-secondary" href="' . $escape($reg) . '">' . $escape(__('hr_employment_contracts')) . '</a>';
    }
    ?>
    <?php
    return;
}

if ($tab === 'salary') {
    if (empty($data['authorized'])) {
        echo '<p class="text-muted mb-0">' . $escape(__('hr_360_salary_unauthorized')) . '</p>';
        return;
    }
    ?>
    <h2 class="h6"><?php echo __('basic_salary'); ?></h2>
    <p class="rateb-ltr-num fs-5 mb-3"><?php echo $escape(number_format((float) ($data['basic_salary'] ?? 0), 2)); ?></p>
    <h2 class="h6"><?php echo __('hr_payroll_components'); ?></h2>
    <?php if (empty($data['components'])) { ?>
        <p class="text-muted small"><?php echo __('no_records'); ?></p>
    <?php } else { ?>
    <div class="table-responsive">
        <table class="table rateb-table table-sm mb-0">
            <thead><tr>
                <th><?php echo __('name'); ?></th>
                <th><?php echo __('type'); ?></th>
                <th><?php echo __('value'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($data['components'] as $c) { ?>
                <tr>
                    <td><?php echo $escape((string) ($c['name'] ?? $c['code'] ?? '')); ?></td>
                    <td><?php echo $escape((string) ($c['component_type'] ?? '')); ?></td>
                    <td class="rateb-ltr-num"><?php echo $escape(number_format((float) ($c['value'] ?? 0), 2)); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
    <?php }
    return;
}

if ($tab === 'attendance') {
    $month = is_array($data['month'] ?? null) ? $data['month'] : [];
    $ytd = is_array($data['ytd'] ?? null) ? $data['ytd'] : [];
    ?>
    <h2 class="h6"><?php echo __('hr_360_attendance_month'); ?></h2>
    <div class="row g-2 mb-3">
        <?php
        $monthCards = [
            'present' => __('hr_360_att_present'),
            'late' => __('hr_360_att_late'),
            'absent' => __('hr_360_att_absent'),
            'on_leave' => __('hr_360_att_leave'),
            'holiday' => __('hr_360_att_holiday'),
        ];
        foreach ($monthCards as $k => $lab) { ?>
        <div class="col-6 col-md-4 col-lg-2">
            <div class="rateb-emp360-kpi">
                <div class="rateb-emp360-kpi-label"><?php echo $escape($lab); ?></div>
                <div class="rateb-emp360-kpi-value rateb-ltr-num"><?php echo (int) ($month[$k] ?? 0); ?></div>
            </div>
        </div>
        <?php } ?>
    </div>
    <h2 class="h6"><?php echo __('hr_360_attendance_ytd'); ?></h2>
    <ul class="list-unstyled small mb-3">
        <li><?php echo __('hr_360_att_present'); ?>: <span class="rateb-ltr-num"><?php echo (int) ($ytd['present'] ?? 0); ?></span></li>
        <li><?php echo __('hr_360_att_absent'); ?>: <span class="rateb-ltr-num"><?php echo (int) ($ytd['absent'] ?? 0); ?></span></li>
        <li><?php echo __('hr_360_att_leave'); ?>: <span class="rateb-ltr-num"><?php echo (int) ($ytd['on_leave'] ?? 0); ?></span></li>
    </ul>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_url(rateb_app_route('hr/attendance')); ?>"><?php echo __('hr_attendance'); ?></a>
    <?php
    return;
}

if ($tab === 'leaves') {
    $balances = is_array($data['balances'] ?? null) ? $data['balances'] : [];
    $recent = is_array($data['recent'] ?? null) ? $data['recent'] : [];
    $upcoming = is_array($data['upcoming'] ?? null) ? $data['upcoming'] : [];
    $summary = is_array($data['summary'] ?? null) ? $data['summary'] : [];
    ?>
    <div class="row g-2 mb-3">
        <div class="col-md-3"><div class="rateb-emp360-kpi"><div class="rateb-emp360-kpi-label"><?php echo __('hr_360_kpi_leave_entitled'); ?></div><div class="rateb-emp360-kpi-value rateb-ltr-num"><?php echo $escape(number_format((float) ($summary['entitled'] ?? 0), 1)); ?></div></div></div>
        <div class="col-md-3"><div class="rateb-emp360-kpi"><div class="rateb-emp360-kpi-label"><?php echo __('hr_360_kpi_leave_used'); ?></div><div class="rateb-emp360-kpi-value rateb-ltr-num"><?php echo $escape(number_format((float) ($summary['used'] ?? 0), 1)); ?></div></div></div>
        <div class="col-md-3"><div class="rateb-emp360-kpi"><div class="rateb-emp360-kpi-label"><?php echo __('hr_360_kpi_leave_remaining'); ?></div><div class="rateb-emp360-kpi-value rateb-ltr-num"><?php echo $escape(number_format((float) ($summary['remaining'] ?? 0), 1)); ?></div></div></div>
        <div class="col-md-3"><div class="rateb-emp360-kpi"><div class="rateb-emp360-kpi-label"><?php echo __('hr_pending_leaves'); ?></div><div class="rateb-emp360-kpi-value rateb-ltr-num"><?php echo (int) ($data['pending_count'] ?? 0); ?></div></div></div>
    </div>
    <h2 class="h6"><?php echo __('leave_balances'); ?></h2>
    <?php if ($balances === []) { ?><p class="text-muted small"><?php echo __('no_records'); ?></p><?php } else { ?>
    <div class="table-responsive mb-3">
        <table class="table rateb-table table-sm"><thead><tr>
            <th><?php echo __('leave_type'); ?></th>
            <th><?php echo __('hr_360_kpi_leave_entitled'); ?></th>
            <th><?php echo __('hr_360_kpi_leave_used'); ?></th>
            <th><?php echo __('hr_360_kpi_leave_remaining'); ?></th>
        </tr></thead><tbody>
        <?php foreach ($balances as $bal) {
            $rem = (float) ($bal['remaining_days'] ?? ((float) ($bal['entitled_days'] ?? 0) - (float) ($bal['used_days'] ?? 0)));
            ?>
            <tr>
                <td><?php echo $escape((string) ($bal['leave_type_name'] ?? '')); ?></td>
                <td class="rateb-ltr-num"><?php echo $escape(number_format((float) ($bal['entitled_days'] ?? 0), 1)); ?></td>
                <td class="rateb-ltr-num"><?php echo $escape(number_format((float) ($bal['used_days'] ?? 0), 1)); ?></td>
                <td class="rateb-ltr-num"><?php echo $escape(number_format($rem, 1)); ?></td>
            </tr>
        <?php } ?>
        </tbody></table>
    </div>
    <?php } ?>
    <h2 class="h6"><?php echo __('hr_360_upcoming_leave'); ?></h2>
    <?php if ($upcoming === []) { ?><p class="text-muted small"><?php echo __('no_records'); ?></p><?php } else { ?>
    <ul class="small mb-3">
        <?php foreach ($upcoming as $u) { ?>
        <li><?php echo $escape((string) ($u['leave_type_name'] ?? '')); ?> —
            <?php echo $fmtDate((string) ($u['start_date'] ?? '')); ?> → <?php echo $fmtDate((string) ($u['end_date'] ?? '')); ?>
            (<?php echo $escape((string) ($u['days'] ?? '')); ?>)</li>
        <?php } ?>
    </ul>
    <?php } ?>
    <h2 class="h6"><?php echo __('recent_leaves'); ?></h2>
    <?php if ($recent === []) { ?><p class="text-muted small mb-0"><?php echo __('no_records'); ?></p><?php } else { ?>
    <div class="table-responsive">
        <table class="table rateb-table table-sm mb-0"><thead><tr>
            <th><?php echo __('leave_type'); ?></th>
            <th><?php echo __('start_date'); ?></th>
            <th><?php echo __('end_date'); ?></th>
            <th><?php echo __('days'); ?></th>
            <th><?php echo __('status'); ?></th>
        </tr></thead><tbody>
        <?php foreach ($recent as $lv) { ?>
            <tr>
                <td><?php echo $escape((string) ($lv['leave_type_name'] ?? '')); ?></td>
                <td><?php echo $fmtDate((string) ($lv['start_date'] ?? '')); ?></td>
                <td><?php echo $fmtDate((string) ($lv['end_date'] ?? '')); ?></td>
                <td class="rateb-ltr-num"><?php echo $escape((string) ($lv['days'] ?? '')); ?></td>
                <td><?php echo $escape(__((string) ($lv['status'] ?? ''))); ?></td>
            </tr>
        <?php } ?>
        </tbody></table>
    </div>
    <?php }
    return;
}

if ($tab === 'requests' || $tab === 'letters') {
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    if ($tab === 'letters') {
        echo '<p class="small text-muted">' . $escape(__('hr_360_letter_pdf_deferred')) . '</p>';
    }
    if ($items === []) {
        echo '<p class="text-muted mb-0">' . $escape(__('no_records')) . '</p>';
        return;
    }
    ?>
    <div class="table-responsive">
        <table class="table rateb-table table-sm mb-0"><thead><tr>
            <th><?php echo __('type'); ?></th>
            <th><?php echo __('date'); ?></th>
            <th><?php echo __('status'); ?></th>
            <th><?php echo __('hr_360_approval_stage'); ?></th>
            <th><?php echo __('created_at'); ?></th>
        </tr></thead><tbody>
        <?php foreach ($items as $row) {
            $stage = (string) ($row['stage_name'] ?? '');
            if ($stage !== '' && isset($row['stage_order'], $row['max_stage_order'])) {
                $stage .= ' (' . (int) $row['stage_order'] . '/' . (int) $row['max_stage_order'] . ')';
            }
            ?>
            <tr>
                <td><?php echo $escape((string) ($row['request_type'] ?? '')); ?></td>
                <td><?php echo $fmtDate((string) ($row['request_date'] ?? '')); ?></td>
                <td><?php echo $escape(__((string) ($row['status'] ?? ''))); ?></td>
                <td><?php echo $escape($stage !== '' ? $stage : '—'); ?></td>
                <td><?php echo $fmtDate((string) ($row['created_at'] ?? '')); ?></td>
            </tr>
        <?php } ?>
        </tbody></table>
    </div>
    <?php
    return;
}

if ($tab === 'payroll') {
    if (empty($data['authorized'])) {
        echo '<p class="text-muted mb-0">' . $escape(__('hr_360_salary_unauthorized')) . '</p>';
        return;
    }
    echo '<p class="small text-muted">' . $escape(__('payroll_posted_status_note')) . '</p>';
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    if ($items === []) {
        echo '<p class="text-muted mb-0">' . $escape(__('no_records')) . '</p>';
        return;
    }
    ?>
    <div class="table-responsive">
        <table class="table rateb-table table-sm mb-0"><thead><tr>
            <th><?php echo __('hr_360_period'); ?></th>
            <th><?php echo __('hr_360_gross'); ?></th>
            <th><?php echo __('deductions'); ?></th>
            <th><?php echo __('net_salary'); ?></th>
            <th><?php echo __('status'); ?></th>
        </tr></thead><tbody>
        <?php foreach ($items as $row) {
            $period = sprintf('%04d-%02d', (int) ($row['period_year'] ?? 0), (int) ($row['period_month'] ?? 0));
            ?>
            <tr>
                <td class="rateb-ltr-num"><?php echo $escape($period); ?></td>
                <td class="rateb-ltr-num"><?php echo $escape(number_format((float) ($row['gross'] ?? 0), 2)); ?></td>
                <td class="rateb-ltr-num"><?php echo $escape(number_format((float) ($row['deductions'] ?? 0), 2)); ?></td>
                <td class="rateb-ltr-num"><?php echo $escape(number_format((float) ($row['net'] ?? 0), 2)); ?></td>
                <td><?php echo $escape(__((string) ($row['status'] ?? ''))); ?></td>
            </tr>
        <?php } ?>
        </tbody></table>
    </div>
    <?php
    return;
}

if ($tab === 'documents') {
    $files = is_array($data['files'] ?? null) ? $data['files'] : [];
    $meta = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
    echo '<p class="small text-muted">' . $escape(__('hr_360_documents_deferred_note')) . '</p>';
    ?>
    <h2 class="h6"><?php echo __('hr_360_doc_files'); ?></h2>
    <?php if ($files === []) { ?><p class="text-muted small"><?php echo __('no_records'); ?></p><?php } else { ?>
    <ul class="small mb-3">
        <?php foreach ($files as $f) { ?>
        <li><?php echo $escape((string) ($f['title'] ?? $f['file_name'] ?? '#' . ($f['id'] ?? ''))); ?></li>
        <?php } ?>
    </ul>
    <?php } ?>
    <h2 class="h6"><?php echo __('hr_documents'); ?></h2>
    <?php if ($meta === []) { ?><p class="text-muted small mb-0"><?php echo __('no_records'); ?></p><?php } else { ?>
    <div class="table-responsive">
        <table class="table rateb-table table-sm mb-0"><thead><tr>
            <th><?php echo __('title'); ?></th>
            <th><?php echo __('type'); ?></th>
            <th><?php echo __('issue_date'); ?></th>
            <th><?php echo __('expiry_date'); ?></th>
        </tr></thead><tbody>
        <?php foreach ($meta as $m) { ?>
            <tr>
                <td><?php echo $escape((string) ($m['title'] ?? '')); ?></td>
                <td><?php echo $escape((string) ($m['doc_type'] ?? '')); ?></td>
                <td><?php echo $fmtDate((string) ($m['issue_date'] ?? '')); ?></td>
                <td><?php echo $fmtDate((string) ($m['expiry_date'] ?? '')); ?></td>
            </tr>
        <?php } ?>
        </tbody></table>
    </div>
    <?php }
    return;
}

if ($tab === 'violations') {
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    if (empty($data['available'])) {
        echo '<p class="text-muted mb-0">' . $escape(__('hr_360_violations_unavailable')) . '</p>';
        return;
    }
    if ($items === []) {
        echo '<p class="text-muted mb-0">' . $escape(__('no_records')) . '</p>';
        return;
    }
    ?>
    <div class="table-responsive">
        <table class="table rateb-table table-sm mb-0"><thead><tr>
            <th><?php echo __('type'); ?></th>
            <th><?php echo __('title'); ?></th>
            <th><?php echo __('date'); ?></th>
            <th><?php echo __('status'); ?></th>
        </tr></thead><tbody>
        <?php foreach ($items as $row) { ?>
            <tr>
                <td><?php echo $escape((string) ($row['action_type'] ?? '')); ?></td>
                <td><?php echo $escape((string) ($row['title'] ?? $row['code'] ?? '')); ?></td>
                <td><?php echo $fmtDate((string) ($row['action_date'] ?? '')); ?></td>
                <td><?php echo $escape((string) ($row['status'] ?? '')); ?></td>
            </tr>
        <?php } ?>
        </tbody></table>
    </div>
    <?php
    return;
}

if ($tab === 'timeline') {
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    if ($items === []) {
        echo '<p class="text-muted mb-0">' . $escape(__('no_records')) . '</p>';
        return;
    }
    ?>
    <ul class="list-group list-group-flush rateb-emp360-timeline">
        <?php foreach ($items as $ev) {
            $labelKey = (string) ($ev['label'] ?? $ev['type'] ?? '');
            $label = function_exists('__') ? __($labelKey) : $labelKey;
            if ($label === $labelKey) {
                $label = $labelKey;
            }
            ?>
        <li class="list-group-item px-0">
            <div class="fw-semibold"><?php echo $escape($label); ?></div>
            <div class="small text-muted"><?php echo $escape((string) ($ev['at'] ?? '')); ?>
                <?php if (($ev['detail'] ?? '') !== '') { ?> · <?php echo $escape((string) $ev['detail']); ?><?php } ?>
            </div>
        </li>
        <?php } ?>
    </ul>
    <?php
    return;
}

echo '<p class="text-muted mb-0">' . $escape(__('hr_coming_soon')) . '</p>';
