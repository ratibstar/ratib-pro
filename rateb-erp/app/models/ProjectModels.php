<?php

declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Phase 18A — Enterprise Projects platform models (ONLINE foundation). */

final class ProjectRole extends Model
{
    protected string $table = 'rateb_project_roles';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'status',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectTag extends Model
{
    protected string $table = 'rateb_project_tags';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'color', 'status',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class Project extends Model
{
    protected string $table = 'rateb_projects';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'project_no', 'name', 'name_ar', 'description',
        'customer_id', 'owner_user_id', 'workflow_status', 'priority', 'start_date', 'end_date',
        'planned_start', 'planned_end', 'percent_complete', 'currency_code', 'budget_amount',
        'cost_center_id', 'version', 'status', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectMember extends Model
{
    protected string $table = 'rateb_project_members';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'project_id', 'user_id', 'role_id', 'role_label',
        'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectPhase extends Model
{
    protected string $table = 'rateb_project_phases';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'project_id', 'code', 'name', 'name_ar', 'sort_order',
        'start_date', 'end_date', 'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectMilestone extends Model
{
    protected string $table = 'rateb_project_milestones';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'project_id', 'phase_id', 'name', 'name_ar',
        'due_date', 'completed_at', 'status', 'sort_order', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectTask extends Model
{
    protected string $table = 'rateb_project_tasks';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'project_id', 'phase_id', 'milestone_id', 'parent_task_id',
        'task_no', 'title', 'description', 'workflow_status', 'priority', 'assignee_user_id',
        'start_date', 'due_date', 'estimated_hours', 'actual_hours', 'percent_complete', 'sort_order',
        'version', 'status', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectActivity extends Model
{
    protected string $table = 'rateb_project_activities';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'project_id', 'task_id', 'activity_type', 'subject',
        'body', 'activity_at', 'owner_user_id', 'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectTimelineEvent extends Model
{
    protected string $table = 'rateb_project_timeline';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'project_id', 'task_id', 'event_type', 'title',
        'body', 'related_type', 'related_id', 'meta_json', 'created_by',
    ];
}

final class ProjectIssue extends Model
{
    protected string $table = 'rateb_project_issues';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'project_id', 'task_id', 'issue_no', 'title',
        'description', 'severity', 'status', 'assignee_user_id', 'due_date', 'resolved_at',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectRisk extends Model
{
    protected string $table = 'rateb_project_risks';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'project_id', 'risk_no', 'title', 'description',
        'probability', 'impact', 'status', 'owner_user_id', 'mitigation_plan',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectTimesheet extends Model
{
    protected string $table = 'rateb_project_timesheets';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'project_id', 'task_id', 'user_id', 'work_date',
        'hours', 'description', 'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectResource extends Model
{
    protected string $table = 'rateb_project_resources';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'project_id', 'resource_type', 'name', 'user_id',
        'allocation_percent', 'start_date', 'end_date', 'cost_rate', 'currency_code', 'status',
        'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectBudget extends Model
{
    protected string $table = 'rateb_project_budgets';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'project_id', 'category', 'planned_amount',
        'currency_code', 'notes', 'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectCost extends Model
{
    protected string $table = 'rateb_project_costs';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'project_id', 'budget_id', 'cost_date', 'amount',
        'currency_code', 'category', 'description', 'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectComment extends Model
{
    protected string $table = 'rateb_project_comments';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'project_id', 'task_id', 'body',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectAssignment extends Model
{
    protected string $table = 'rateb_project_assignments';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'related_type', 'related_id', 'assignee_user_id',
        'role_label', 'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class ProjectStatusHistory extends Model
{
    protected string $table = 'rateb_project_status_history';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'project_id', 'task_id', 'entity_type', 'from_status', 'to_status',
        'reason', 'created_by',
    ];
}
