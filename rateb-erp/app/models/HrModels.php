<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class HrDepartment extends Model
{
    protected string $table = 'rateb_hr_departments';
    protected bool $tenantScoped = true;
    protected array $fillable = ['company_id', 'name', 'code', 'status'];
}

final class Employee extends Model
{
    protected string $table = 'rateb_employees';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'employee_code', 'name', 'email', 'phone', 'national_id',
        'department_id', 'branch_id', 'job_title', 'hire_date', 'salary_base', 'user_id', 'status', 'notes',
    ];
}

final class AttendanceRecord extends Model
{
    protected string $table = 'rateb_attendance_records';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'employee_id', 'attendance_date', 'check_in', 'check_out', 'status', 'notes', 'branch_id',
    ];
}

final class LeaveType extends Model
{
    protected string $table = 'rateb_leave_types';
    protected bool $tenantScoped = true;
    protected array $fillable = ['company_id', 'name', 'paid', 'days_per_year', 'status'];
}

final class LeaveRequest extends Model
{
    protected string $table = 'rateb_leave_requests';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'employee_id', 'leave_type_id', 'start_date', 'end_date',
        'days', 'reason', 'status', 'approved_by', 'approved_at', 'branch_id',
    ];
}

final class PayrollPeriod extends Model
{
    protected string $table = 'rateb_payroll_periods';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = ['company_id', 'period_year', 'period_month', 'status', 'notes', 'branch_id'];
}

final class PayrollLine extends Model
{
    protected string $table = 'rateb_payroll_lines';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'period_id', 'employee_id', 'basic_salary',
        'allowances', 'deductions', 'net_salary', 'notes', 'branch_id',
    ];
}

final class LeaveBalance extends Model
{
    protected string $table = 'rateb_leave_balances';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'employee_id', 'leave_type_id', 'balance_year', 'entitled_days', 'used_days',
    ];
}

final class HrHoliday extends Model
{
    protected string $table = 'rateb_hr_holidays';
    protected bool $tenantScoped = true;
    protected array $fillable = ['company_id', 'name', 'holiday_date', 'is_recurring', 'status', 'notes'];
}

final class HrWorkplace extends Model
{
    protected string $table = 'rateb_hr_workplaces';
    protected bool $tenantScoped = true;
    protected array $fillable = ['company_id', 'name', 'address', 'latitude', 'longitude', 'radius_meters', 'status'];
}

final class HrPermissionRequest extends Model
{
    protected string $table = 'rateb_hr_permission_requests';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'employee_id', 'permission_date', 'time_from', 'time_to',
        'reason', 'status', 'approved_by', 'approved_at',
    ];
}

final class HrLoanType extends Model
{
    protected string $table = 'rateb_hr_loan_types';
    protected bool $tenantScoped = true;
    protected array $fillable = ['company_id', 'name', 'max_amount', 'max_installments', 'status'];
}

final class HrLoan extends Model
{
    protected string $table = 'rateb_hr_loans';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'loan_code', 'employee_id', 'loan_type_id', 'principal',
        'installment_amount', 'installments_count', 'paid_installments', 'start_date', 'status', 'notes',
    ];
}

final class HrPayrollComponent extends Model
{
    protected string $table = 'rateb_hr_payroll_components';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'code', 'name', 'component_type', 'calc_type', 'default_value', 'status',
    ];
}

final class HrPayrollStructure extends Model
{
    protected string $table = 'rateb_hr_payroll_structures';
    protected bool $tenantScoped = true;
    protected array $fillable = ['company_id', 'employee_id', 'component_id', 'value'];
}

final class HrEmployeeRequest extends Model
{
    protected string $table = 'rateb_hr_employee_requests';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'request_no', 'employee_id', 'request_type', 'request_date',
        'status', 'processed_by', 'processed_at', 'notes',
    ];
}

final class HrFleetVehicle extends Model
{
    protected string $table = 'rateb_hr_fleet';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'plate_number', 'brand', 'model', 'model_year',
        'assigned_employee_id', 'status', 'notes',
    ];
}

final class HrDocument extends Model
{
    protected string $table = 'rateb_hr_documents';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'employee_id', 'title', 'doc_type', 'issue_date', 'expiry_date', 'notes',
    ];
}
