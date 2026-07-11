<?php

declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Phase 23A — Enterprise Human Resources (HRMS) Platform models (ONLINE). */

final class HrmDepartment extends Model
{
    protected string $table = 'rateb_hrm_departments';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'parent_id', 'manager_profile_id', 'description', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmPosition extends Model
{
    protected string $table = 'rateb_hrm_positions';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'department_id', 'grade_id', 'description', 'legacy_job_title_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmGrade extends Model
{
    protected string $table = 'rateb_hrm_grades';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'level_rank', 'min_salary', 'max_salary', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmLocation extends Model
{
    protected string $table = 'rateb_hrm_locations';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'address', 'city', 'country_code', 'legacy_workplace_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmOrgUnit extends Model
{
    protected string $table = 'rateb_hrm_org_units';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'parent_id', 'department_id', 'unit_type', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmEmployeeProfile extends Model
{
    protected string $table = 'rateb_hrm_employee_profiles';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'legacy_employee_id', 'first_name', 'last_name', 'first_name_ar', 'last_name_ar', 'email', 'phone', 'department_id', 'position_id', 'grade_id', 'location_id', 'org_unit_id', 'manager_profile_id', 'hire_date', 'termination_date', 'employment_type', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmEmployeeDocumentMeta extends Model
{
    protected string $table = 'rateb_hrm_employee_documents_meta';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'employee_profile_id', 'doc_type', 'title', 'file_name', 'mime_type', 'file_size', 'storage_key', 'issued_at', 'expires_at', 'legacy_document_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmEmployeeContact extends Model
{
    protected string $table = 'rateb_hrm_employee_contacts';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'employee_profile_id', 'contact_type', 'label', 'value', 'is_primary', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmDependent extends Model
{
    protected string $table = 'rateb_hrm_dependents';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'employee_profile_id', 'full_name', 'relationship', 'birth_date', 'national_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmEmergencyContact extends Model
{
    protected string $table = 'rateb_hrm_emergency_contacts';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'employee_profile_id', 'full_name', 'relationship', 'phone', 'email', 'is_primary', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmCertification extends Model
{
    protected string $table = 'rateb_hrm_certifications';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'employee_profile_id', 'code', 'name', 'issuer', 'issued_at', 'expires_at', 'credential_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmLicense extends Model
{
    protected string $table = 'rateb_hrm_licenses';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'employee_profile_id', 'code', 'name', 'license_number', 'issued_at', 'expires_at', 'authority', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmSkill extends Model
{
    protected string $table = 'rateb_hrm_skills';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'employee_profile_id', 'code', 'name', 'name_ar', 'category', 'proficiency', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmLanguage extends Model
{
    protected string $table = 'rateb_hrm_languages';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'employee_profile_id', 'language_code', 'language_name', 'proficiency', 'is_native', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmTraining extends Model
{
    protected string $table = 'rateb_hrm_training';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'title', 'title_ar', 'provider', 'location_id', 'planned_start', 'planned_end', 'hours', 'capacity', 'description', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmTrainingHistory extends Model
{
    protected string $table = 'rateb_hrm_training_history';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'training_id', 'employee_profile_id', 'result_status', 'score', 'completed_at', 'certificate_ref', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmPerformanceReview extends Model
{
    protected string $table = 'rateb_hrm_performance_reviews';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'employee_profile_id', 'reviewer_profile_id', 'period_start', 'period_end', 'overall_score', 'summary', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmGoal extends Model
{
    protected string $table = 'rateb_hrm_goals';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'employee_profile_id', 'review_id', 'title', 'description', 'target_date', 'progress_percent', 'goal_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmCompetency extends Model
{
    protected string $table = 'rateb_hrm_competencies';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'employee_profile_id', 'code', 'name', 'name_ar', 'category', 'level_score', 'description', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmDisciplinaryAction extends Model
{
    protected string $table = 'rateb_hrm_disciplinary_actions';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'employee_profile_id', 'code', 'action_type', 'title', 'action_date', 'severity_date', 'description', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmReward extends Model
{
    protected string $table = 'rateb_hrm_rewards';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'employee_profile_id', 'code', 'reward_type', 'title', 'reward_date', 'amount', 'description', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmTransfer extends Model
{
    protected string $table = 'rateb_hrm_transfers';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'employee_profile_id', 'code', 'from_department_id', 'to_department_id', 'from_position_id', 'to_position_id', 'from_location_id', 'to_location_id', 'effective_date', 'transfer_status', 'reason', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmPromotion extends Model
{
    protected string $table = 'rateb_hrm_promotions';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'employee_profile_id', 'code', 'from_position_id', 'to_position_id', 'from_grade_id', 'to_grade_id', 'effective_date', 'promotion_status', 'reason', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmAssignment extends Model
{
    protected string $table = 'rateb_hrm_assignments';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'assignee_user_id', 'role_label', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmNote extends Model
{
    protected string $table = 'rateb_hrm_notes';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'title', 'body', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmComment extends Model
{
    protected string $table = 'rateb_hrm_comments';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'body', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmTimeline extends Model
{
    protected string $table = 'rateb_hrm_timeline';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'event_type', 'title', 'body', 'meta_json', 'status', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmTag extends Model
{
    protected string $table = 'rateb_hrm_tags';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'color', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmEntityTag extends Model
{
    protected string $table = 'rateb_hrm_entity_tags';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'tag_id', 'entity_type', 'entity_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class HrmStatusHistory extends Model
{
    protected string $table = 'rateb_hrm_status_history';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'from_status', 'to_status', 'reason', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}
