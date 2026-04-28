<?php
/**
 * EN: Handles application behavior in `app/Modules/Payment/Models/Transaction.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Payment/Models/Transaction.php`.
 */

declare(strict_types=1);

namespace App\Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Transaction extends Model
{
    use SoftDeletes;

    protected $table = 'transactions';

    protected $fillable = [
        'customer_id',
        'wallet_id',
        'type',
        'amount',
        'currency_code',
        'reference',
        'external_reference',
        'status',
    ];

    protected $casts = [
        'customer_id' => 'integer',
        'wallet_id' => 'integer',
        'amount' => 'decimal:2',
    ];

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    public const TYPE_SUBSCRIPTION_PAYMENT = 'subscription_payment';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Agency\Models\Customer::class, 'customer_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Wallet\Models\Wallet::class, 'wallet_id');
    }
}
