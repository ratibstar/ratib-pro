<?php
/**
 * EN: Handles application behavior in `app/Modules/Subscription/Models/Subscription.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Subscription/Models/Subscription.php`.
 */

declare(strict_types=1);

namespace App\Modules\Subscription\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Subscription extends Model
{
    use SoftDeletes;

    protected $table = 'subscriptions';

    protected $fillable = [
        'customer_id',
        'subscription_plan_id',
        'status',
        'started_at',
        'ended_at',
        'currency_code',
        'amount',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'subscription_plan_id' => 'integer',
        'amount' => 'decimal:2',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_ACTIVE,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Agency\Models\Customer::class, 'customer_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ACTIVE], true);
    }
}
