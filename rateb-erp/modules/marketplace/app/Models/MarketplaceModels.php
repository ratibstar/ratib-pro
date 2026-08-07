<?php
declare(strict_types=1);

namespace Rateb\App\Marketplace\Models;

use Rateb\App\Core\Model;

/** Phase 1 — Marketplace models (tables from migration 229). */

final class MarketplaceProvider extends Model
{
    protected string $table = 'rateb_mp_providers';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'branch_id', 'code', 'name', 'name_ar', 'email', 'phone',
        'status', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class MarketplaceCategory extends Model
{
    protected string $table = 'rateb_mp_categories';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'parent_id', 'code', 'name', 'name_ar', 'sort_order',
        'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class MarketplaceService extends Model
{
    protected string $table = 'rateb_mp_services';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'provider_id', 'category_id', 'code', 'name', 'name_ar',
        'description', 'base_price', 'currency_code', 'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class MarketplaceServiceAvailability extends Model
{
    protected string $table = 'rateb_mp_service_availability';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'service_id', 'weekday', 'starts_at', 'ends_at', 'capacity',
        'is_available', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class MarketplaceServiceRequest extends Model
{
    protected string $table = 'rateb_mp_service_requests';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'service_id', 'provider_id', 'customer_id', 'portal_user_id',
        'request_no', 'status', 'requested_at', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class MarketplaceOrder extends Model
{
    protected string $table = 'rateb_mp_orders';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'provider_id', 'customer_id', 'service_request_id', 'order_no',
        'status', 'currency_code', 'subtotal', 'tax_amount', 'total_amount', 'invoice_id',
        'payment_id', 'shipment_id', 'notes', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class MarketplaceOrderItem extends Model
{
    protected string $table = 'rateb_mp_order_items';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'order_id', 'service_id', 'item_name', 'quantity',
        'unit_price', 'line_total', 'sort_order', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class MarketplaceReview extends Model
{
    protected string $table = 'rateb_mp_reviews';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'public_uuid', 'company_id', 'order_id', 'provider_id', 'service_id', 'customer_id',
        'rating', 'title', 'body', 'status', 'created_by', 'updated_by', 'deleted_at',
    ];
}

final class MarketplaceTimelineEvent extends Model
{
    protected string $table = 'rateb_mp_timeline';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'event_type', 'title', 'body', 'related_type', 'related_id',
        'order_id', 'provider_id', 'service_id', 'customer_id', 'created_by',
    ];
}
