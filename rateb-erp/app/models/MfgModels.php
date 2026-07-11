<?php

declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Phase 22A — Enterprise Manufacturing (MRP) Platform models (ONLINE). */

final class MfgProduct extends Model
{
    protected string $table = 'rateb_mfg_products';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'description', 'inventory_item_id', 'uom', 'product_type', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgProductVariant extends Model
{
    protected string $table = 'rateb_mfg_product_variants';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'product_id', 'code', 'name', 'sku', 'inventory_item_id', 'attributes_json', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgBom extends Model
{
    protected string $table = 'rateb_mfg_boms';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'product_id', 'variant_id', 'description', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgBomVersion extends Model
{
    protected string $table = 'rateb_mfg_bom_versions';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'bom_id', 'version_label', 'is_current', 'effective_from', 'effective_to', 'scrap_percent', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgBomLine extends Model
{
    protected string $table = 'rateb_mfg_bom_lines';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'bom_version_id', 'line_no', 'component_product_id', 'component_variant_id', 'inventory_item_id', 'component_code', 'component_name', 'qty_per', 'uom', 'scrap_percent', 'is_optional', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgWorkCenter extends Model
{
    protected string $table = 'rateb_mfg_work_centers';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'description', 'capacity_hours_day', 'cost_per_hour', 'warehouse_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgMachine extends Model
{
    protected string $table = 'rateb_mfg_machines';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'work_center_id', 'code', 'name', 'eam_asset_id', 'capacity_hours_day', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgRouting extends Model
{
    protected string $table = 'rateb_mfg_routings';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'product_id', 'bom_id', 'description', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgRoutingOperation extends Model
{
    protected string $table = 'rateb_mfg_routing_operations';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'routing_id', 'seq_no', 'code', 'name', 'work_center_id', 'machine_id', 'setup_minutes', 'run_minutes_per_unit', 'queue_minutes', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgProductionOrder extends Model
{
    protected string $table = 'rateb_mfg_production_orders';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'title', 'product_id', 'variant_id', 'bom_id', 'bom_version_id', 'routing_id', 'qty_planned', 'qty_completed', 'qty_scrap', 'uom', 'warehouse_id', 'project_id', 'planned_start', 'planned_end', 'actual_start', 'actual_end', 'priority', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgWorkOrder extends Model
{
    protected string $table = 'rateb_mfg_work_orders';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'production_order_id', 'routing_operation_id', 'code', 'title', 'work_center_id', 'machine_id', 'seq_no', 'qty_planned', 'qty_completed', 'planned_start', 'planned_end', 'actual_start', 'actual_end', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgCapacityPlan extends Model
{
    protected string $table = 'rateb_mfg_capacity_plans';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'work_center_id', 'plan_date', 'available_hours', 'booked_hours', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgProductionCalendar extends Model
{
    protected string $table = 'rateb_mfg_production_calendar';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'title', 'event_type', 'event_date', 'start_time', 'end_time', 'work_center_id', 'is_holiday', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgSchedule extends Model
{
    protected string $table = 'rateb_mfg_schedules';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'production_order_id', 'work_order_id', 'work_center_id', 'scheduled_start', 'scheduled_end', 'schedule_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgMaterialReservation extends Model
{
    protected string $table = 'rateb_mfg_material_reservations';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'production_order_id', 'bom_line_id', 'inventory_item_id', 'component_name', 'qty_reserved', 'warehouse_id', 'reservation_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgMaterialConsumption extends Model
{
    protected string $table = 'rateb_mfg_material_consumptions';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'production_order_id', 'work_order_id', 'reservation_id', 'inventory_item_id', 'component_name', 'qty_consumed', 'uom', 'warehouse_id', 'batch_code', 'serial_code', 'consumed_at', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgFinishedGoodsReceipt extends Model
{
    protected string $table = 'rateb_mfg_finished_goods_receipts';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'production_order_id', 'product_id', 'variant_id', 'inventory_item_id', 'qty_received', 'uom', 'warehouse_id', 'batch_code', 'serial_code', 'received_at', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgScrapRecord extends Model
{
    protected string $table = 'rateb_mfg_scrap_records';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'production_order_id', 'work_order_id', 'qty_scrap', 'uom', 'reason_code', 'reason', 'scrap_at', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgQualityCheck extends Model
{
    protected string $table = 'rateb_mfg_quality_checks';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'production_order_id', 'work_order_id', 'code', 'title', 'check_type', 'result_status', 'checklist_json', 'checked_at', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgProductionCost extends Model
{
    protected string $table = 'rateb_mfg_production_costs';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'production_order_id', 'cost_type', 'amount', 'currency_code', 'cost_center_id', 'accounting_ref', 'cost_date', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgTimelineEvent extends Model
{
    protected string $table = 'rateb_mfg_timeline';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'event_type', 'title', 'body', 'meta_json', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgAssignment extends Model
{
    protected string $table = 'rateb_mfg_assignments';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'assignee_user_id', 'role_label', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgComment extends Model
{
    protected string $table = 'rateb_mfg_comments';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'body', 'status', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgAttachmentMeta extends Model
{
    protected string $table = 'rateb_mfg_attachments_meta';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'file_name', 'mime_type', 'file_size', 'storage_key', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgTag extends Model
{
    protected string $table = 'rateb_mfg_tags';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'color', 'status', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgEntityTag extends Model
{
    protected string $table = 'rateb_mfg_entity_tags';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'tag_id', 'entity_type', 'entity_id', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class MfgStatusHistory extends Model
{
    protected string $table = 'rateb_mfg_status_history';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'from_status', 'to_status', 'reason', 'version', 'created_by', 'updated_by', 'deleted_at'];
}
