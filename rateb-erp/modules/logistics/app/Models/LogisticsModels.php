<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Models;

use Rateb\App\Core\Model;

final class LogisticsDriver extends Model
{
    protected string $table = 'rateb_logistics_drivers';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'branch_id', 'employee_id', 'license_number', 'license_type',
        'license_expiry', 'status', 'notes', 'created_by', 'updated_by',
    ];
    protected array $searchable = ['license_number', 'license_type', 'status'];
}

final class LogisticsVehicle extends Model
{
    protected string $table = 'rateb_logistics_vehicles';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'branch_id', 'plate_number', 'vehicle_type', 'brand', 'model',
        'year', 'capacity', 'status', 'current_driver_id', 'notes', 'created_by', 'updated_by',
    ];
    protected array $searchable = ['plate_number', 'vehicle_type', 'brand', 'model', 'status'];
}

final class LogisticsRoute extends Model
{
    protected string $table = 'rateb_logistics_routes';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'branch_id', 'code', 'name', 'name_ar', 'origin', 'destination',
        'distance_km', 'estimated_minutes', 'status', 'notes', 'created_by', 'updated_by',
    ];
    protected array $searchable = ['code', 'name', 'name_ar', 'origin', 'destination', 'status'];
}

final class LogisticsDeliveryOrder extends Model
{
    protected string $table = 'rateb_logistics_delivery_orders';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'branch_id', 'customer_id', 'order_id', 'order_ref', 'delivery_no',
        'pickup_location', 'delivery_location', 'planned_date', 'status', 'notes',
        'created_by', 'updated_by',
    ];
    protected array $searchable = ['delivery_no', 'order_ref', 'pickup_location', 'delivery_location', 'status'];
}

final class LogisticsTrip extends Model
{
    protected string $table = 'rateb_logistics_trips';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'branch_id', 'driver_id', 'vehicle_id', 'route_id', 'origin',
        'destination', 'planned_date', 'start_time', 'end_time', 'status', 'notes',
        'created_by', 'updated_by',
    ];
    protected array $searchable = ['origin', 'destination', 'status'];
}

final class LogisticsShipment extends Model
{
    protected string $table = 'rateb_logistics_shipments';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'branch_id', 'customer_id', 'order_id', 'delivery_order_id', 'trip_id',
        'tracking_number', 'pickup_location', 'delivery_location', 'status',
        'dispatched_at', 'delivered_at', 'notes', 'created_by', 'updated_by',
    ];
    protected array $searchable = ['tracking_number', 'pickup_location', 'delivery_location', 'status'];
}

final class LogisticsDeliveryProof extends Model
{
    protected string $table = 'rateb_logistics_delivery_proofs';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'shipment_id', 'receiver_name', 'signature_file', 'photo_file',
        'gps_lat', 'gps_long', 'delivered_at', 'notes', 'created_by',
    ];
    protected array $searchable = ['receiver_name'];
}

final class LogisticsExpense extends Model
{
    protected string $table = 'rateb_logistics_expenses';
    protected bool $tenantScoped = true;
    protected bool $branchScoped = true;
    protected array $fillable = [
        'company_id', 'branch_id', 'trip_id', 'vehicle_id', 'driver_id', 'expense_type',
        'amount', 'currency', 'expense_date', 'description', 'journal_entry_id', 'status',
        'created_by', 'updated_by',
    ];
    protected array $searchable = ['expense_type', 'description', 'status', 'currency'];
}

final class LogisticsStatusHistory extends Model
{
    protected string $table = 'rateb_logistics_status_history';
    protected bool $tenantScoped = true;
    protected array $fillable = [
        'company_id', 'entity_type', 'entity_id', 'from_status', 'to_status',
        'reason', 'created_by',
    ];
    protected array $searchable = ['entity_type', 'from_status', 'to_status', 'reason'];
}
