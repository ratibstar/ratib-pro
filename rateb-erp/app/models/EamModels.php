<?php

declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Phase 19A — Enterprise EAM models (ONLINE). Distinct from legacy Asset → rateb_assets. */

final class EamAssetCategory extends Model
{
    protected string $table = 'rateb_eam_asset_categories';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'parent_id', 'status',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamManufacturer extends Model
{
    protected string $table = 'rateb_eam_manufacturers';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'status',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamVendor extends Model
{
    protected string $table = 'rateb_eam_vendors';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'supplier_id',
        'phone', 'email', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamLocation extends Model
{
    protected string $table = 'rateb_eam_locations';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'parent_id', 'status',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamAssetModel extends Model
{
    protected string $table = 'rateb_eam_asset_models';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'manufacturer_id', 'category_id', 'code', 'name',
        'name_ar', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamAsset extends Model
{
    protected string $table = 'rateb_eam_assets';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'asset_no', 'name', 'name_ar', 'category_id',
        'model_id', 'manufacturer_id', 'vendor_id', 'location_id', 'legacy_asset_id', 'serial_no',
        'barcode', 'workflow_status', 'priority', 'purchase_date', 'purchase_cost', 'current_value',
        'currency_code', 'useful_life_months', 'salvage_value', 'depreciation_method',
        'placed_in_service_date', 'owner_user_id', 'custodian_user_id', 'status', 'notes',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamAssetAssignment extends Model
{
    protected string $table = 'rateb_eam_asset_assignments';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'asset_id', 'assignee_user_id', 'role_label',
        'assigned_at', 'released_at', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamAssetTransfer extends Model
{
    protected string $table = 'rateb_eam_asset_transfers';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'asset_id', 'from_location_id', 'to_location_id',
        'from_branch_id', 'to_branch_id', 'transfer_at', 'status', 'notes', 'version',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamMaintenancePlan extends Model
{
    protected string $table = 'rateb_eam_maintenance_plans';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'asset_id', 'code', 'name', 'plan_type',
        'frequency_days', 'next_due_date', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamMaintenanceRequest extends Model
{
    protected string $table = 'rateb_eam_maintenance_requests';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'asset_id', 'request_no', 'title', 'description',
        'request_type', 'workflow_status', 'priority', 'requested_by', 'scheduled_at', 'completed_at',
        'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamWorkOrder extends Model
{
    protected string $table = 'rateb_eam_work_orders';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'asset_id', 'request_id', 'plan_id', 'work_order_no',
        'title', 'description', 'work_type', 'workflow_status', 'assignee_user_id', 'scheduled_start',
        'scheduled_end', 'started_at', 'completed_at', 'labor_hours', 'status', 'notes', 'version',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamChecklist extends Model
{
    protected string $table = 'rateb_eam_checklists';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'checklist_type', 'status',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamChecklistItem extends Model
{
    protected string $table = 'rateb_eam_checklist_items';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'checklist_id', 'sort_order', 'item_text', 'is_required',
        'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamInspection extends Model
{
    protected string $table = 'rateb_eam_inspections';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'asset_id', 'checklist_id', 'work_order_id',
        'inspection_no', 'inspected_at', 'result', 'inspector_user_id', 'notes', 'status',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamMeterReading extends Model
{
    protected string $table = 'rateb_eam_meter_readings';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'asset_id', 'meter_name', 'reading_value',
        'reading_at', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamWarranty extends Model
{
    protected string $table = 'rateb_eam_warranties';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'asset_id', 'provider_name', 'policy_no',
        'start_date', 'end_date', 'coverage_notes', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamInsurance extends Model
{
    protected string $table = 'rateb_eam_insurance';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'asset_id', 'insurer_name', 'policy_no',
        'start_date', 'end_date', 'coverage_amount', 'currency_code', 'status', 'notes',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamSparePartRef extends Model
{
    protected string $table = 'rateb_eam_spare_part_refs';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'asset_id', 'part_sku', 'part_name',
        'inventory_item_id', 'qty_on_hand', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamPartsConsumption extends Model
{
    protected string $table = 'rateb_eam_parts_consumption';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'work_order_id', 'spare_part_id', 'part_name',
        'quantity', 'unit_cost', 'consumed_at', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamDocumentMeta extends Model
{
    protected string $table = 'rateb_eam_document_meta';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'related_type', 'related_id', 'document_id',
        'title', 'doc_type', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamTimelineEvent extends Model
{
    protected string $table = 'rateb_eam_timeline';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'asset_id', 'event_type', 'title', 'body',
        'related_type', 'related_id', 'meta_json', 'created_by',
    ];
}

final class EamActivity extends Model
{
    protected string $table = 'rateb_eam_activities';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'asset_id', 'activity_type', 'subject', 'body',
        'activity_at', 'owner_user_id', 'status', 'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamComment extends Model
{
    protected string $table = 'rateb_eam_comments';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'related_type', 'related_id', 'body',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamTag extends Model
{
    protected string $table = 'rateb_eam_tags';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'color', 'status',
        'version', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class EamStatusHistory extends Model
{
    protected string $table = 'rateb_eam_status_history';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'entity_type', 'entity_id', 'asset_id', 'from_status', 'to_status',
        'reason', 'created_by',
    ];
}
