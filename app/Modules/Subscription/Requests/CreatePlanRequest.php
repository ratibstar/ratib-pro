<?php
/**
 * EN: Handles application behavior in `app/Modules/Subscription/Requests/CreatePlanRequest.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Subscription/Requests/CreatePlanRequest.php`.
 */

declare(strict_types=1);

namespace App\Modules\Subscription\Requests;

use App\Modules\Subscription\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $supported = array_keys(config('currencies.supported', []));
        $currencyRule = empty($supported) ? ['required', 'string', 'size:3'] : ['required', 'string', 'size:3', Rule::in($supported)];

        return [
            'name' => ['required', 'string', 'max:255'],
            'interval' => ['required', 'string', Rule::in(SubscriptionPlan::intervals())],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency_code' => $currencyRule,
        ];
    }
}
