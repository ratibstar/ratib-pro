<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class Warehouse extends Model
{
    protected string $table = 'rateb_warehouses';
    protected bool $tenantScoped = true;
    protected array $fillable = ['name', 'code', 'location', 'manager_name', 'status'];
}

final class Asset extends Model
{
    protected string $table = 'rateb_assets';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'asset_tag', 'name', 'category', 'purchase_date', 'purchase_cost',
        'current_value', 'location', 'status',
    ];
}

final class MedicalDevice extends Model
{
    protected string $table = 'rateb_medical_devices';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'asset_id', 'device_name', 'manufacturer', 'model_no', 'serial_no',
        'calibration_due', 'maintenance_due', 'regulatory_status', 'status',
    ];
}

final class Contract extends Model
{
    protected string $table = 'rateb_contracts';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'contract_no', 'title', 'supplier_id', 'contract_type', 'start_date',
        'end_date', 'value', 'status', 'document_path',
    ];
}

final class Tender extends Model
{
    protected string $table = 'rateb_tenders';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'tender_no', 'title', 'description', 'publish_date', 'closing_date',
        'estimated_value', 'status',
    ];
}

final class Rfq extends Model
{
    protected string $table = 'rateb_rfq';
    protected bool $tenantScoped = true;
    protected array $fillable = ['rfq_no', 'title', 'status', 'deadline', 'description'];
}

final class SupplierQuotation extends Model
{
    protected string $table = 'rateb_supplier_quotations';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'rfq_id', 'supplier_id', 'quotation_no', 'amount', 'status', 'valid_until', 'notes',
    ];
}

final class StockMovement extends Model
{
    protected string $table = 'rateb_stock_movements';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'inventory_id', 'warehouse_id', 'movement_type', 'quantity',
        'reference_type', 'reference_id', 'notes', 'created_by',
    ];
}

final class Role extends Model
{
    protected string $table = 'rateb_roles';
    protected bool $tenantScoped = false;
    protected array $fillable = ['company_id', 'name', 'slug', 'description', 'is_system'];
}

final class Permission extends Model
{
    protected string $table = 'rateb_permissions';
    protected bool $tenantScoped = false;
    protected array $fillable = ['name', 'slug', 'module', 'description'];
}

final class Payment extends Model
{
    protected string $table = 'rateb_payments';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'subscription_id', 'amount', 'currency', 'method',
        'reference_no', 'status', 'paid_at',
    ];
}

final class Invoice extends Model
{
    protected string $table = 'rateb_invoices';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'subscription_id', 'invoice_no', 'amount', 'tax_amount',
        'total_amount', 'status', 'due_date', 'issued_at',
    ];
}

final class Notification extends Model
{
    protected string $table = 'rateb_notifications';
    protected bool $tenantScoped = false;
    protected array $fillable = ['company_id', 'user_id', 'title', 'message', 'type', 'is_read'];
}

final class AuditLog extends Model
{
    protected string $table = 'rateb_audit_logs';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'user_id', 'action', 'entity_type', 'entity_id',
        'ip_address', 'user_agent', 'payload',
    ];
}

final class LoginActivity extends Model
{
    protected string $table = 'rateb_login_activity';
    protected bool $tenantScoped = false;
    protected array $fillable = ['user_id', 'email', 'ip_address', 'user_agent', 'success'];
}

final class EmailTemplate extends Model
{
    protected string $table = 'rateb_email_templates';
    protected bool $tenantScoped = false;
    protected array $fillable = ['slug', 'subject', 'body_html', 'body_text', 'is_active'];
}

final class SmsTemplate extends Model
{
    protected string $table = 'rateb_sms_templates';
    protected bool $tenantScoped = false;
    protected array $fillable = ['slug', 'body', 'is_active'];
}

final class SystemSetting extends Model
{
    protected string $table = 'rateb_system_settings';
    protected bool $tenantScoped = false;
    protected array $fillable = ['setting_key', 'setting_value', 'setting_group'];

    public function get(string $key, ?string $default = null): ?string
    {
        $row = $this->queryOne('SELECT setting_value FROM rateb_system_settings WHERE setting_key = :k', ['k' => $key]);
        return $row ? (string) $row['setting_value'] : $default;
    }
}

final class ApiToken extends Model
{
    protected string $table = 'rateb_api_tokens';
    protected bool $tenantScoped = false;
    protected array $fillable = ['user_id', 'company_id', 'token_hash', 'name', 'abilities', 'last_used_at', 'expires_at'];
}

final class SupportTicket extends Model
{
    protected string $table = 'rateb_support_tickets';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'user_id', 'ticket_no', 'subject', 'priority', 'status', 'message', 'assigned_to',
    ];
}
