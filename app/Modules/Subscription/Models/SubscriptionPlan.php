<?php
/**
 * EN: Handles application behavior in `app/Modules/Subscription/Models/SubscriptionPlan.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Subscription/Models/SubscriptionPlan.php`.
 */

declare(strict_types=1);

namespace App\Modules\Subscription\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SubscriptionPlan extends Model
{
    use SoftDeletes;

    protected $table = 'subscription_plans';

    protected $fillable = [
        'name',
        'interval',
        'amount',
        'currency_code',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public const INTERVAL_MONTHLY = 'monthly';

    public const INTERVAL_YEARLY = 'yearly';

    public static function intervals(): array
    {
        return [self::INTERVAL_MONTHLY, self::INTERVAL_YEARLY];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'subscription_plan_id');
    }
}
