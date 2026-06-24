<?php
declare(strict_types=1);

namespace Rateb\App\Models;

use Rateb\App\Core\Model;

final class Warehouse extends Model
{
    protected string $table = 'rateb_warehouses';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = ['name', 'code', 'location', 'manager_name', 'status', 'branch_id'];
}

final class Branch extends Model
{
    protected string $table = 'rateb_branches';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected string $branchColumn = 'id';
    protected array $fillable = ['name', 'code', 'address', 'phone', 'email', 'map_url', 'status', 'is_main'];
}

final class Asset extends Model
{
    protected string $table = 'rateb_assets';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'asset_tag', 'name', 'category', 'purchase_date', 'purchase_cost',
        'current_value', 'location', 'status', 'branch_id',
    ];
}

final class MedicalDevice extends Model
{
    protected string $table = 'rateb_medical_devices';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'asset_id', 'device_name', 'manufacturer', 'model_no', 'serial_no',
        'calibration_due', 'maintenance_due', 'warranty_expiry', 'regulatory_status', 'status',
    ];
}

final class Contract extends Model
{
    protected string $table = 'rateb_contracts';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'contract_no', 'title', 'supplier_id', 'contract_type', 'start_date',
        'end_date', 'renewal_date', 'alert_days', 'approval_status', 'value', 'status', 'document_path', 'branch_id',
    ];
}

final class Tender extends Model
{
    protected string $table = 'rateb_tenders';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'tender_no', 'title', 'description', 'publish_date', 'closing_date',
        'estimated_value', 'status', 'branch_id',
    ];
}

final class Rfq extends Model
{
    protected string $table = 'rateb_rfq';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = ['rfq_no', 'title', 'status', 'deadline', 'description', 'branch_id'];
}

final class SupplierQuotation extends Model
{
    protected string $table = 'rateb_supplier_quotations';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'rfq_id', 'supplier_id', 'quotation_no', 'amount', 'status', 'valid_until', 'notes', 'branch_id',
    ];
}

final class StockMovement extends Model
{
    protected string $table = 'rateb_stock_movements';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'movement_no', 'inventory_id', 'warehouse_id', 'movement_type', 'quantity',
        'reference_type', 'reference_id', 'notes', 'created_by', 'branch_id',
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
    protected array $fillable = ['name', 'name_ar', 'slug', 'module', 'description', 'description_ar'];
}

final class ChartOfAccount extends Model
{
    protected string $table = 'rateb_chart_of_accounts';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'code', 'name', 'name_ar', 'account_type', 'parent_id', 'is_active',
    ];
}

final class JournalEntry extends Model
{
    protected string $table = 'rateb_journal_entries';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'entry_no', 'entry_date', 'description', 'description_ar',
        'source_type', 'source_id', 'status', 'created_by', 'posted_at', 'branch_id',
        'submitted_for_approval_at', 'reject_reason', 'rejected_at', 'rejected_by',
    ];
}

final class JournalLine extends Model
{
    protected string $table = 'rateb_journal_lines';
    protected bool $tenantScoped = false;
    protected array $fillable = ['journal_entry_id', 'account_id', 'cost_center_id', 'debit', 'credit', 'memo'];
}

final class CostCenter extends Model
{
    protected string $table = 'rateb_cost_centers';
    protected bool $tenantScoped = true;
    protected array $fillable = ['company_id', 'code', 'name', 'name_ar', 'parent_id', 'is_active'];
}

final class Customer extends Model
{
    protected string $table = 'rateb_customers';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'code', 'name', 'name_ar', 'phone', 'email', 'tax_id',
        'cost_center_id', 'notes', 'is_active', 'branch_id',
    ];
}

final class FiscalPeriod extends Model
{
    protected string $table = 'rateb_fiscal_periods';
    protected bool $tenantScoped = true;
    protected array $fillable = ['company_id', 'name', 'start_date', 'end_date', 'status', 'closed_at', 'closed_by'];
}

final class CashVoucher extends Model
{
    protected string $table = 'rateb_cash_vouchers';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'voucher_no', 'voucher_type', 'voucher_date', 'amount', 'party_name', 'customer_id',
        'description', 'description_ar', 'counter_account_id', 'bank_account_id', 'status', 'journal_entry_id',
        'created_by', 'posted_at', 'submitted_for_approval_at', 'reject_reason', 'rejected_at', 'rejected_by', 'branch_id',
    ];
}

final class BankAccount extends Model
{
    protected string $table = 'rateb_bank_accounts';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'name', 'bank_name', 'account_number', 'chart_account_id',
        'opening_balance', 'is_default', 'is_active',
    ];
}

final class SupplierEvaluation extends Model
{
    protected string $table = 'rateb_supplier_evaluations';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'evaluation_no', 'supplier_id', 'evaluated_by', 'evaluator_name',
        'evaluation_date', 'period_start', 'period_end',
        'quality_score', 'delivery_score', 'price_score', 'service_score',
        'overall_score', 'score_percent', 'rating_tier', 'comments', 'status',
        'manager_approval', 'approved_by', 'approved_at', 'branch_id',
    ];

    public function recalculateOverall(array $scores): float
    {
        $vals = array_map('intval', $scores);
        $sum = array_sum($vals);
        return round($sum / max(count($vals), 1), 2);
    }

    public function updateSupplierRating(int $supplierId): void
    {
        $row = $this->queryOne(
            'SELECT AVG(overall_score) AS avg_rating FROM rateb_supplier_evaluations
             WHERE supplier_id = :sid AND status = :st AND manager_approval = :ap',
            ['sid' => $supplierId, 'st' => 'published', 'ap' => 'approved']
        );
        $avg = $row ? round((float) $row['avg_rating'], 2) : 0.0;
        $avg = max(0.0, min(10.0, $avg));
        $this->db->prepare('UPDATE rateb_suppliers SET rating = :r WHERE id = :id')
            ->execute(['r' => $avg, 'id' => $supplierId]);
    }
}

