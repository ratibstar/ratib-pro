<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Http\Middleware\IdempotencyMiddleware;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IdempotencyReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IdempotencyWriteRepositoryInterface;

catalog_test('IdempotencyMiddleware replays cached responses', static function (): void {
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'idem-123';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/json';

    $read = new class implements IdempotencyReadRepositoryInterface {
        public function findByKeyAndScope(string $idempotencyKey, string $scope): ?array
        {
            return [
                'idempotency_key' => $idempotencyKey,
                'scope' => $scope,
                'request_hash' => hash('sha256', "POST\n/catalog/products\n"),
                'response_status' => 201,
                'response_body' => json_encode([
                    'data' => ['uuid' => 'prod-1'],
                    'meta' => [],
                    'errors' => [],
                ]),
                'expires_at' => (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s.u'),
            ];
        }

        public function deleteExpired(): int
        {
            return 0;
        }
    };

    $write = new class implements IdempotencyWriteRepositoryInterface {
        public int $stores = 0;

        public function store(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            int $responseStatus,
            ?array $responseBody,
            DateTimeImmutable $expiresAt
        ): void {
            $this->stores++;
        }
    };

    $middleware = new IdempotencyMiddleware($read, $write);

    try {
        $continue = $middleware->handle('POST', '/catalog/products');
        catalog_assert_false($continue);
    } catch (\Rateb\PlatformCatalog\Core\ResponseSentException $e) {
        catalog_assert_same(201, $e->status);
        catalog_assert_true(isset($e->payload['data']['uuid']) && $e->payload['data']['uuid'] === 'prod-1');
        catalog_assert_same('true', $e->headers['X-Idempotency-Replayed'] ?? null);
    }

    catalog_assert_same(0, $write->stores);

    unset($_SERVER['HTTP_IDEMPOTENCY_KEY'], $_SERVER['REQUEST_METHOD'], $_SERVER['CONTENT_TYPE']);
});

catalog_test('IdempotencyMiddleware passes through when header is absent', static function (): void {
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);

    $read = new class implements IdempotencyReadRepositoryInterface {
        public function findByKeyAndScope(string $idempotencyKey, string $scope): ?array
        {
            return null;
        }

        public function deleteExpired(): int
        {
            return 0;
        }
    };

    $write = new class implements IdempotencyWriteRepositoryInterface {
        public function store(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            int $responseStatus,
            ?array $responseBody,
            DateTimeImmutable $expiresAt
        ): void {
        }
    };

    $middleware = new IdempotencyMiddleware($read, $write);
    catalog_assert_true($middleware->handle('POST', '/catalog/products'));
});

catalog_test('IdempotencyMiddleware rejects key reuse with different payload hash', static function (): void {
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'idem-456';
    $_SERVER['REQUEST_METHOD'] = 'POST';

    $read = new class implements IdempotencyReadRepositoryInterface {
        public function findByKeyAndScope(string $idempotencyKey, string $scope): ?array
        {
            return [
                'idempotency_key' => $idempotencyKey,
                'scope' => $scope,
                'request_hash' => hash('sha256', "POST\n/catalog/products\n{\"sku\":\"OLD\"}"),
                'response_status' => 201,
                'response_body' => '{}',
                'expires_at' => (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s.u'),
            ];
        }

        public function deleteExpired(): int
        {
            return 0;
        }
    };

    $write = new class implements IdempotencyWriteRepositoryInterface {
        public function store(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            int $responseStatus,
            ?array $responseBody,
            DateTimeImmutable $expiresAt
        ): void {
        }
    };

    $middleware = new IdempotencyMiddleware($read, $write);

    try {
        $continue = $middleware->handle('POST', '/catalog/products');
        catalog_assert_false($continue);
        throw new RuntimeException('Expected conflict response');
    } catch (\Rateb\PlatformCatalog\Core\ResponseSentException $e) {
        catalog_assert_same(409, $e->status);
        catalog_assert_true(str_contains((string) ($e->payload['errors'][0]['message'] ?? ''), 'different request payload'));
    }

    unset($_SERVER['HTTP_IDEMPOTENCY_KEY'], $_SERVER['REQUEST_METHOD']);
});
