<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Middleware;

use Rateb\PlatformCatalog\Application\Support\IdempotencyAcquireResult;
use Rateb\PlatformCatalog\Application\Support\IdempotencyResponseCachePolicy;
use Rateb\PlatformCatalog\Core\Response;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IdempotencyReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IdempotencyWriteRepositoryInterface;
use Rateb\PlatformCatalog\Support\Request;

final class IdempotencyMiddleware
{
    private const TTL_SECONDS = 86400;

    /** @var list<string> */
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH'];

    private ?string $activeKey = null;
    private ?string $activeScope = null;
    private ?string $activeRequestHash = null;

    public function __construct(
        private readonly IdempotencyReadRepositoryInterface $readRepository,
        private readonly IdempotencyWriteRepositoryInterface $writeRepository,
        private readonly string $defaultScope = 'api'
    ) {
    }

    public function handle(string $method, string $path): bool
    {
        if (!in_array(strtoupper($method), self::MUTATING_METHODS, true)) {
            return true;
        }

        if (!str_starts_with($path, '/catalog/')) {
            return true;
        }

        $idempotencyKey = trim((string) (Request::header('Idempotency-Key') ?? ''));
        if ($idempotencyKey === '') {
            return true;
        }

        if (strlen($idempotencyKey) > 128) {
            Response::json([
                'data' => null,
                'meta' => [],
                'errors' => [['message' => 'Idempotency-Key exceeds maximum length']],
            ], 400);

            return false;
        }

        $scope = trim((string) (Request::header('X-Idempotency-Scope') ?? $this->defaultScope));
        if ($scope === '') {
            $scope = $this->defaultScope;
        }

        if (strlen($scope) > 80) {
            Response::json([
                'data' => null,
                'meta' => [],
                'errors' => [['message' => 'X-Idempotency-Scope exceeds maximum length']],
            ], 400);

            return false;
        }

        if ((defined('RATEB_CATALOG_TESTING') && RATEB_CATALOG_TESTING) || random_int(1, 100) === 1) {
            $this->readRepository->deleteExpired();
        }

        $requestHash = $this->buildRequestHash($method, $path);
        $expiresAt = (new \DateTimeImmutable())->modify('+' . self::TTL_SECONDS . ' seconds');
        $acquire = $this->writeRepository->acquire($idempotencyKey, $scope, $requestHash, $expiresAt);

        if ($acquire->action === IdempotencyAcquireResult::HASH_CONFLICT) {
            Response::json([
                'data' => null,
                'meta' => [],
                'errors' => [['message' => 'Idempotency-Key reused with different request payload']],
            ], 409);

            return false;
        }

        if ($acquire->action === IdempotencyAcquireResult::IN_PROGRESS) {
            Response::json([
                'data' => null,
                'meta' => [],
                'errors' => [['message' => 'Idempotent request is already in progress']],
            ], 409);

            return false;
        }

        if ($acquire->action === IdempotencyAcquireResult::REPLAY && $acquire->record !== null) {
            $this->replayResponse($acquire->record);

            return false;
        }

        return $this->beginCapture($idempotencyKey, $scope, $requestHash, $expiresAt);
    }

    private function beginCapture(
        string $idempotencyKey,
        string $scope,
        string $requestHash,
        \DateTimeImmutable $expiresAt
    ): bool {
        $this->activeKey = $idempotencyKey;
        $this->activeScope = $scope;
        $this->activeRequestHash = $requestHash;

        Response::onBeforeExit(function (array $payload, int $status, array $headers) use ($expiresAt): void {
            if ($this->activeKey === null || $this->activeScope === null || $this->activeRequestHash === null) {
                return;
            }

            if (IdempotencyResponseCachePolicy::shouldCache($status)) {
                $this->writeRepository->finalize(
                    $this->activeKey,
                    $this->activeScope,
                    $this->activeRequestHash,
                    $status,
                    $payload,
                    $expiresAt
                );

                return;
            }

            $this->writeRepository->abandon($this->activeKey, $this->activeScope);
        });

        return true;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function replayResponse(array $record): void
    {
        $status = (int) ($record['response_status'] ?? 200);
        $body = $record['response_body'] ?? null;
        if (is_string($body)) {
            $decoded = json_decode($body, true);
            $body = is_array($decoded) ? $decoded : ['data' => null, 'meta' => [], 'errors' => []];
        }
        if (!is_array($body)) {
            $body = ['data' => null, 'meta' => [], 'errors' => []];
        }

        Response::json($body, $status, ['X-Idempotency-Replayed' => 'true']);
    }

    private function buildRequestHash(string $method, string $path): string
    {
        return hash('sha256', strtoupper($method) . "\n" . $path . "\n" . Request::rawBody());
    }
}
