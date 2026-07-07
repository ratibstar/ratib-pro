<?php

declare(strict_types=1);



namespace Rateb\App\Pos\Models;



use Rateb\App\Core\Model;



final class PosTerminal extends Model

{

    protected string $table = 'rateb_pos_terminals';

    protected bool $tenantScoped = true;

    protected bool $branchScoped = true;

    protected array $fillable = [

        'company_id', 'branch_id', 'warehouse_id', 'code', 'name', 'status', 'device_meta',

    ];

    protected array $searchable = ['code', 'name'];

}



final class PosSession extends Model

{

    protected string $table = 'rateb_pos_sessions';

    protected bool $tenantScoped = true;

    protected bool $branchScoped = true;

    protected array $fillable = [

        'company_id', 'branch_id', 'terminal_id', 'user_id', 'shift_id', 'status', 'started_at', 'ended_at',

    ];

}



final class PosShift extends Model

{

    protected string $table = 'rateb_pos_shifts';

    protected bool $tenantScoped = true;

    protected bool $branchScoped = true;

    protected array $fillable = [

        'company_id', 'branch_id', 'terminal_id', 'user_id', 'shift_no', 'status',

        'opened_at', 'closed_at', 'opening_float', 'closing_float', 'expected_cash', 'variance', 'notes',
        'last_x_report_at', 'last_z_report_id',

    ];

    protected array $searchable = ['shift_no'];

}



final class PosCashDrawer extends Model

{

    protected string $table = 'rateb_pos_cash_drawers';

    protected bool $tenantScoped = true;

    protected bool $branchScoped = true;

    protected array $fillable = [

        'company_id', 'branch_id', 'terminal_id', 'shift_id', 'status',

        'expected_balance', 'counted_balance', 'variance', 'opened_at', 'closed_at',

    ];

}



final class PosCashDrawerEvent extends Model

{

    protected string $table = 'rateb_pos_cash_drawer_events';

    protected bool $tenantScoped = true;

    protected bool $branchScoped = true;

    protected array $fillable = [

        'company_id', 'branch_id', 'drawer_id', 'shift_id', 'event_type',

        'amount', 'notes', 'user_id',

    ];

}



final class PosOrder extends Model

{

    protected string $table = 'rateb_pos_orders';

    protected bool $tenantScoped = true;

    protected bool $branchScoped = true;

    protected array $fillable = [

        'company_id', 'branch_id', 'warehouse_id', 'terminal_id', 'shift_id', 'session_id',

        'order_no', 'order_type', 'status', 'customer_id', 'original_order_id', 'linked_order_id',
        'idempotency_key',
        'gift_receipt', 'quote_expires_at', 'suspended_payload', 'notes', 'created_by',
        'subtotal', 'discount_total', 'coupon_code', 'promotion_id',
        'loyalty_points_redeemed', 'loyalty_points_earned', 'gift_card_code',
        'tax', 'total',
        'completed_at', 'receipt_json',

    ];

}



final class PosOrderLine extends Model

{

    protected string $table = 'rateb_pos_order_lines';

    protected bool $tenantScoped = true;

    protected bool $branchScoped = false;

    protected array $fillable = [

        'order_id', 'company_id', 'inventory_id', 'batch_id', 'batch_allocations_json', 'serial_no', 'serial_id', 'line_no',
        'line_kind', 'original_line_id', 'return_reason',
        'description', 'quantity', 'unit_price', 'discount_amount', 'tax_amount', 'line_total',

    ];

}



final class PosPayment extends Model

{

    protected string $table = 'rateb_pos_payments';

    protected bool $tenantScoped = true;

    protected bool $branchScoped = false;

    protected array $fillable = [

        'order_id', 'company_id', 'payment_method', 'amount', 'reference_no',

    ];

}



final class PosInventoryReservation extends Model

{

    protected string $table = 'rateb_pos_inventory_reservations';

    protected bool $tenantScoped = true;

    protected bool $branchScoped = true;

    protected array $fillable = [

        'company_id', 'branch_id', 'inventory_id', 'session_id', 'quantity', 'status', 'expires_at',

    ];

}



final class PosSyncQueueItem extends Model

{

    protected string $table = 'rateb_pos_sync_queue';

    protected bool $tenantScoped = true;

    protected array $fillable = [

        'company_id', 'branch_id', 'terminal_id', 'idempotency_key', 'payload', 'status', 'version', 'retry_count', 'last_error',

    ];

}



final class PosSyncConflict extends Model

{

    protected string $table = 'rateb_pos_sync_conflicts';

    protected bool $tenantScoped = true;

    protected array $fillable = [

        'company_id', 'queue_id', 'idempotency_key', 'reason', 'client_payload', 'server_payload', 'status', 'resolved_by', 'resolved_at',

    ];

}



final class PosSetting extends Model

{

    protected string $table = 'rateb_pos_settings';

    protected bool $tenantScoped = true;

    protected bool $branchScoped = false;

    protected array $fillable = ['company_id', 'branch_id', 'settings_json'];

}



final class PosRefund extends Model

{

    protected string $table = 'rateb_pos_refunds';

    protected bool $tenantScoped = true;

    protected bool $branchScoped = true;

    protected array $fillable = [

        'company_id', 'branch_id', 'order_id', 'original_order_id', 'refund_method',

        'amount', 'reference_no', 'status', 'created_by',

    ];

}



final class PosStoreCreditAccount extends Model

{

    protected string $table = 'rateb_pos_store_credit_accounts';

    protected bool $tenantScoped = true;

    protected bool $branchScoped = false;

    protected array $fillable = ['company_id', 'customer_id', 'balance', 'status'];

}



final class PosStoreCreditLedger extends Model

{

    protected string $table = 'rateb_pos_store_credit_ledger';

    protected bool $tenantScoped = true;

    protected bool $branchScoped = false;

    protected array $fillable = [

        'company_id', 'account_id', 'order_id', 'refund_id', 'entry_type',

        'amount', 'balance_after', 'notes', 'created_by',

    ];

}



final class PosReturnRecord extends Model

{

    protected string $table = 'rateb_pos_returns';

    protected bool $tenantScoped = true;

    protected bool $branchScoped = true;

    protected array $fillable = [

        'company_id', 'branch_id', 'original_order_id', 'order_id', 'return_no', 'status', 'total',

    ];

    protected array $searchable = ['return_no'];

}


