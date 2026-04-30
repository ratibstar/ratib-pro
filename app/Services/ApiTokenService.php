<?php
declare(strict_types=1);

namespace App\Services;

final class ApiTokenService
{
    public function resolveBearerToken(): string
    {
        $auth = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? ''));
        if ($auth !== '' && stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }
        $queryToken = trim((string) ($_GET['api_token'] ?? ''));
        return $queryToken;
    }

    public function validate(string $token): bool
    {
        if ($token === '') {
            return false;
        }
        $single = trim((string) (getenv('EXTERNAL_API_TOKEN') ?: ''));
        if ($single !== '' && hash_equals($single, $token)) {
            return true;
        }
        $csv = trim((string) (getenv('EXTERNAL_API_TOKENS') ?: ''));
        if ($csv === '') {
            return false;
        }
        $tokens = array_values(array_filter(array_map('trim', explode(',', $csv)), static fn (string $x): bool => $x !== ''));
        foreach ($tokens as $candidate) {
            if (hash_equals($candidate, $token)) {
                return true;
            }
        }
        return false;
    }
}
