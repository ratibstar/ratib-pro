<?php

declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Phase 21A — Enterprise Procurement Platform models (ONLINE). Distinct from legacy ProcurementService / rateb_purchase_*. */

final class EprocSupplierCategory extends Model
{
    protected string $table = 'rateb_eproc_supplier_categories';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'parent_id',
        'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocSupplierProfile extends Model
{
    protected string $table = 'rateb_eproc_supplier_profiles';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'legacy_supplier_id', 'category_id', 'code', 'name', 'name_ar',
        'legal_name', 'tax_number', 'country_code', 'city', 'email', 'phone', 'website', 'risk_level',
        'qualification_status', 'workflow_status', 'status', 'notes',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocSupplierContact extends Model
{
    protected string $table = 'rateb_eproc_supplier_contacts';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'profile_id', 'name', 'title', 'email', 'phone',
        'is_primary', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocSupplierCertification extends Model
{
    protected string $table = 'rateb_eproc_supplier_certifications';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'profile_id', 'cert_type', 'cert_number',
        'issued_at', 'expires_at', 'issuer', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocSupplierSla extends Model
{
    protected string $table = 'rateb_eproc_supplier_sla';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'profile_id', 'code', 'name', 'metric_key',
        'target_value', 'unit', 'period_days', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocSupplierScorecard extends Model
{
    protected string $table = 'rateb_eproc_supplier_scorecards';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'profile_id', 'period_label',
        'quality_score', 'delivery_score', 'price_score', 'service_score', 'overall_score',
        'notes', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocSupplierPerformance extends Model
{
    protected string $table = 'rateb_eproc_supplier_performance';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'profile_id', 'metric_key', 'metric_value',
        'period_start', 'period_end', 'notes', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocSupplierRisk extends Model
{
    protected string $table = 'rateb_eproc_supplier_risk';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'profile_id', 'risk_code', 'risk_level',
        'title', 'description', 'mitigation', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocSupplierBlacklist extends Model
{
    protected string $table = 'rateb_eproc_supplier_blacklist';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'profile_id', 'reason', 'effective_from',
        'effective_to', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocSupplierQualification extends Model
{
    protected string $table = 'rateb_eproc_supplier_qualification';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'profile_id', 'code', 'title', 'checklist_json',
        'workflow_status', 'decided_at', 'status', 'notes',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocDocumentMeta extends Model
{
    protected string $table = 'rateb_eproc_document_meta';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'document_id',
        'file_name', 'mime_type', 'title', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocPortalInvite extends Model
{
    protected string $table = 'rateb_eproc_portal_invites';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'profile_id', 'email', 'invite_token',
        'expires_at', 'accepted_at', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocCollaboration extends Model
{
    protected string $table = 'rateb_eproc_collaboration';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'profile_id', 'related_type', 'related_id',
        'subject', 'body', 'workflow_status', 'status',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocRfqTemplate extends Model
{
    protected string $table = 'rateb_eproc_rfq_templates';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'body_template',
        'default_days', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocTender extends Model
{
    protected string $table = 'rateb_eproc_tenders';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'legacy_tender_id', 'code', 'title', 'description',
        'opens_at', 'closes_at', 'budget_amount', 'currency_code', 'workflow_status', 'status',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocTenderBid extends Model
{
    protected string $table = 'rateb_eproc_tender_bids';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'tender_id', 'profile_id', 'bid_amount',
        'currency_code', 'score', 'notes', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocBidComparison extends Model
{
    protected string $table = 'rateb_eproc_bid_comparisons';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'tender_id', 'title', 'comparison_json',
        'recommended_bid_id', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocContract extends Model
{
    protected string $table = 'rateb_eproc_contracts';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'legacy_contract_id', 'profile_id', 'code', 'title',
        'starts_at', 'ends_at', 'value_amount', 'currency_code', 'workflow_status', 'status', 'notes',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocCalendarEvent extends Model
{
    protected string $table = 'rateb_eproc_calendar_events';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'event_type', 'title', 'starts_at', 'ends_at',
        'related_type', 'related_id', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocSpendSnapshot extends Model
{
    protected string $table = 'rateb_eproc_spend_snapshots';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'period_label', 'category_key', 'amount',
        'currency_code', 'meta_json', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocApprovalLink extends Model
{
    protected string $table = 'rateb_eproc_approval_links';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'eap_request_id',
        'legacy_instance_id', 'link_status', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocTimelineEvent extends Model
{
    protected string $table = 'rateb_eproc_timeline';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'event_type',
        'message', 'meta_json', 'created_by',
    ];
}

final class EprocAudit extends Model
{
    protected string $table = 'rateb_eproc_audit';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'action',
        'message', 'meta_json', 'created_by',
    ];
}

final class EprocAssignment extends Model
{
    protected string $table = 'rateb_eproc_assignments';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'assignee_user_id',
        'role_label', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocComment extends Model
{
    protected string $table = 'rateb_eproc_comments';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'body',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocTag extends Model
{
    protected string $table = 'rateb_eproc_tags';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'color', 'status',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocEntityTag extends Model
{
    protected string $table = 'rateb_eproc_entity_tags';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'tag_id', 'entity_type', 'entity_id',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EprocStatusHistory extends Model
{
    protected string $table = 'rateb_eproc_status_history';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id',
        'from_status', 'to_status', 'reason', 'created_by',
    ];
}
