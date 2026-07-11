<?php

declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

/** Phase 27A — Enterprise Business Intelligence (BI) Platform models (ONLINE). */

final class BiDashboard extends Model
{
    protected string $table = 'rateb_bi_dashboards';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'description', 'dashboard_type', 'owner_user_id', 'layout_json', 'workflow_status', 'status', 'published_at', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiWidget extends Model
{
    protected string $table = 'rateb_bi_widgets';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'dashboard_id', 'code', 'title', 'title_ar', 'widget_type', 'data_source', 'config_json', 'sort_order', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiKpi extends Model
{
    protected string $table = 'rateb_bi_kpis';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'metric_key', 'unit', 'target_value', 'direction', 'source_module', 'formula_text', 'workflow_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiKpiSnapshot extends Model
{
    protected string $table = 'rateb_bi_kpi_snapshots';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'kpi_id', 'snapshot_at', 'metric_value', 'period_key', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiReport extends Model
{
    protected string $table = 'rateb_bi_reports';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'report_type', 'source_module', 'query_meta_json', 'filters_json', 'workflow_status', 'status', 'published_at', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiReportRun extends Model
{
    protected string $table = 'rateb_bi_report_runs';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'report_id', 'run_status', 'started_at', 'completed_at', 'row_count', 'result_summary', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiDataset extends Model
{
    protected string $table = 'rateb_bi_datasets';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'source_module', 'entity_hint', 'refresh_mode', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiDatasetLink extends Model
{
    protected string $table = 'rateb_bi_dataset_links';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'dataset_id', 'linked_module', 'linked_entity_type', 'linked_entity_id', 'link_role', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiDrilldown extends Model
{
    protected string $table = 'rateb_bi_drilldowns';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'report_id', 'parent_level', 'child_level', 'config_json', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiTrend extends Model
{
    protected string $table = 'rateb_bi_trends';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'kpi_id', 'code', 'name', 'period_grain', 'series_json', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiForecast extends Model
{
    protected string $table = 'rateb_bi_forecasts';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'kpi_id', 'code', 'name', 'horizon_periods', 'method_hint', 'forecast_json', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiAlert extends Model
{
    protected string $table = 'rateb_bi_alerts';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'kpi_id', 'code', 'name', 'threshold_value', 'comparison', 'alert_status', 'last_triggered_at', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiSchedule extends Model
{
    protected string $table = 'rateb_bi_schedules';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'report_id', 'code', 'name', 'cron_hint', 'next_run_at', 'schedule_status', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiExport extends Model
{
    protected string $table = 'rateb_bi_exports';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'report_id', 'export_format', 'export_status', 'storage_path', 'requested_at', 'completed_at', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiAnalyticsScope extends Model
{
    protected string $table = 'rateb_bi_analytics_scopes';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'scope_type', 'scope_ref_id', 'code', 'name', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiComment extends Model
{
    protected string $table = 'rateb_bi_comments';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'comment_text', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiTimeline extends Model
{
    protected string $table = 'rateb_bi_timeline';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'event_type', 'title', 'body', 'entity_type', 'entity_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiStatusHistory extends Model
{
    protected string $table = 'rateb_bi_status_history';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'entity_type', 'entity_id', 'from_status', 'to_status', 'reason', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiAuditLog extends Model
{
    protected string $table = 'rateb_bi_audit_logs';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'action_type', 'actor_user_id', 'entity_type', 'entity_id', 'detail_text', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiFavorite extends Model
{
    protected string $table = 'rateb_bi_favorites';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'user_id', 'entity_type', 'entity_id', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}

final class BiTag extends Model
{
    protected string $table = 'rateb_bi_tags';
    protected bool $tenantScoped = true;
    protected array $fillable = ['public_uuid', 'company_id', 'branch_id', 'code', 'name', 'color', 'status', 'notes', 'version', 'created_by', 'updated_by', 'deleted_at'];
}