final class Payment extends Model
{
    protected string $table = 'rateb_payments';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'subscription_id', 'invoice_id', 'amount', 'currency', 'method',
        'reference_no', 'status', 'paid_at',
    ];

    public function withRelations(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        return $this->query(
            "SELECT p.*, c.name AS company_name
             FROM rateb_payments p
             LEFT JOIN rateb_companies c ON c.id = p.company_id
             ORDER BY p.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
    }
}

final class Invoice extends Model
{
    protected string $table = 'rateb_invoices';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'subscription_id', 'invoice_no', 'invoice_type', 'po_number',
        'amount', 'tax_amount', 'total_amount', 'currency', 'discount_amount', 'discount_type',
        'tax_rate', 'payment_terms_days', 'payment_method', 'supplier_account_no', 'supplier_bank_account_id',
        'status', 'payment_status', 'notes',
        'due_date', 'issued_at', 'sent_at', 'barcode', 'qr_code', 'document_path',
    ];

    public function withRelations(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        return $this->query(
            "SELECT i.*, c.name AS company_name
             FROM rateb_invoices i
             LEFT JOIN rateb_companies c ON c.id = i.company_id
             ORDER BY i.id DESC LIMIT {$limit} OFFSET {$offset}"
        );
    }
}

final class InvoiceLine extends Model
{
    protected string $table = 'rateb_invoice_lines';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'invoice_id', 'line_no', 'item_name', 'description', 'quantity', 'unit',
        'unit_price', 'account_id', 'tax_rate', 'excluding_tax', 'line_subtotal', 'tax_amount', 'line_total',
    ];
}

final class Notification extends Model
{
    protected string $table = 'rateb_notifications';
    protected bool $tenantScoped = false;
    protected array $fillable = [
        'company_id', 'user_id', 'title', 'message', 'type', 'trigger_type',
        'entity_type', 'entity_id', 'is_read',
    ];
}

final class PurchaseRequestItem extends Model
{
    protected string $table = 'rateb_purchase_request_items';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'purchase_request_id', 'inventory_id', 'item_name', 'description', 'needed_by',
        'supplier_id', 'warehouse_id', 'account_id', 'attachment_path', 'attachment_name',
        'sku', 'quantity', 'unit',
        'unit_price', 'tax_name', 'tax_rate', 'excluding_tax', 'total_price',
    ];
}

final class ProductCategory extends Model
{
    protected string $table = 'rateb_product_categories';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'code', 'name', 'name_ar', 'description_en', 'description_ar',
        'parent_id', 'sort_order', 'is_active', 'is_visible', 'icon', 'image_path',
    ];
}

final class Document extends Model
{
    protected string $table = 'rateb_documents';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'entity_type', 'entity_id', 'title', 'file_name', 'file_path', 'mime_type', 'file_size', 'uploaded_by', 'branch_id',
    ];
}

final class ApprovalWorkflow extends Model
{
    protected string $table = 'rateb_approval_workflows';
    protected bool $tenantScoped = false;
    protected array $fillable = ['company_id', 'name', 'entity_type', 'is_active'];
}

final class InventoryBatch extends Model
{
    protected string $table = 'rateb_inventory_batches';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = ['inventory_id', 'batch_no', 'quantity', 'production_date', 'expiry_date', 'warehouse_id', 'branch_id'];
}

final class InventoryAudit extends Model
{
    protected string $table = 'rateb_inventory_audits';
    protected bool $tenantScoped = true;
    protected array $fillable = ['audit_no', 'warehouse_id', 'audit_date', 'status', 'notes', 'created_by'];
}

final class SupplierClassification extends Model
{
    protected string $table = 'rateb_supplier_classifications';
    protected bool $tenantScoped = true;
    protected array $fillable = ['name', 'slug', 'color'];
}

final class SupplierCommunication extends Model
{
    protected string $table = 'rateb_supplier_communications';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'supplier_id', 'channel', 'subject', 'comm_date', 'comm_time', 'details',
        'body', 'responsible_name', 'supplier_contact', 'supplier_phone', 'supplier_email',
        'comm_status', 'follow_up_date', 'follow_up_priority',
        'purchase_order_id', 'rfq_id', 'is_archived', 'archived_at', 'created_by',
        'send_status', 'sent_at', 'response_rating', 'response_notes',
        'follow_up_reminded_at', 'no_response_notified_at', 'branch_id',
    ];
    protected array $searchable = ['channel', 'subject', 'body', 'details', 'responsible_name', 'supplier_contact'];
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
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'user_id', 'ticket_no', 'subject', 'priority', 'status', 'message', 'assigned_to', 'branch_id',
    ];
}
