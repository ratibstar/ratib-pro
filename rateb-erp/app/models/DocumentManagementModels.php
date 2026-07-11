<?php

declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Phase 26A — Enterprise Document Management (DMS) Platform models (ONLINE). */

final class DmsRepository extends Model
{
    protected string $table = 'rateb_dms_repositories';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'description', 'storage_driver', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsFolder extends Model
{
    protected string $table = 'rateb_dms_folders';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'repository_id', 'parent_folder_id', 'code', 'name', 'name_ar', 'path_text', 'sort_order', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsDocument extends Model
{
    protected string $table = 'rateb_dms_documents';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'repository_id', 'folder_id', 'category_id', 'code', 'title', 'title_ar', 'document_type', 'mime_type', 'file_extension', 'current_version_no', 'workflow_status', 'status', 'checked_out_by', 'checked_out_at', 'published_at', 'legacy_document_id', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsVersion extends Model
{
    protected string $table = 'rateb_dms_versions';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'document_id', 'version_no', 'storage_path', 'storage_driver', 'file_size', 'checksum_sha256', 'change_summary', 'is_current', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsDocumentMetadata extends Model
{
    protected string $table = 'rateb_dms_document_metadata';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'document_id', 'meta_key', 'meta_value', 'value_type', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsCheckout extends Model
{
    protected string $table = 'rateb_dms_checkouts';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'document_id', 'user_id', 'checked_out_at', 'due_at', 'checked_in_at', 'checkout_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsShare extends Model
{
    protected string $table = 'rateb_dms_shares';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'document_id', 'share_token', 'shared_with_user_id', 'shared_with_email', 'permission_level', 'expires_at', 'share_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsPermission extends Model
{
    protected string $table = 'rateb_dms_permissions';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'resource_type', 'resource_id', 'grantee_type', 'grantee_id', 'permission_slug', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsRetentionPolicy extends Model
{
    protected string $table = 'rateb_dms_retention_policies';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'retention_days', 'action_on_expiry', 'applies_to', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsRetentionJob extends Model
{
    protected string $table = 'rateb_dms_retention_jobs';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'policy_id', 'document_id', 'job_status', 'scheduled_at', 'completed_at', 'result_summary', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsLegalHold extends Model
{
    protected string $table = 'rateb_dms_legal_holds';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'document_id', 'folder_id', 'code', 'title', 'hold_reason', 'hold_status', 'effective_from', 'effective_to', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsCategory extends Model
{
    protected string $table = 'rateb_dms_categories';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'color', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsTag extends Model
{
    protected string $table = 'rateb_dms_tags';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'color', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsDocumentLink extends Model
{
    protected string $table = 'rateb_dms_document_links';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'document_id', 'linked_entity_type', 'linked_entity_id', 'link_role', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsDocumentRelation extends Model
{
    protected string $table = 'rateb_dms_document_relations';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'source_document_id', 'target_document_id', 'relation_type', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsApprovalMeta extends Model
{
    protected string $table = 'rateb_dms_approvals_meta';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'document_id', 'approval_stage', 'approver_user_id', 'approval_status', 'decided_at', 'legacy_approval_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsSignatureRequest extends Model
{
    protected string $table = 'rateb_dms_signature_requests';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'document_id', 'version_id', 'request_code', 'signer_user_id', 'signer_email', 'request_status', 'requested_at', 'completed_at', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsSignatureEvent extends Model
{
    protected string $table = 'rateb_dms_signature_events';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'signature_request_id', 'event_type', 'event_at', 'ip_address', 'user_agent', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsAuditLog extends Model
{
    protected string $table = 'rateb_dms_audit_logs';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'document_id', 'action_type', 'actor_user_id', 'resource_type', 'resource_id', 'detail_text', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsTimeline extends Model
{
    protected string $table = 'rateb_dms_timeline';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'event_type', 'title', 'body', 'entity_type', 'entity_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsComment extends Model
{
    protected string $table = 'rateb_dms_comments';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'document_id', 'comment_text', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsFavorite extends Model
{
    protected string $table = 'rateb_dms_favorites';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'user_id', 'document_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsRecentItem extends Model
{
    protected string $table = 'rateb_dms_recent_items';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'user_id', 'document_id', 'accessed_at', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsSearchIndex extends Model
{
    protected string $table = 'rateb_dms_search_index';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'document_id', 'title_index', 'content_snippet', 'tag_text', 'category_text', 'indexed_at', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class DmsStatusHistory extends Model
{
    protected string $table = 'rateb_dms_status_history';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'from_status', 'to_status', 'reason', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}
