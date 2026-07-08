<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Http\Middleware;

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

        $this->readRepository->deleteExpired();

        $requestHash = $this->buildRequestHash($method, $path);
        $existing = $this->readRepository->findByKeyAndScope($idempotencyKey, $scope);

        if ($existing !== null) {
            if (!$this->isActive($existing)) {
                return $this->beginCapture($idempotencyKey, $scope, $requestHash);
            }

            $storedHash = (string) ($existing['request_hash'] ?? '');
            if ($storedHash !== '' && !hash_equals($storedHash, $requestHash)) {
                Response::json([
                    'data' => null,
                    'meta' => [],
                    'errors' => [['message' => 'Idempotency-Key reused with different request payload']],
                ], 409);

                return false;
            }

            $this->replayResponse($existing);

            return false;
        }

        return $this->beginCapture($idempotencyKey, $scope, $requestHash);
    }

    private function beginCapture(string $idempotencyKey, string $scope, string $requestHash): bool
    {
        $this->activeKey = $idempotencyKey;
        $this->activeScope = $scope;
        $this->activeRequestHash = $requestHash;

        Response::onBeforeExit(function (array $payload, int $status, array $headers): void {
            if ($this->activeKey === null || $this->activeScope === null || $this->activeRequestHash === null) {
                return;
            }

            if ($status < 200 || $status >= 500) {
                return;
            }

            $this->writeRepository->store(
                $this->activeKey,
                $this->activeScope,
                $this->activeRequestHash,
                $status,
                $payload,
                (new \DateTimeImmutable())->modify('+' . self::TTL_SECONDS . ' seconds')
            );
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

    /**
     * @param array<string, mixed> $record
     */
    private function isActive(array $record): bool
    {
        $expiresAt = (string) ($record['expires_at'] ?? '');
        if ($expiresAt === '') {
            return false;
        }

        return strtotime($expiresAt) >= time();
    }

    private function buildRequestHash(string $method, string $path): string
    {
        $rawBody = file_get_contents('php://input');
        $body = $rawBody === false ? '' : $rawBody;

        return hash('sha256', strtoupper($method) . "\n" . $path . "\n" . $body);
    }
}
