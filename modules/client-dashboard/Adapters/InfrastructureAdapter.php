<?php
/**
 * Read-only awareness via public JSON endpoint (no deep module coupling).
 */
declare(strict_types=1);

final class RATEB_ClientDashboard_InfrastructureAdapter
{
    /**
     * @return array<string, mixed>
     */
    public function fetchAwareness(RATEB_ClientDashboard_AdapterContext $ctx): array
    {
        $fallback = [
            'reachable' => false,
            'control_plane' => 'unknown',
            'queue' => null,
            'providers' => null,
            'diagnostics' => null,
            'incident_level' => 'none',
        ];

        if (!function_exists('getBaseUrl')) {
            $ctx->obs->recordAdapter('infrastructure', false, 'no_base_url');

            return $fallback;
        }

        $url = rtrim((string) getBaseUrl(), '/') . '/api/infrastructure-marketplace/dashboard.php';

        try {
            $body = $this->httpGetJson($url, 2);
            if (!is_array($body)) {
                $ctx->obs->recordAdapter('infrastructure', false, 'invalid_json', ['url' => $url]);

                return $fallback;
            }

            $queue = $body['queue'] ?? null;
            $providers = $body['providers'] ?? null;
            $diag = $body['diagnostics'] ?? null;

            $level = 'none';
            $provStatus = is_array($providers) ? strtolower((string) ($providers['status'] ?? '')) : '';
            if ($provStatus === 'unavailable') {
                $level = 'warning';
            }

            $ctx->obs->recordAdapter('infrastructure', true, null, ['incident_level' => $level]);

            return [
                'reachable' => true,
                'control_plane' => is_array($diag) ? (string) ($diag['status'] ?? 'ok') : 'ok',
                'queue' => $queue,
                'providers' => $providers,
                'diagnostics' => $diag,
                'incident_level' => $level,
            ];
        } catch (Throwable $e) {
            $ctx->obs->recordAdapter('infrastructure', false, $e->getMessage());

            return $fallback;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function httpGetJson(string $url, int $timeoutSec): ?array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => $timeoutSec,
                CURLOPT_TIMEOUT => $timeoutSec,
                CURLOPT_HTTPHEADER => ['Accept: application/json'],
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
            if (!is_string($raw) || $raw === '') {
                return null;
            }
            $data = json_decode($raw, true);

            return is_array($data) ? $data : null;
        }

        $streamCtx = stream_context_create([
            'http' => [
                'timeout' => $timeoutSec,
                'header' => "Accept: application/json\r\n",
            ],
        ]);
        $raw = @file_get_contents($url, false, $streamCtx);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }
}
