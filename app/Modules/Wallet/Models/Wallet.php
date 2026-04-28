<?php
/**
 * EN: Handles application behavior in `app/Modules/Wallet/Models/Wallet.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Wallet/Models/Wallet.php`.
 */

declare(strict_types=1);

namespace App\Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Wallet extends Model
{
    use SoftDeletes;

    protected $table = 'wallets';

    protected $fillable = [
        'agency_id',
        'holder_type',
        'holder_id',
        'balance',
        'available_balance',
        'pending_balance',
        'total_earned',
        'total_paid',
        'currency_code',
    ];

    protected $casts = [
        'agency_id' => 'integer',
        'holder_id' => 'integer',
        'balance' => 'decimal:2',
        'available_balance' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'total_earned' => 'decimal:2',
        'total_paid' => 'decimal:2',
    ];

    public const HOLDER_AGENCY = 'agency';

    public const HOLDER_CUSTOMER = 'customer';

    public function holder(): MorphTo
    {
        return $this->morphTo();
    }
}
