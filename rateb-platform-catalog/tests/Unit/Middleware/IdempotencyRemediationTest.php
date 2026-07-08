<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Support\IdempotencyAcquireResult;
use Rateb\PlatformCatalog\Core\Response;
use Rateb\PlatformCatalog\Http\Middleware\IdempotencyMiddleware;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IdempotencyReadRepositoryInterface;
use Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\Contracts\IdempotencyWriteRepositoryInterface;
use Rateb\PlatformCatalog\Support\Request;

function idempotency_test_read_repository(): IdempotencyReadRepositoryInterface
{
    return new class implements IdempotencyReadRepositoryInterface {
        public int $deleteExpiredCalls = 0;

        public function findByKeyAndScope(string $idempotencyKey, string $scope): ?array
        {
            return null;
        }

        public function deleteExpired(): int
        {
            $this->deleteExpiredCalls++;

            return 0;
        }
    };
}

catalog_test('IdempotencyMiddleware invokes deleteExpired cleanup', static function (): void {
    $read = idempotency_test_read_repository();
    $write = new class implements IdempotencyWriteRepositoryInterface {
        public function acquire(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            DateTimeImmutable $expiresAt
        ): IdempotencyAcquireResult {
            return new IdempotencyAcquireResult(IdempotencyAcquireResult::PROCESS);
        }

        public function finalize(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            int $responseStatus,
            ?array $responseBody,
            DateTimeImmutable $expiresAt
        ): void {
        }

        public function abandon(string $idempotencyKey, string $scope): void
        {
        }

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

    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'cleanup-key';
    Request::seedRawBodyForTesting('{}');
    $middleware = new IdempotencyMiddleware($read, $write);
    $middleware->handle('POST', '/catalog/products');
    catalog_assert_same(1, $read->deleteExpiredCalls);

    Request::resetCachedInput();
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
});

catalog_test('Idempotency first request persists cacheable response', static function (): void {
    $read = idempotency_test_read_repository();
    $write = new class implements IdempotencyWriteRepositoryInterface {
        public bool $finalized = false;
        public bool $abandoned = false;

        public function acquire(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            DateTimeImmutable $expiresAt
        ): IdempotencyAcquireResult {
            return new IdempotencyAcquireResult(IdempotencyAcquireResult::PROCESS);
        }

        public function finalize(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            int $responseStatus,
            ?array $responseBody,
            DateTimeImmutable $expiresAt
        ): void {
            $this->finalized = true;
        }

        public function abandon(string $idempotencyKey, string $scope): void
        {
            $this->abandoned = true;
        }

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

    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'persist-key';
    Request::seedRawBodyForTesting('{"sku":"A1"}');
    $middleware = new IdempotencyMiddleware($read, $write);
    catalog_assert_true($middleware->handle('POST', '/catalog/products'));

    try {
        Response::json(['data' => ['uuid' => 'prod-1'], 'meta' => [], 'errors' => []], 201);
    } catch (\Rateb\PlatformCatalog\Core\ResponseSentException) {
    }

    catalog_assert_true($write->finalized);
    catalog_assert_false($write->abandoned);

    Request::resetCachedInput();
    Response::resetBeforeExit();
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
});

catalog_test('Request jsonBody remains readable after idempotency middleware', static function (): void {
    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'body-key';
    $_SERVER['CONTENT_TYPE'] = 'application/json; charset=UTF-8';
    Request::seedRawBodyForTesting('{"sku":"A1","name":"Widget"}');

    $read = idempotency_test_read_repository();
    $write = new class implements IdempotencyWriteRepositoryInterface {
        public function acquire(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            DateTimeImmutable $expiresAt
        ): IdempotencyAcquireResult {
            return new IdempotencyAcquireResult(IdempotencyAcquireResult::PROCESS);
        }

        public function finalize(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            int $responseStatus,
            ?array $responseBody,
            DateTimeImmutable $expiresAt
        ): void {
        }

        public function abandon(string $idempotencyKey, string $scope): void
        {
        }

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
    catalog_assert_same('A1', Request::jsonBody()['sku'] ?? null);
    catalog_assert_same('Widget', Request::jsonBody()['name'] ?? null);

    Request::resetCachedInput();
    Response::resetBeforeExit();
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY'], $_SERVER['CONTENT_TYPE']);
});

catalog_test('Idempotency concurrent request simulation returns in-progress conflict', static function (): void {
    $read = idempotency_test_read_repository();
    $write = new class implements IdempotencyWriteRepositoryInterface {
        public int $acquireCount = 0;

        public function acquire(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            DateTimeImmutable $expiresAt
        ): IdempotencyAcquireResult {
            $this->acquireCount++;
            if ($this->acquireCount === 1) {
                return new IdempotencyAcquireResult(IdempotencyAcquireResult::PROCESS);
            }

            return new IdempotencyAcquireResult(IdempotencyAcquireResult::IN_PROGRESS);
        }

        public function finalize(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            int $responseStatus,
            ?array $responseBody,
            DateTimeImmutable $expiresAt
        ): void {
        }

        public function abandon(string $idempotencyKey, string $scope): void
        {
        }

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

    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'concurrent-key';
    Request::seedRawBodyForTesting('{}');
    $middleware = new IdempotencyMiddleware($read, $write);
    catalog_assert_true($middleware->handle('POST', '/catalog/products'));

    try {
        catalog_assert_false($middleware->handle('POST', '/catalog/products'));
        throw new RuntimeException('Expected in-progress conflict');
    } catch (\Rateb\PlatformCatalog\Core\ResponseSentException $e) {
        catalog_assert_same(409, $e->status);
        catalog_assert_true(str_contains((string) ($e->payload['errors'][0]['message'] ?? ''), 'already in progress'));
    }

    Request::resetCachedInput();
    Response::resetBeforeExit();
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
});

catalog_test('Idempotency replays after cached success', static function (): void {
    $read = idempotency_test_read_repository();
    $write = new class implements IdempotencyWriteRepositoryInterface {
        public function acquire(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            DateTimeImmutable $expiresAt
        ): IdempotencyAcquireResult {
            return new IdempotencyAcquireResult(IdempotencyAcquireResult::REPLAY, [
                'response_status' => 201,
                'response_body' => json_encode([
                    'data' => ['uuid' => 'cached-prod'],
                    'meta' => [],
                    'errors' => [],
                ]),
            ]);
        }

        public function finalize(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            int $responseStatus,
            ?array $responseBody,
            DateTimeImmutable $expiresAt
        ): void {
        }

        public function abandon(string $idempotencyKey, string $scope): void
        {
        }

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

    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'replay-key';
    Request::seedRawBodyForTesting('{}');
    $middleware = new IdempotencyMiddleware($read, $write);

    try {
        catalog_assert_false($middleware->handle('POST', '/catalog/products'));
    } catch (\Rateb\PlatformCatalog\Core\ResponseSentException $e) {
        catalog_assert_same(201, $e->status);
        catalog_assert_same('cached-prod', $e->payload['data']['uuid'] ?? null);
        catalog_assert_same('true', $e->headers['X-Idempotency-Replayed'] ?? null);
    }

    Request::resetCachedInput();
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
});

catalog_test('Idempotency does not cache 422 validation responses', static function (): void {
    $read = idempotency_test_read_repository();
    $write = new class implements IdempotencyWriteRepositoryInterface {
        public bool $finalized = false;
        public bool $abandoned = false;

        public function acquire(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            DateTimeImmutable $expiresAt
        ): IdempotencyAcquireResult {
            return new IdempotencyAcquireResult(IdempotencyAcquireResult::PROCESS);
        }

        public function finalize(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            int $responseStatus,
            ?array $responseBody,
            DateTimeImmutable $expiresAt
        ): void {
            $this->finalized = true;
        }

        public function abandon(string $idempotencyKey, string $scope): void
        {
            $this->abandoned = true;
        }

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

    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'validation-key';
    Request::seedRawBodyForTesting('{}');
    $middleware = new IdempotencyMiddleware($read, $write);
    catalog_assert_true($middleware->handle('POST', '/catalog/products'));

    try {
        Response::json([
            'data' => null,
            'meta' => [],
            'errors' => [['message' => 'Invalid upload']],
        ], 422);
    } catch (\Rateb\PlatformCatalog\Core\ResponseSentException) {
    }

    catalog_assert_false($write->finalized);
    catalog_assert_true($write->abandoned);

    Request::resetCachedInput();
    Response::resetBeforeExit();
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
});

catalog_test('Idempotency hash conflict is not cached as business replay', static function (): void {
    $read = idempotency_test_read_repository();
    $write = new class implements IdempotencyWriteRepositoryInterface {
        public bool $finalized = false;

        public function acquire(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            DateTimeImmutable $expiresAt
        ): IdempotencyAcquireResult {
            return new IdempotencyAcquireResult(IdempotencyAcquireResult::HASH_CONFLICT);
        }

        public function finalize(
            string $idempotencyKey,
            string $scope,
            string $requestHash,
            int $responseStatus,
            ?array $responseBody,
            DateTimeImmutable $expiresAt
        ): void {
            $this->finalized = true;
        }

        public function abandon(string $idempotencyKey, string $scope): void
        {
        }

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

    $_SERVER['HTTP_IDEMPOTENCY_KEY'] = 'hash-conflict';
    Request::seedRawBodyForTesting('{"sku":"NEW"}');
    $middleware = new IdempotencyMiddleware($read, $write);

    try {
        catalog_assert_false($middleware->handle('POST', '/catalog/products'));
    } catch (\Rateb\PlatformCatalog\Core\ResponseSentException $e) {
        catalog_assert_same(409, $e->status);
        catalog_assert_true(str_contains((string) ($e->payload['errors'][0]['message'] ?? ''), 'different request payload'));
    }

    catalog_assert_false($write->finalized);

    Request::resetCachedInput();
    unset($_SERVER['HTTP_IDEMPOTENCY_KEY']);
});
