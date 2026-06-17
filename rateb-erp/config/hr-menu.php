<?php
declare(strict_types=1);

/**
 * HR module sidebar tree — matches enterprise HR navigation structure.
 *
 * @return list<array<string, mixed>>
 */
return [
    ['id' => 'overview', 'label' => 'hr_overview', 'icon' => 'fa-gauge-high', 'route' => 'hr'],
    ['id' => 'employees', 'label' => 'hr_employee_list', 'icon' => 'fa-list', 'route' => 'hr/employees'],
    ['id' => 'departments', 'label' => 'hr_departments', 'icon' => 'fa-sitemap', 'route' => 'hr/departments'],
    ['id' => 'holidays', 'label' => 'hr_holidays', 'icon' => 'fa-calendar-day', 'route' => 'hr/holidays', 'stub' => true],
    [
        'id' => 'attendance-group',
        'label' => 'hr_attendance_short',
        'icon' => 'fa-clock',
        'children' => [
            ['id' => 'workplaces', 'label' => 'hr_workplaces', 'icon' => 'fa-location-dot', 'route' => 'hr/workplaces', 'stub' => true],
            ['id' => 'permission-requests', 'label' => 'hr_permission_requests', 'icon' => 'fa-right-to-bracket', 'route' => 'hr/permission-requests', 'stub' => true],
            ['id' => 'attendance-daily', 'label' => 'hr_attendance_daily', 'icon' => 'fa-calendar-check', 'route' => 'hr/attendance'],
            ['id' => 'attendance-bulk', 'label' => 'hr_attendance_bulk', 'icon' => 'fa-upload', 'route' => 'hr/attendance/bulk', 'stub' => true],
            ['id' => 'attendance-monthly', 'label' => 'hr_attendance_monthly', 'icon' => 'fa-chart-column', 'route' => 'hr/reports'],
        ],
    ],
    [
        'id' => 'leaves-group',
        'label' => 'hr_leaves',
        'icon' => 'fa-plane',
        'children' => [
            ['id' => 'leave-requests', 'label' => 'hr_leave_requests', 'icon' => 'fa-list', 'route' => 'hr/leaves'],
            ['id' => 'leave-create', 'label' => 'hr_leave_submit', 'icon' => 'fa-circle-plus', 'route' => 'hr/leaves/create'],
            ['id' => 'leave-types', 'label' => 'leave_types', 'icon' => 'fa-tag', 'route' => 'hr/leave-types'],
            ['id' => 'leave-balances', 'label' => 'hr_leave_balances', 'icon' => 'fa-calculator', 'route' => 'hr/leaves'],
            ['id' => 'leave-report', 'label' => 'hr_leave_report', 'icon' => 'fa-file-lines', 'route' => 'hr/reports'],
        ],
    ],
    [
        'id' => 'loans-group',
        'label' => 'hr_loans',
        'icon' => 'fa-money-bill',
        'children' => [
            ['id' => 'loans-list', 'label' => 'hr_loans_list', 'icon' => 'fa-list', 'route' => 'hr/loans', 'stub' => true],
            ['id' => 'loans-create', 'label' => 'hr_loan_add', 'icon' => 'fa-circle-plus', 'route' => 'hr/loans/create', 'stub' => true],
            ['id' => 'loan-types', 'label' => 'hr_loan_types', 'icon' => 'fa-tag', 'route' => 'hr/loan-types', 'stub' => true],
        ],
    ],
    [
        'id' => 'payroll-group',
        'label' => 'hr_payroll',
        'icon' => 'fa-credit-card',
        'children' => [
            ['id' => 'payroll-list', 'label' => 'hr_payroll_list', 'icon' => 'fa-list', 'route' => 'hr/payroll'],
            ['id' => 'payroll-create', 'label' => 'hr_payroll_create', 'icon' => 'fa-gears', 'route' => 'hr/payroll/create'],
            ['id' => 'payroll-components', 'label' => 'hr_payroll_components', 'icon' => 'fa-puzzle-piece', 'route' => 'hr/payroll/components', 'stub' => true],
            ['id' => 'payroll-structure', 'label' => 'hr_payroll_structure', 'icon' => 'fa-list', 'route' => 'hr/payroll/structure', 'stub' => true],
        ],
    ],
    [
        'id' => 'documents-group',
        'label' => 'hr_documents',
        'icon' => 'fa-file-lines',
        'children' => [
            ['id' => 'documents-manage', 'label' => 'hr_documents_manage', 'icon' => 'fa-list', 'route' => 'hr/documents', 'stub' => true],
            ['id' => 'documents-add', 'label' => 'hr_document_add', 'icon' => 'fa-circle-plus', 'route' => 'hr/documents/create', 'stub' => true],
        ],
    ],
    ['id' => 'employee-requests', 'label' => 'hr_employee_requests', 'icon' => 'fa-file-lines', 'route' => 'hr/requests', 'stub' => true],
    [
        'id' => 'fleet-group',
        'label' => 'hr_fleet',
        'icon' => 'fa-car',
        'children' => [
            ['id' => 'fleet-manage', 'label' => 'hr_fleet_manage', 'icon' => 'fa-list', 'route' => 'hr/fleet', 'stub' => true],
            ['id' => 'fleet-add', 'label' => 'hr_fleet_add', 'icon' => 'fa-circle-plus', 'route' => 'hr/fleet/create', 'stub' => true],
        ],
    ],
];
