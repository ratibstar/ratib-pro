<?php
/**
 * EN: Handles application behavior in `app/Modules/Agency/Models/Customer.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Agency/Models/Customer.php`.
 */

declare(strict_types=1);

namespace App\Modules\Agency\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Customer extends Model
{
    use SoftDeletes;

    protected $table = 'customers';

    protected $fillable = [
        'agency_id',
        'name',
        'email',
        'phone',
        'currency_code',
    ];

    protected $casts = [
        'agency_id' => 'integer',
    ];
}
