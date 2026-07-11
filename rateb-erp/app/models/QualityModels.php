<?php

declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Phase 25A — Enterprise Quality Management (QMS) Platform models (ONLINE). */

final class QmsProgram extends Model
{
    protected string $table = 'rateb_qms_programs';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'description', 'owner_user_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsPlan extends Model
{
    protected string $table = 'rateb_qms_plans';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'program_id', 'code', 'name', 'name_ar', 'scope_text', 'effective_from', 'effective_to', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsStandard extends Model
{
    protected string $table = 'rateb_qms_standards';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'standard_ref', 'revision', 'description', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsChecklist extends Model
{
    protected string $table = 'rateb_qms_checklists';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'plan_id', 'standard_id', 'code', 'name', 'name_ar', 'checklist_type', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsChecklistItem extends Model
{
    protected string $table = 'rateb_qms_checklist_items';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'checklist_id', 'sort_order', 'item_code', 'item_text', 'is_required', 'pass_criteria', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsInspection extends Model
{
    protected string $table = 'rateb_qms_inspections';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'plan_id', 'checklist_id', 'code', 'title', 'title_ar', 'planned_at', 'started_at', 'completed_at', 'inspector_user_id', 'mfg_quality_check_id', 'eam_inspection_id', 'result_summary', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsResult extends Model
{
    protected string $table = 'rateb_qms_results';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'inspection_id', 'checklist_item_id', 'result_value', 'result_status', 'measured_value', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsDefect extends Model
{
    protected string $table = 'rateb_qms_defects';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'inspection_id', 'code', 'title', 'severity', 'defect_type', 'quantity', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsNonconformity extends Model
{
    protected string $table = 'rateb_qms_nonconformities';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'inspection_id', 'defect_id', 'code', 'title', 'description', 'severity', 'detected_at', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsRootCause extends Model
{
    protected string $table = 'rateb_qms_root_causes';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'nonconformity_id', 'code', 'title', 'analysis_method', 'description', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsCorrectiveAction extends Model
{
    protected string $table = 'rateb_qms_corrective_actions';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'nonconformity_id', 'root_cause_id', 'code', 'title', 'description', 'assignee_user_id', 'due_date', 'verified_at', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsPreventiveAction extends Model
{
    protected string $table = 'rateb_qms_preventive_actions';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'nonconformity_id', 'code', 'title', 'description', 'assignee_user_id', 'due_date', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsAudit extends Model
{
    protected string $table = 'rateb_qms_audits';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'program_id', 'standard_id', 'code', 'title', 'audit_type', 'planned_start', 'planned_end', 'lead_auditor_user_id', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsAuditFinding extends Model
{
    protected string $table = 'rateb_qms_audit_findings';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'audit_id', 'code', 'title', 'finding_type', 'description', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsComplaint extends Model
{
    protected string $table = 'rateb_qms_complaints';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'title', 'complainant', 'complaint_date', 'description', 'severity', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsSupplierQuality extends Model
{
    protected string $table = 'rateb_qms_supplier_quality';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'supplier_name', 'legacy_supplier_id', 'eproc_profile_id', 'score', 'rating', 'last_review_date', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsTraining extends Model
{
    protected string $table = 'rateb_qms_training';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'title', 'training_date', 'audience', 'hrm_training_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsDocumentMeta extends Model
{
    protected string $table = 'rateb_qms_documents_meta';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'doc_type', 'title', 'file_name', 'mime_type', 'file_size', 'storage_key', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsComment extends Model
{
    protected string $table = 'rateb_qms_comments';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'comment_text', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsAssignment extends Model
{
    protected string $table = 'rateb_qms_assignments';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'assignee_user_id', 'role_label', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsTimeline extends Model
{
    protected string $table = 'rateb_qms_timeline';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'event_type', 'title', 'body', 'entity_type', 'entity_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsStatusHistory extends Model
{
    protected string $table = 'rateb_qms_status_history';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'from_status', 'to_status', 'reason', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class QmsTag extends Model
{
    protected string $table = 'rateb_qms_tags';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'color', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}
