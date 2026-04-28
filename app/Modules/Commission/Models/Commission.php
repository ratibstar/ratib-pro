<?php
/**
 * EN: Handles application behavior in `app/Modules/Commission/Models/Commission.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Commission/Models/Commission.php`.
 */

declare(strict_types=1);

namespace App\Modules\Commission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Commission extends Model
{
    use SoftDeletes;

    protected $table = 'commissions';

    protected $fillable = [
        'agency_id',
        'transaction_id',
        'amount',
        'rate',
        'currency_code',
        'status',
    ];

    protected $casts = [
        'agency_id' => 'integer',
        'transaction_id' => 'integer',
        'amount' => 'decimal:2',
        'rate' => 'decimal:2',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Payment\Models\Transaction::class, 'transaction_id');
    }
}
