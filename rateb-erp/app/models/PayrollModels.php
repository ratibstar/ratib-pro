<?php

declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Phase 24A — Enterprise Payroll Platform models (ONLINE). */

final class PayrollSalaryStructure extends Model
{
    protected string $table = 'rateb_payroll_salary_structures';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'description', 'currency_code', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollSalaryComponent extends Model
{
    protected string $table = 'rateb_payroll_salary_components';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'structure_id', 'code', 'name', 'name_ar', 'component_type', 'calc_method', 'amount', 'percent_value', 'earning_type_id', 'deduction_type_id', 'sort_order', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollEarningType extends Model
{
    protected string $table = 'rateb_payroll_earning_types';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'taxable', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollDeductionType extends Model
{
    protected string $table = 'rateb_payroll_deduction_types';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'statutory', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollEmployeeSalary extends Model
{
    protected string $table = 'rateb_payroll_employee_salary';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'hrm_employee_profile_id', 'legacy_employee_id', 'structure_id', 'basic_salary', 'currency_code', 'effective_from', 'effective_to', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollCycle extends Model
{
    protected string $table = 'rateb_payroll_cycles';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'frequency', 'start_day', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollRunPeriod extends Model
{
    protected string $table = 'rateb_payroll_run_periods';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'cycle_id', 'code', 'period_start', 'period_end', 'pay_date', 'legacy_payroll_period_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollBatch extends Model
{
    protected string $table = 'rateb_payroll_batches';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'cycle_id', 'run_period_id', 'code', 'title', 'title_ar', 'period_start', 'period_end', 'pay_date', 'workflow_status', 'status', 'total_gross', 'total_deductions', 'total_net', 'employee_count', 'accounting_post_ref', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollItem extends Model
{
    protected string $table = 'rateb_payroll_items';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'batch_id', 'hrm_employee_profile_id', 'legacy_employee_id', 'employee_salary_id', 'basic_salary', 'gross_amount', 'deduction_amount', 'net_amount', 'attendance_ref', 'leave_ref', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollPayslip extends Model
{
    protected string $table = 'rateb_payroll_payslips';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'batch_id', 'payroll_item_id', 'hrm_employee_profile_id', 'legacy_employee_id', 'payslip_number', 'period_start', 'period_end', 'pay_date', 'gross_amount', 'deduction_amount', 'net_amount', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollOvertime extends Model
{
    protected string $table = 'rateb_payroll_overtime';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'hrm_employee_profile_id', 'legacy_employee_id', 'batch_id', 'code', 'overtime_date', 'hours', 'rate_multiplier', 'amount', 'attendance_ref', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollBonus extends Model
{
    protected string $table = 'rateb_payroll_bonuses';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'hrm_employee_profile_id', 'legacy_employee_id', 'batch_id', 'code', 'title', 'amount', 'bonus_date', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollCommission extends Model
{
    protected string $table = 'rateb_payroll_commissions';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'hrm_employee_profile_id', 'legacy_employee_id', 'batch_id', 'code', 'title', 'amount', 'commission_date', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollLoan extends Model
{
    protected string $table = 'rateb_payroll_loans';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'hrm_employee_profile_id', 'legacy_employee_id', 'code', 'principal_amount', 'outstanding_amount', 'installment_amount', 'installments_total', 'installments_paid', 'start_date', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollLoanInstallment extends Model
{
    protected string $table = 'rateb_payroll_loan_installments';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'loan_id', 'batch_id', 'installment_no', 'due_date', 'amount', 'paid_amount', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollAdvance extends Model
{
    protected string $table = 'rateb_payroll_advances';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'hrm_employee_profile_id', 'legacy_employee_id', 'batch_id', 'code', 'amount', 'advance_date', 'recovery_amount', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollReimbursement extends Model
{
    protected string $table = 'rateb_payroll_reimbursements';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'hrm_employee_profile_id', 'legacy_employee_id', 'batch_id', 'code', 'title', 'amount', 'expense_date', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollSettlement extends Model
{
    protected string $table = 'rateb_payroll_settlements';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'hrm_employee_profile_id', 'legacy_employee_id', 'batch_id', 'code', 'settlement_type', 'amount', 'settlement_date', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollAdjustment extends Model
{
    protected string $table = 'rateb_payroll_adjustments';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'batch_id', 'payroll_item_id', 'hrm_employee_profile_id', 'legacy_employee_id', 'code', 'adjustment_type', 'amount', 'reason', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollNote extends Model
{
    protected string $table = 'rateb_payroll_notes';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'title', 'body', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollComment extends Model
{
    protected string $table = 'rateb_payroll_comments';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'comment_text', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollTimeline extends Model
{
    protected string $table = 'rateb_payroll_timeline';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'event_type', 'title', 'body', 'entity_type', 'entity_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollAttachmentMeta extends Model
{
    protected string $table = 'rateb_payroll_attachments_meta';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'doc_type', 'title', 'file_name', 'mime_type', 'file_size', 'storage_key', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollStatusHistory extends Model
{
    protected string $table = 'rateb_payroll_status_history';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'from_status', 'to_status', 'reason', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollAssignment extends Model
{
    protected string $table = 'rateb_payroll_assignments';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'assignee_user_id', 'role_label', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class PayrollAudit extends Model
{
    protected string $table = 'rateb_payroll_audit';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'action', 'payload_json', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}
