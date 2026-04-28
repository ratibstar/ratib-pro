<?php
/**
 * EN: Handles application behavior in `app/Modules/Subscription/Requests/SubscribeRequest.php`.
 * AR: يدير سلوك جزء من التطبيق في `app/Modules/Subscription/Requests/SubscribeRequest.php`.
 */

declare(strict_types=1);

namespace App\Modules\Subscription\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
