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
    protected array $fillable = [
        'company_id', 'employee_code', 'name', 'email', 'phone', 'national_id',
        'department_id', 'job_title', 'hire_date', 'salary_base', 'user_id', 'status', 'notes',
    ];
}

final class AttendanceRecord extends Model
{
    protected string $table = 'rateb_attendance_records';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'employee_id', 'attendance_date', 'check_in', 'check_out', 'status', 'notes',
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
    protected array $fillable = [
        'company_id', 'employee_id', 'leave_type_id', 'start_date', 'end_date',
        'days', 'reason', 'status', 'approved_by', 'approved_at',
    ];
}

final class PayrollPeriod extends Model
{
    protected string $table = 'rateb_payroll_periods';
    protected bool $tenantScoped = true;
    protected array $fillable = ['company_id', 'period_year', 'period_month', 'status', 'notes'];
}

final class PayrollLine extends Model
{
    protected string $table = 'rateb_payroll_lines';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'period_id', 'employee_id', 'basic_salary',
        'allowances', 'deductions', 'net_salary', 'notes',
    ];
}
