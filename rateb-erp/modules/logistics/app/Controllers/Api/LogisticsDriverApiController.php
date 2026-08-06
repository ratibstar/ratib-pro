<?php
declare(strict_types=1);

namespace Rateb\App\Logistics\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Logistics\Services\DriverApi\LogisticsDriverContextService;
use Rateb\App\Logistics\Services\DriverApi\LogisticsIdempotencyService;

abstract class LogisticsDriverApiController extends Controller
{
    protected LogisticsDriverContextService $contextService;
    protected LogisticsIdempotencyService $idempotency;

    public function __construct(
        ?LogisticsDriverContextService $contextService = null,
        ?LogisticsIdempotencyService $idempotency = null
    ) {
        $this->contextService = $contextService ?? new LogisticsDriverContextService();
        $this->idempotency = $idempotency ?? new LogisticsIdempotencyService();
    }

    /** @return array<string, mixed> */
    protected function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if (!is_string($raw) || trim($raw) === '') {
            return is_array($_POST) ? $_POST : [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>|null context or null after JSON error response
     */
    protected function requireDriverContext(): ?array
    {
        $resolved = $this->contextService->resolve();
        if (!($resolved['ok'] ?? false)) {
            Response::json($resolved['body'] ?? ['success' => false], (int) ($resolved['status'] ?? 403));

            return null;
        }

        return $resolved['context'] ?? null;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $payload
     * @param callable():array{status:int,body:array<string,mixed>} $action
     */
    protected function respondIdempotent(array $context, string $endpoint, array $payload, callable $action): void
    {
        $result = $this->idempotency->run(
            (int) $context['company_id'],
            (int) $context['driver_id'],
            $endpoint,
            $payload,
            $action
        );
        Response::json($result['body'], (int) $result['status']);
    }

    /** @param array<string, mixed> $body */
    protected function respond(array $body, int $status = 200): void
    {
        Response::json($body, $status);
    }
}
