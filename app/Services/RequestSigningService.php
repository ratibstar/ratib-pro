<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class RequestSigningService
{
    public function enforceGovernmentSigning(string $action, string $rawBody): void
    {
        $mode = strtolower((string) (getenv('SYSTEM_MODE') ?: 'commercial'));
        if ($mode !== 'government') {
            return;
        }
        $sensitiveCsv = (string) (getenv('GOV_SIGN_SENSITIVE_ACTIONS') ?: 'workers.create,tracking.move,workflow.worker_onboarding');
        $sensitive = array_filter(array_map('trim', explode(',', $sensitiveCsv)));
        if (!in_array($action, $sensitive, true)) {
            return;
        }
        $secret = (string) (getenv('GOV_REQUEST_SIGNING_SECRET') ?: '');
        if ($secret === '') {
            throw new RuntimeException('Government signing secret missing', 500);
        }
        $sig = trim((string) ($_SERVER['HTTP_X_REQUEST_SIGNATURE'] ?? ''));
        $ts = trim((string) ($_SERVER['HTTP_X_REQUEST_TIMESTAMP'] ?? ''));
        if ($sig === '' || $ts === '' || !ctype_digit($ts)) {
            throw new RuntimeException('Missing request signature headers', 401);
        }
        $timestamp = (int) $ts;
        if (abs(time() - $timestamp) > 300) {
            throw new RuntimeException('Expired request signature', 401);
        }
        $canonical = $timestamp . "\n" . strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) . "\n" . (string) ($_SERVER['REQUEST_URI'] ?? '/') . "\n" . $rawBody;
        $expected = hash_hmac('sha256', $canonical, $secret);
        if (!hash_equals($expected, $sig)) {
            throw new RuntimeException('Invalid request signature', 401);
        }
    }
}
