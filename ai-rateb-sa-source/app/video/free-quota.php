<?php
declare(strict_types=1);

/**
 * Free-only online video quota helpers.
 * FREE_AVAILABLE requires configured API key + explicit free entitlement verification.
 * Published marketing prices alone never mark a provider FREE_AVAILABLE.
 */

/** @return list<string> */
function rateb_video_free_provider_order(): array
{
    return ['agnes', 'replicate', 'runware'];
}

function rateb_video_free_only_mode(): bool
{
    $flag = strtolower(trim((string) rateb_video_env('RATEB_VIDEO_FREE_ONLY')));
    if (in_array($flag, ['1', 'on', 'true', 'yes'], true)) {
        return true;
    }
    if (in_array($flag, ['0', 'off', 'false', 'no'], true)) {
        return false;
    }
    $choice = strtolower(trim((string) rateb_video_env('RATEB_VIDEO_PROVIDER')));
    return in_array($choice, ['free', 'free_chain'], true);
}

function rateb_video_free_entitlement_flag(string $provider): bool
{
    $map = [
        'agnes' => 'RATEB_AGNES_FREE_ENTITLEMENT',
        'replicate' => 'RATEB_REPLICATE_FREE_ENTITLEMENT',
        'runware' => 'RATEB_RUNWARE_FREE_ENTITLEMENT',
    ];
    $env = $map[$provider] ?? '';
    if ($env === '') {
        return false;
    }
    $value = strtolower(trim((string) rateb_video_env($env)));
    return in_array($value, ['1', 'on', 'true', 'yes'], true);
}

/**
 * True only for free-only mode when Agnes is the sole FREE_AVAILABLE provider.
 * Used to skip RATEB point reserve/charge; never applies to Runway/private/local
 * or to free-chain jobs that still have another eligible free provider.
 */
function rateb_video_free_agnes_no_credit(): bool
{
    if (!rateb_video_free_only_mode()) {
        return false;
    }
    $agnes = rateb_video_free_provider_status('agnes', false);
    if (empty($agnes['free_available']) || empty($agnes['eligible'])) {
        return false;
    }
    foreach (['replicate', 'runware'] as $other) {
        $status = rateb_video_free_provider_status($other, false);
        if (!empty($status['eligible'])) {
            return false;
        }
    }
    return true;
}

/**
 * @return array{
 *   provider:string,
 *   configured:bool,
 *   free_entitlement_flag:bool,
 *   quota_status:string,
 *   free_available:bool,
 *   eligible:bool,
 *   last_error:string,
 *   last_success_at:string,
 *   exhausted:bool,
 *   notes:string
 * }
 */
function rateb_video_free_provider_status(string $provider, bool $probe = false): array
{
    $provider = strtolower(trim($provider));
    $base = [
        'provider' => $provider,
        'configured' => false,
        'free_entitlement_flag' => rateb_video_free_entitlement_flag($provider),
        'quota_status' => 'UNKNOWN',
        'free_available' => false,
        'eligible' => false,
        'last_error' => '',
        'last_success_at' => rateb_video_free_audit_last_success($provider),
        'exhausted' => rateb_video_free_audit_exhausted($provider),
        'notes' => '',
    ];
    if ($base['exhausted']) {
        $base['quota_status'] = 'EXHAUSTED';
        $base['notes'] = 'Previous free-quota exhaustion recorded in audit log.';
        return $base;
    }

    if ($provider === 'agnes') {
        $adapter = new AgnesVideoProvider();
        $base['configured'] = $adapter->configured();
        $base['notes'] = 'Model forced to agnes-video-2.5-flash (v2.0 retired). Angel $0 is published pricing only; FREE_AVAILABLE needs RATEB_AGNES_FREE_ENTITLEMENT=1 after console verification.';
        if ($probe && $base['configured']) {
            $probeResult = $adapter->probeAccount();
            if (!$probeResult['ok']) {
                $base['last_error'] = (string) ($probeResult['error'] ?? 'probe_failed');
                $base['quota_status'] = 'UNKNOWN';
                return $base;
            }
            $base['quota_status'] = $base['free_entitlement_flag'] ? 'VERIFIED_FREE' : 'UNKNOWN';
        } elseif ($base['free_entitlement_flag'] && $base['configured']) {
            $base['quota_status'] = 'VERIFIED_FREE';
        }
    } elseif ($provider === 'replicate') {
        $adapter = new ReplicateVideoProvider();
        $base['configured'] = $adapter->configured();
        $base['notes'] = 'minimax/video-01 is in Try-for-Free collection; free-run count is not published by account.get. FREE_AVAILABLE needs RATEB_REPLICATE_FREE_ENTITLEMENT=1.';
        if ($probe && $base['configured']) {
            $probeResult = $adapter->probeAccount();
            if (!$probeResult['ok']) {
                $base['last_error'] = (string) ($probeResult['error'] ?? 'probe_failed');
                return $base;
            }
            $base['quota_status'] = $base['free_entitlement_flag'] ? 'VERIFIED_FREE' : 'UNKNOWN';
        } elseif ($base['free_entitlement_flag'] && $base['configured']) {
            $base['quota_status'] = 'VERIFIED_FREE';
        }
    } elseif ($provider === 'runware') {
        $adapter = new RunwareVideoProvider();
        $base['configured'] = $adapter->configured();
        $base['notes'] = 'Balance>0 is not proof of promotional free credit. FREE_AVAILABLE needs RATEB_RUNWARE_FREE_ENTITLEMENT=1 after verifying free/startup credits.';
        if ($probe && $base['configured']) {
            $probeResult = $adapter->probeAccount();
            if (!$probeResult['ok']) {
                $base['last_error'] = (string) ($probeResult['error'] ?? 'probe_failed');
                return $base;
            }
            $balance = (float) ($probeResult['balance'] ?? 0);
            if ($balance <= 0) {
                $base['quota_status'] = 'EXHAUSTED';
                $base['exhausted'] = true;
                $base['notes'] .= ' Account balance is 0.';
                return $base;
            }
            $base['quota_status'] = $base['free_entitlement_flag'] ? 'VERIFIED_FREE' : 'UNKNOWN';
            $base['notes'] .= ' Balance observed (value redacted).';
        } elseif ($base['free_entitlement_flag'] && $base['configured']) {
            $base['quota_status'] = 'VERIFIED_FREE';
        }
    } else {
        $base['notes'] = 'Unknown provider.';
        return $base;
    }

    $base['free_available'] = $base['configured']
        && $base['free_entitlement_flag']
        && $base['quota_status'] === 'VERIFIED_FREE'
        && !$base['exhausted'];
    $base['eligible'] = $base['free_available'];
    return $base;
}

