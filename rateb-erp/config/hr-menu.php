<?php
declare(strict_types=1);

/**
 * HR module sidebar — no duplicate routes, all items implemented.
 *
 * @return list<array<string, mixed>>
 */
return [
    ['id' => 'overview', 'label' => 'hr_overview', 'icon' => 'fa-gauge-high', 'route' => 'hr'],
    ['id' => 'employees', 'label' => 'hr_employee_list', 'icon' => 'fa-list', 'route' => 'hr/employees'],
    ['id' => 'departments', 'label' => 'hr_departments', 'icon' => 'fa-sitemap', 'route' => 'hr/departments'],
    ['id' => 'holidays', 'label' => 'hr_holidays', 'icon' => 'fa-calendar-day', 'route' => 'hr/holidays'],
    [
        'id' => 'attendance-group',
        'label' => 'hr_attendance_short',
        'icon' => 'fa-clock',
        'children' => [
            ['id' => 'workplaces', 'label' => 'hr_workplaces', 'icon' => 'fa-location-dot', 'route' => 'hr/workplaces'],
            ['id' => 'permission-requests', 'label' => 'hr_permission_requests', 'icon' => 'fa-right-to-bracket', 'route' => 'hr/permission-requests'],
            ['id' => 'attendance-daily', 'label' => 'hr_attendance_daily', 'icon' => 'fa-calendar-check', 'route' => 'hr/attendance'],
            ['id' => 'attendance-bulk', 'label' => 'hr_attendance_bulk', 'icon' => 'fa-upload', 'route' => 'hr/attendance/bulk'],
            ['id' => 'attendance-monthly', 'label' => 'hr_attendance_monthly', 'icon' => 'fa-chart-column', 'route' => 'hr/reports'],
        ],
    ],
    [
        'id' => 'leaves-group',
        'label' => 'hr_leaves',
        'icon' => 'fa-plane',
        'children' => [
            ['id' => 'leave-requests', 'label' => 'hr_leave_requests', 'icon' => 'fa-list', 'route' => 'hr/leaves'],
            ['id' => 'leave-types', 'label' => 'leave_types', 'icon' => 'fa-tag', 'route' => 'hr/leave-types'],
            ['id' => 'leave-balances', 'label' => 'hr_leave_balances', 'icon' => 'fa-calculator', 'route' => 'hr/leaves/balances'],
            ['id' => 'leave-report', 'label' => 'hr_leave_report', 'icon' => 'fa-file-lines', 'route' => 'hr/reports/leaves'],
        ],
    ],
    [
        'id' => 'loans-group',
        'label' => 'hr_loans',
        'icon' => 'fa-money-bill',
        'children' => [
            ['id' => 'loans-list', 'label' => 'hr_loans_list', 'icon' => 'fa-list', 'route' => 'hr/loans'],
            ['id' => 'loan-types', 'label' => 'hr_loan_types', 'icon' => 'fa-tag', 'route' => 'hr/loan-types'],
        ],
    ],
    [
        'id' => 'payroll-group',
        'label' => 'hr_payroll',
        'icon' => 'fa-credit-card',
        'children' => [
            ['id' => 'payroll-list', 'label' => 'hr_payroll_list', 'icon' => 'fa-list', 'route' => 'hr/payroll'],
            ['id' => 'payroll-components', 'label' => 'hr_payroll_components', 'icon' => 'fa-puzzle-piece', 'route' => 'hr/payroll/components'],
            ['id' => 'payroll-structure', 'label' => 'hr_payroll_structure', 'icon' => 'fa-list', 'route' => 'hr/payroll/structure'],
        ],
    ],
    ['id' => 'documents-manage', 'label' => 'hr_documents', 'icon' => 'fa-file-lines', 'route' => 'hr/documents'],
    ['id' => 'employee-requests', 'label' => 'hr_employee_requests', 'icon' => 'fa-file-lines', 'route' => 'hr/requests'],
    ['id' => 'fleet-manage', 'label' => 'hr_fleet', 'icon' => 'fa-car', 'route' => 'hr/fleet'],
];
