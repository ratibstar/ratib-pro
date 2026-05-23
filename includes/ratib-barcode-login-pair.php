<?php
declare(strict_types=1);

/**
 * Temporary tokens for cross-device barcode login (PC waits, phone scans badge).
 */
if (!function_exists('ratib_barcode_pair_dir')) {
    function ratib_barcode_pair_dir(): string
    {
        $dir = dirname(__DIR__) . '/cache/barcode_login_pairs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        return $dir;
    }
}

if (!function_exists('ratib_barcode_pair_path')) {
    function ratib_barcode_pair_path(string $token): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        return ratib_barcode_pair_dir() . '/' . $token . '.json';
    }
}

if (!function_exists('ratib_barcode_pair_read')) {
    /**
     * @return array<string, mixed>|null
     */
    function ratib_barcode_pair_read(string $token): ?array
    {
        $path = ratib_barcode_pair_path($token);
        if ($path === null || !is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }
        $expires = (int) ($data['expires'] ?? 0);
        if ($expires > 0 && time() > $expires) {
            @unlink($path);
            return null;
        }
        return $data;
    }
}

if (!function_exists('ratib_barcode_pair_write')) {
    /**
     * @param array<string, mixed> $data
     */
    function ratib_barcode_pair_write(string $token, array $data): bool
    {
        $path = ratib_barcode_pair_path($token);
        if ($path === null) {
            return false;
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        return @file_put_contents($path, $json, LOCK_EX) !== false;
    }
}

if (!function_exists('ratib_barcode_pair_create')) {
    /**
     * @param array<string, mixed> $context
     * @return array{ok:bool, token?:string, message?:string}
     */
    function ratib_barcode_pair_create(array $context): array
    {
        try {
            $token = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Could not create login session.'];
        }
        $data = [
            'status' => 'pending',
            'created' => time(),
            'expires' => time() + 300,
            'context' => $context,
            'session' => null,
        ];
        if (!ratib_barcode_pair_write($token, $data)) {
            return ['ok' => false, 'message' => 'Could not store login session.'];
        }
        return ['ok' => true, 'token' => $token];
    }
}

if (!function_exists('ratib_barcode_pair_approve')) {
    /**
     * @param array<string, mixed> $sessionPayload
     */
    function ratib_barcode_pair_approve(string $token, array $sessionPayload): bool
    {
        $data = ratib_barcode_pair_read($token);
        if ($data === null || ($data['status'] ?? '') !== 'pending') {
            return false;
        }
        $data['status'] = 'approved';
        $data['session'] = $sessionPayload;
        $data['approved_at'] = time();
        return ratib_barcode_pair_write($token, $data);
    }
}

if (!function_exists('ratib_barcode_pair_poll')) {
    /**
     * @return array{ok:bool, status?:string, message?:string}
     */
    function ratib_barcode_pair_poll(string $token): array
    {
        $data = ratib_barcode_pair_read($token);
        if ($data === null) {
            return ['ok' => false, 'status' => 'expired', 'message' => 'Session expired.'];
        }
        return [
            'ok' => true,
            'status' => (string) ($data['status'] ?? 'pending'),
        ];
    }
}

if (!function_exists('ratib_barcode_pair_consume_session')) {
    /**
     * @return array<string, mixed>|null
     */
    function ratib_barcode_pair_consume_session(string $token): ?array
    {
        $data = ratib_barcode_pair_read($token);
        if ($data === null) {
            return null;
        }
        if (($data['status'] ?? '') !== 'approved' || !is_array($data['session'] ?? null)) {
            return null;
        }
        $session = $data['session'];
        $path = ratib_barcode_pair_path($token);
        if ($path !== null) {
            @unlink($path);
        }
        return $session;
    }
}
