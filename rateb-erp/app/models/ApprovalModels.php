<?php

declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Phase 20A — Enterprise Approval Platform models (ONLINE). Distinct from legacy ApprovalWorkflow → rateb_approval_*. */

final class EapTemplate extends Model
{
    protected string $table = 'rateb_eap_templates';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'module_key', 'description',
        'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EapStage extends Model
{
    protected string $table = 'rateb_eap_stages';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'template_id', 'code', 'name', 'sort_order',
        'approver_role', 'min_approvals', 'sla_hours', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EapRule extends Model
{
    protected string $table = 'rateb_eap_rules';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'template_id', 'code', 'name', 'rule_type',
        'condition_json', 'priority', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EapChain extends Model
{
    protected string $table = 'rateb_eap_chains';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'template_id', 'code', 'name', 'status',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EapChainStage extends Model
{
    protected string $table = 'rateb_eap_chain_stages';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'chain_id', 'stage_id', 'sort_order',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EapRequest extends Model
{
    protected string $table = 'rateb_eap_requests';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'request_no', 'title', 'template_id', 'chain_id',
        'current_stage_id', 'related_module', 'related_type', 'related_id', 'workflow_status', 'priority',
        'amount', 'currency_code', 'submitted_at', 'decided_at', 'requester_user_id', 'status', 'notes',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EapAction extends Model
{
    protected string $table = 'rateb_eap_actions';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'request_id', 'stage_id', 'action_type',
        'actor_user_id', 'comment', 'acted_at', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EapDelegation extends Model
{
    protected string $table = 'rateb_eap_delegations';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'request_id', 'from_user_id', 'to_user_id',
        'starts_at', 'ends_at', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EapEscalation extends Model
{
    protected string $table = 'rateb_eap_escalations';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'request_id', 'stage_id', 'escalate_to_user_id',
        'reason', 'escalated_at', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EapSla extends Model
{
    protected string $table = 'rateb_eap_sla';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'request_id', 'stage_id', 'due_at', 'breached_at',
        'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EapReminder extends Model
{
    protected string $table = 'rateb_eap_reminders';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'request_id', 'remind_at', 'channel', 'status',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EapTimelineEvent extends Model
{
    protected string $table = 'rateb_eap_timeline';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'request_id', 'event_type', 'title', 'body',
        'related_type', 'related_id', 'meta_json', 'created_by',
    ];
}

final class EapComment extends Model
{
    protected string $table = 'rateb_eap_comments';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'request_id', 'body',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EapAudit extends Model
{
    protected string $table = 'rateb_eap_audit';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'request_id', 'event_code', 'message', 'meta_json', 'created_by',
    ];
}

final class EapNotificationMeta extends Model
{
    protected string $table = 'rateb_eap_notification_meta';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'request_id', 'channel', 'recipient_user_id',
        'subject', 'status', 'notification_id', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EapAttachmentMeta extends Model
{
    protected string $table = 'rateb_eap_attachment_meta';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'request_id', 'document_id', 'title', 'doc_type',
        'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EapStatusHistory extends Model
{
    protected string $table = 'rateb_eap_status_history';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'request_id', 'from_status', 'to_status', 'reason', 'created_by',
    ];
}
