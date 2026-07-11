<?php

declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Phase 15A — Recruitment domain models (ONLINE). Soft-delete via deleted_at filtered in services. */

final class RecruitmentAgency extends Model
{
    protected string $table = 'rateb_recruitment_agencies';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'contact_name', 'email', 'phone',
        'country_code', 'status', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class RecruitmentCandidate extends Model
{
    protected string $table = 'rateb_recruitment_candidates';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'agency_id', 'candidate_no', 'full_name', 'full_name_ar',
        'email', 'phone', 'nationality', 'gender', 'date_of_birth', 'national_id', 'job_title_target',
        'source', 'recruiter_user_id', 'workflow_status', 'status', 'notes',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class RecruitmentPassport extends Model
{
    protected string $table = 'rateb_recruitment_passports';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = false;
    protected array $fillable = [
        'public_uuid', 'company_id', 'candidate_id', 'passport_no', 'nationality', 'issue_date',
        'expiry_date', 'issue_place', 'status', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class RecruitmentVisa extends Model
{
    protected string $table = 'rateb_recruitment_visas';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = false;
    protected array $fillable = [
        'public_uuid', 'company_id', 'candidate_id', 'visa_no', 'visa_type', 'destination_country',
        'issue_date', 'expiry_date', 'status', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class RecruitmentMedical extends Model
{
    protected string $table = 'rateb_recruitment_medicals';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = false;
    protected array $fillable = [
        'public_uuid', 'company_id', 'candidate_id', 'clinic_name', 'exam_date', 'result',
        'expiry_date', 'status', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class RecruitmentContract extends Model
{
    protected string $table = 'rateb_recruitment_contracts';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = false;
    protected array $fillable = [
        'public_uuid', 'company_id', 'candidate_id', 'contract_no', 'title', 'start_date', 'end_date',
        'salary', 'currency', 'status', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class RecruitmentInterview extends Model
{
    protected string $table = 'rateb_recruitment_interviews';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'candidate_id', 'interviewer_user_id', 'scheduled_at',
        'location', 'mode', 'result', 'score', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class RecruitmentSkill extends Model
{
    protected string $table = 'rateb_recruitment_skills';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = false;
    protected array $fillable = ['company_id', 'name', 'status', 'deleted_at'];
}

final class RecruitmentLanguage extends Model
{
    protected string $table = 'rateb_recruitment_languages';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = false;
    protected array $fillable = ['company_id', 'name', 'code', 'status', 'deleted_at'];
}

final class RecruitmentExperience extends Model
{
    protected string $table = 'rateb_recruitment_experiences';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = false;
    protected array $fillable = [
        'company_id', 'candidate_id', 'employer_name', 'job_title', 'country_code',
        'start_date', 'end_date', 'is_current', 'description', 'created_by', 'deleted_at',
    ];
}

final class RecruitmentEducation extends Model
{
    protected string $table = 'rateb_recruitment_educations';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = false;
    protected array $fillable = [
        'company_id', 'candidate_id', 'institution', 'degree', 'field_of_study', 'country_code',
        'start_year', 'end_year', 'notes', 'created_by', 'deleted_at',
    ];
}

final class RecruitmentNote extends Model
{
    protected string $table = 'rateb_recruitment_notes';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = false;
    protected array $fillable = [
        'company_id', 'candidate_id', 'body', 'visibility', 'created_by', 'deleted_at',
    ];
}

final class RecruitmentActivity extends Model
{
    protected string $table = 'rateb_recruitment_activities';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = false;
    protected array $fillable = [
        'company_id', 'candidate_id', 'activity_type', 'title', 'body', 'meta_json', 'created_by',
    ];
}

final class RecruitmentStatusHistory extends Model
{
    protected string $table = 'rateb_recruitment_status_history';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = false;
    protected array $fillable = [
        'company_id', 'candidate_id', 'from_status', 'to_status', 'reason', 'created_by',
    ];
}

final class RecruitmentTimeline extends Model
{
    protected string $table = 'rateb_recruitment_timeline';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = false;
    protected array $fillable = [
        'company_id', 'candidate_id', 'event_type', 'title', 'body',
        'related_entity', 'related_id', 'created_by',
    ];
}

final class RecruitmentAssignment extends Model
{
    protected string $table = 'rateb_recruitment_assignments';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = false;
    protected array $fillable = [
        'public_uuid', 'company_id', 'candidate_id', 'assignee_user_id', 'role_label',
        'status', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}
