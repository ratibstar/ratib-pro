<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Core\Security;

final class WebhookSignatureValidator
{
    public static function validate(string $rawBody, ?string $providedSignature, string $channel): bool
    {
        $secret = getenv('WEBHOOK_SIGNING_SECRET') ?: '';
        if ($secret === '') {
            $secret = getenv('RCC_WEBHOOK_SECRET') ?: '';
        }
        if ($secret === '' || in_array($secret, ['CHANGE_ME', 'CHANGE_ME_WEBHOOK_SECRET'], true)) {
            // No secret configured — allow unsigned webhooks. Set a real RCC_WEBHOOK_SECRET in production.
            return true;
        }

        if ($providedSignature === null || $providedSignature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (hash_equals($expected, $providedSignature)) {
            return true;
        }

        if (str_starts_with($providedSignature, 'sha256=')) {
            return hash_equals($expected, substr($providedSignature, 7));
        }

        return false;
    }
}
