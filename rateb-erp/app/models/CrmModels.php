<?php

declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Phase 17A — Enterprise CRM platform models (ONLINE foundation). */

final class CrmLeadSource extends Model
{
    protected string $table = 'rateb_crm_lead_sources';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'status',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmTag extends Model
{
    protected string $table = 'rateb_crm_tags';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'color', 'status',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmPipeline extends Model
{
    protected string $table = 'rateb_crm_pipelines';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'is_default', 'status',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmPipelineStage extends Model
{
    protected string $table = 'rateb_crm_pipeline_stages';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'pipeline_id', 'code', 'name', 'name_ar', 'sort_order',
        'probability_percent', 'is_won', 'is_lost', 'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmCompany extends Model
{
    protected string $table = 'rateb_crm_companies';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'customer_id', 'code', 'name', 'name_ar',
        'industry', 'website', 'phone', 'email', 'city', 'country_code', 'status', 'notes',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmContact extends Model
{
    protected string $table = 'rateb_crm_contacts';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'crm_company_id', 'customer_id', 'full_name',
        'full_name_ar', 'job_title', 'email', 'phone', 'mobile', 'is_primary', 'status', 'notes',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmLead extends Model
{
    protected string $table = 'rateb_crm_leads';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'lead_no', 'title', 'contact_name', 'email', 'phone',
        'crm_company_id', 'contact_id', 'customer_id', 'source_id', 'owner_user_id', 'workflow_status',
        'estimated_value', 'currency_code', 'expected_close_date', 'priority', 'status', 'notes',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmOpportunity extends Model
{
    protected string $table = 'rateb_crm_opportunities';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'opportunity_no', 'name', 'name_ar', 'lead_id',
        'crm_company_id', 'contact_id', 'customer_id', 'pipeline_id', 'stage_id', 'owner_user_id',
        'amount', 'currency_code', 'probability_percent', 'expected_close_date', 'workflow_status',
        'loss_reason_id', 'loss_notes',
        'status', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmCampaign extends Model
{
    protected string $table = 'rateb_crm_campaigns';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'campaign_type',
        'start_date', 'end_date', 'budget', 'status', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmActivity extends Model
{
    protected string $table = 'rateb_crm_activities';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'activity_type', 'subject', 'body', 'related_type',
        'related_id', 'lead_id', 'opportunity_id', 'contact_id', 'crm_company_id', 'customer_id',
        'owner_user_id', 'activity_at', 'due_at', 'reminder_at', 'priority', 'status',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmMeeting extends Model
{
    protected string $table = 'rateb_crm_meetings';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'subject', 'location', 'starts_at', 'ends_at',
        'lead_id', 'opportunity_id', 'contact_id', 'crm_company_id', 'customer_id', 'owner_user_id',
        'status', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmCall extends Model
{
    protected string $table = 'rateb_crm_calls';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'subject', 'direction', 'called_at', 'duration_sec',
        'phone', 'lead_id', 'opportunity_id', 'contact_id', 'crm_company_id', 'customer_id',
        'owner_user_id', 'outcome', 'status', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmTask extends Model
{
    protected string $table = 'rateb_crm_tasks';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'subject', 'due_at', 'priority', 'lead_id',
        'opportunity_id', 'contact_id', 'crm_company_id', 'customer_id', 'owner_user_id', 'reminder_at',
        'status', 'completed_at', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmNote extends Model
{
    protected string $table = 'rateb_crm_notes';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'related_type', 'related_id', 'lead_id',
        'opportunity_id', 'contact_id', 'crm_company_id', 'customer_id', 'body',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmTimelineEvent extends Model
{
    protected string $table = 'rateb_crm_timeline';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'branch_id', 'event_type', 'title', 'body', 'related_type', 'related_id',
        'lead_id', 'opportunity_id', 'contact_id', 'crm_company_id', 'customer_id', 'created_by',
    ];
}

final class CrmAssignment extends Model
{
    protected string $table = 'rateb_crm_assignments';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'related_type', 'related_id', 'assignee_user_id',
        'role_label', 'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmEntityTag extends Model
{
    protected string $table = 'rateb_crm_entity_tags';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'tag_id', 'related_type', 'related_id', 'created_by',
    ];
}

final class CrmStatusHistory extends Model
{
    protected string $table = 'rateb_crm_status_history';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'lead_id', 'from_status', 'to_status', 'reason', 'created_by',
    ];
}

/** Phase 1 — Sales quotations (linked to leads / opportunities / customers). */
final class CrmQuotation extends Model
{
    protected string $table = 'rateb_crm_quotations';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'quotation_no', 'title', 'lead_id',
        'opportunity_id', 'customer_id', 'crm_company_id', 'contact_id', 'owner_user_id',
        'status', 'currency_code', 'subtotal', 'tax_amount', 'discount_amount', 'total_amount',
        'valid_until', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class CrmQuotationLine extends Model
{
    protected string $table = 'rateb_crm_quotation_lines';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'quotation_id', 'line_no', 'item_name', 'description',
        'quantity', 'unit_price', 'tax_rate', 'line_subtotal', 'line_tax', 'line_total',
        'sort_order', 'created_by', 'updated_by', 'deleted_at',
    ];
}

/** Phase 2 — Multi-entity status history (quotations, etc.). */
final class CrmEntityStatusHistory extends Model
{
    protected string $table = 'rateb_crm_entity_status_history';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'entity_type', 'entity_id', 'from_status', 'to_status', 'reason', 'created_by',
    ];
}

/** Phase 2 — Formal conversion audit trail. */
final class CrmConversion extends Model
{
    protected string $table = 'rateb_crm_conversions';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'public_uuid', 'company_id', 'conversion_type', 'from_type', 'from_id',
        'to_type', 'to_id', 'meta_json', 'created_by',
    ];
}

/** Phase 3 — Loss reason catalog. */
final class CrmLossReason extends Model
{
    protected string $table = 'rateb_crm_loss_reasons';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'code', 'name', 'name_ar', 'sort_order',
        'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

/** Phase 3 — Opportunity won/lost outcome snapshots. */
final class CrmOpportunityOutcome extends Model
{
    protected string $table = 'rateb_crm_opportunity_outcomes';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'opportunity_id', 'outcome', 'loss_reason_id', 'amount',
        'probability_percent', 'expected_revenue', 'notes', 'created_by',
    ];
}

/** Phase 3 — Activity/task reminder side records. */
final class CrmActivityReminder extends Model
{
    protected string $table = 'rateb_crm_activity_reminders';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'activity_id', 'task_id', 'owner_user_id', 'due_at',
        'reminder_at', 'priority', 'reminded_at', 'status', 'created_by',
    ];
}

/** Phase 3 — Automation event log. */
final class CrmAutomationLog extends Model
{
    protected string $table = 'rateb_crm_automation_log';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'event_type', 'entity_type', 'entity_id', 'user_id', 'payload_json',
    ];
}
