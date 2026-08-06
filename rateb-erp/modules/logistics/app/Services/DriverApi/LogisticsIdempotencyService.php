<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Services\DriverApi;

use Rateb\App\Logistics\Repositories\LogisticsApiIdempotencyRepository;

final class LogisticsIdempotencyService
{
    public function __construct(private LogisticsApiIdempotencyRepository $store = new LogisticsApiIdempotencyRepository())
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @param callable():array{status:int,body:array<string,mixed>} $action
     * @return array{status:int,body:array<string,mixed>,replay:bool}
     */
    public function run(
        int $companyId,
        int $driverId,
        string $endpoint,
        array $payload,
        callable $action
    ): array {
        $key = trim((string) ($payload['idempotency_key'] ?? ''));
        $clientTs = trim((string) ($payload['client_timestamp'] ?? ''));
        if ($key === '') {
            $result = $action();

            return [
                'status' => (int) ($result['status'] ?? 200),
                'body' => is_array($result['body'] ?? null) ? $result['body'] : [],
                'replay' => false,
            ];
        }

        $hash = $this->hashPayload($payload);
        $existing = $this->store->findByKey($companyId, $driverId, $key);
        if ($existing !== null) {
            if ((string) ($existing['request_hash'] ?? '') !== $hash) {
                return [
                    'status' => 409,
                    'body' => [
                        'success' => false,
                        'code' => 'idempotency_conflict',
                        'message' => 'Idempotency key reused with a different payload',
                        'idempotency_key' => $key,
                    ],
                    'replay' => true,
                ];
            }
            $body = json_decode((string) ($existing['response_body'] ?? '{}'), true);
            if (!is_array($body)) {
                $body = ['success' => true];
            }
            $body['idempotent_replay'] = true;
            $body['idempotency_key'] = $key;

            return [
                'status' => (int) ($existing['response_code'] ?? 200),
                'body' => $body,
                'replay' => true,
            ];
        }

        $result = $action();
        $status = (int) ($result['status'] ?? 200);
        $body = is_array($result['body'] ?? null) ? $result['body'] : [];
        $body['idempotency_key'] = $key;
        if ($clientTs !== '') {
            $body['client_timestamp'] = $clientTs;
        }

        // Cache successful and client-error responses; avoid caching auth failures.
        if ($status < 500 && $status !== 401 && $status !== 403) {
            try {
                $this->store->create($companyId, [
                    'driver_id' => $driverId,
                    'idempotency_key' => $key,
                    'endpoint' => $endpoint,
                    'request_hash' => $hash,
                    'response_code' => $status,
                    'response_body' => json_encode($body, JSON_UNESCAPED_UNICODE) ?: '{}',
                    'client_timestamp' => $clientTs !== '' ? $clientTs : null,
                ]);
            } catch (\Throwable $e) {
                // Concurrent insert: re-read and replay if present.
                $race = $this->store->findByKey($companyId, $driverId, $key);
                if ($race !== null && (string) ($race['request_hash'] ?? '') === $hash) {
                    $replayBody = json_decode((string) ($race['response_body'] ?? '{}'), true);
                    if (!is_array($replayBody)) {
                        $replayBody = $body;
                    }
                    $replayBody['idempotent_replay'] = true;

                    return [
                        'status' => (int) ($race['response_code'] ?? $status),
                        'body' => $replayBody,
                        'replay' => true,
                    ];
                }
            }
        }

        return ['status' => $status, 'body' => $body, 'replay' => false];
    }

    /** @param array<string, mixed> $payload */
    public function hashPayload(array $payload): string
    {
        $copy = $payload;
        unset($copy['idempotency_key']);
        ksort($copy);

        return hash('sha256', json_encode($copy, JSON_UNESCAPED_UNICODE) ?: '');
    }
}
