<?php
declare(strict_types=1);

namespace Rateb\App\Payment\Gateways;

final class MoyasarErrorMapper
{
    /**
     * @param array<string, mixed>|null $json
     * @return array{code: string, message: string}
     */
    public static function fromResponse(int $httpStatus, ?array $json, string $fallback = 'Gateway request failed'): array
    {
        if ($httpStatus === 0) {
            return ['code' => 'network_timeout', 'message' => 'Payment gateway network timeout'];
        }
        if ($httpStatus === 401 || $httpStatus === 403) {
            return ['code' => 'auth_failed', 'message' => 'Payment gateway authentication failed'];
        }
        if ($json === null) {
            return ['code' => 'invalid_response', 'message' => $fallback];
        }
        if (isset($json['message']) && is_string($json['message'])) {
            return ['code' => 'gateway_error', 'message' => $json['message']];
        }
        if (isset($json['errors']) && is_array($json['errors'])) {
            $first = $json['errors'][0] ?? null;
            if (is_array($first) && isset($first['message'])) {
                return ['code' => 'gateway_error', 'message' => (string) $first['message']];
            }
        }

        return ['code' => 'http_' . $httpStatus, 'message' => $fallback];
    }

    public static function normalizeStatus(string $status): string
    {
        return strtolower(trim($status));
    }

    public static function isPaidStatus(string $status): bool
    {
        return in_array(self::normalizeStatus($status), ['paid', 'captured', 'completed'], true);
    }
}