/** @return list<array<string,mixed>> */
function rateb_video_free_provider_statuses(bool $probe = false): array
{
    $rows = [];
    foreach (rateb_video_free_provider_order() as $name) {
        $rows[] = rateb_video_free_provider_status($name, $probe);
    }
    return $rows;
}

function rateb_video_free_audit_path(): string
{
    $root = defined('MEDIA_ROOT') ? (string) MEDIA_ROOT : (dirname(__DIR__, 2) . '/storage');
    return rtrim($root, '/\\') . '/_video_free_audit.jsonl';
}

/** @param array<string,mixed> $row */
function rateb_video_free_audit_write(array $row): void
{
    $row['ts'] = gmdate('c');
    unset($row['api_key'], $row['token'], $row['authorization'], $row['prompt_image'], $row['image']);
    if (isset($row['prompt']) && is_string($row['prompt'])) {
        $row['prompt'] = mb_substr($row['prompt'], 0, 180);
    }
    $line = json_encode($row, JSON_UNESCAPED_UNICODE);
    if (!is_string($line) || $line === '') {
        return;
    }
    $path = rateb_video_free_audit_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    @file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
}

function rateb_video_free_audit_mark_exhausted(string $provider, string $reason): void
{
    rateb_video_free_audit_write([
        'event' => 'quota_exhausted',
        'provider' => $provider,
        'reason' => $reason,
    ]);
}

function rateb_video_free_audit_exhausted(string $provider): bool
{
    $path = rateb_video_free_audit_path();
    if (!is_readable($path)) {
        return false;
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return false;
    }
    $provider = strtolower($provider);
    foreach (array_reverse($lines) as $line) {
        $row = json_decode((string) $line, true);
        if (!is_array($row) || strtolower((string) ($row['provider'] ?? '')) !== $provider) {
            continue;
        }
        $event = (string) ($row['event'] ?? '');
        if ($event === 'quota_reset') {
            return false;
        }
        if ($event === 'quota_exhausted') {
            return true;
        }
    }
    return false;
}

function rateb_video_free_audit_last_success(string $provider): string
{
    $path = rateb_video_free_audit_path();
    if (!is_readable($path)) {
        return '';
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return '';
    }
    $provider = strtolower($provider);
    foreach (array_reverse($lines) as $line) {
        $row = json_decode((string) $line, true);
        if (!is_array($row)) {
            continue;
        }
        if (strtolower((string) ($row['provider'] ?? '')) === $provider && (string) ($row['event'] ?? '') === 'success') {
            return (string) ($row['ts'] ?? '');
        }
    }
    return '';
}

function rateb_video_free_error_is_quota(string $code, string $body = ''): bool
{
    $blob = strtolower($code . ' ' . $body);
    if (in_array($code, ['rejected_credit', 'quota_exhausted', 'http_402', 'http_429'], true)) {
        return true;
    }
    return (bool) preg_match('/\b(credit|quota|billing|payment required|insufficient|out of credit|free.?run)\b/', $blob);
}
