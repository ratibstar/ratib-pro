<?php

declare(strict_types=1);

use Rateb\PlatformCatalog\Application\Support\IdempotencyAcquireResult;
use Rateb\PlatformCatalog\Application\Support\IdempotencyResponseCachePolicy;

catalog_test('Integration: Idempotency acquire inserts pending row', static function (): void {
    if (!catalog_integration_enabled()) {
        echo "[SKIP] Integration: Idempotency acquire pending (set CATALOG_INTEGRATION_TESTS=1)\n";

        return;
    }

    try {
        $write = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlIdempotencyWriteRepository();
        $read = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlIdempotencyReadRepository();
    } catch (Throwable $e) {
        echo '[SKIP] Integration DB unavailable: ' . $e->getMessage() . "\n";

        return;
    }

    $key = 'integration:acquire:' . bin2hex(random_bytes(6));
    $scope = 'integration';
    $hash = hash('sha256', 'POST' . "\n" . '/catalog/products' . "\n" . '{}');
    $expiresAt = (new DateTimeImmutable('+1 hour'));

    $result = $write->acquire($key, $scope, $hash, $expiresAt);
    catalog_assert_same(IdempotencyAcquireResult::PROCESS, $result->action);

    $row = $read->findByKeyAndScope($key, $scope);
    catalog_assert_true($row !== null);
    catalog_assert_same($hash, $row['request_hash']);
    catalog_assert_null($row['response_status']);

    $write->abandon($key, $scope);
});

catalog_test('Integration: Idempotency duplicate acquire returns in progress', static function (): void {
    if (!catalog_integration_enabled()) {
        echo "[SKIP] Integration: Idempotency duplicate acquire (set CATALOG_INTEGRATION_TESTS=1)\n";

        return;
    }

    try {
        $write = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlIdempotencyWriteRepository();
    } catch (Throwable $e) {
        echo '[SKIP] Integration DB unavailable: ' . $e->getMessage() . "\n";

        return;
    }

    $key = 'integration:dup:' . bin2hex(random_bytes(6));
    $scope = 'integration';
    $hash = hash('sha256', 'POST' . "\n" . '/catalog/products' . "\n" . '{"sku":"A1"}');
    $expiresAt = (new DateTimeImmutable('+1 hour'));

    $first = $write->acquire($key, $scope, $hash, $expiresAt);
    $second = $write->acquire($key, $scope, $hash, $expiresAt);

    catalog_assert_same(IdempotencyAcquireResult::PROCESS, $first->action);
    catalog_assert_same(IdempotencyAcquireResult::IN_PROGRESS, $second->action);

    $write->abandon($key, $scope);
});

catalog_test('Integration: Idempotency finalize and replay cached response', static function (): void {
    if (!catalog_integration_enabled()) {
        echo "[SKIP] Integration: Idempotency finalize replay (set CATALOG_INTEGRATION_TESTS=1)\n";

        return;
    }

    try {
        $write = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlIdempotencyWriteRepository();
    } catch (Throwable $e) {
        echo '[SKIP] Integration DB unavailable: ' . $e->getMessage() . "\n";

        return;
    }

    $key = 'integration:replay:' . bin2hex(random_bytes(6));
    $scope = 'integration';
    $hash = hash('sha256', 'POST' . "\n" . '/catalog/products' . "\n" . '{}');
    $expiresAt = (new DateTimeImmutable('+1 hour'));
    $body = ['data' => ['uuid' => 'prod-1'], 'meta' => [], 'errors' => []];

    catalog_assert_same(IdempotencyAcquireResult::PROCESS, $write->acquire($key, $scope, $hash, $expiresAt)->action);
    $write->finalize($key, $scope, $hash, 201, $body, $expiresAt);

    $replay = $write->acquire($key, $scope, $hash, $expiresAt);
    catalog_assert_same(IdempotencyAcquireResult::REPLAY, $replay->action);
    catalog_assert_true($replay->record !== null);
    catalog_assert_same(201, (int) $replay->record['response_status']);

    $write->abandon($key, $scope);
});

catalog_test('Integration: Idempotency hash mismatch returns conflict', static function (): void {
    if (!catalog_integration_enabled()) {
        echo "[SKIP] Integration: Idempotency hash mismatch (set CATALOG_INTEGRATION_TESTS=1)\n";

        return;
    }

    try {
        $write = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlIdempotencyWriteRepository();
    } catch (Throwable $e) {
        echo '[SKIP] Integration DB unavailable: ' . $e->getMessage() . "\n";

        return;
    }

    $key = 'integration:hash:' . bin2hex(random_bytes(6));
    $scope = 'integration';
    $hashA = hash('sha256', 'POST' . "\n" . '/catalog/products' . "\n" . '{"a":1}');
    $hashB = hash('sha256', 'POST' . "\n" . '/catalog/products' . "\n" . '{"b":2}');
    $expiresAt = (new DateTimeImmutable('+1 hour'));

    catalog_assert_same(IdempotencyAcquireResult::PROCESS, $write->acquire($key, $scope, $hashA, $expiresAt)->action);
    catalog_assert_same(IdempotencyAcquireResult::HASH_CONFLICT, $write->acquire($key, $scope, $hashB, $expiresAt)->action);

    $write->abandon($key, $scope);
});

catalog_test('Integration: Idempotency abandon removes pending row', static function (): void {
    if (!catalog_integration_enabled()) {
        echo "[SKIP] Integration: Idempotency abandon (set CATALOG_INTEGRATION_TESTS=1)\n";

        return;
    }

    try {
        $write = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlIdempotencyWriteRepository();
        $read = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlIdempotencyReadRepository();
    } catch (Throwable $e) {
        echo '[SKIP] Integration DB unavailable: ' . $e->getMessage() . "\n";

        return;
    }

    $key = 'integration:abandon:' . bin2hex(random_bytes(6));
    $scope = 'integration';
    $hash = hash('sha256', 'POST' . "\n" . '/catalog/products' . "\n" . '{}');
    $expiresAt = (new DateTimeImmutable('+1 hour'));

    $write->acquire($key, $scope, $hash, $expiresAt);
    $write->abandon($key, $scope);

    catalog_assert_null($read->findByKeyAndScope($key, $scope));
});

catalog_test('Integration: Idempotency deleteExpired removes expired rows', static function (): void {
    if (!catalog_integration_enabled()) {
        echo "[SKIP] Integration: Idempotency deleteExpired (set CATALOG_INTEGRATION_TESTS=1)\n";

        return;
    }

    try {
        $write = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlIdempotencyWriteRepository();
        $read = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlIdempotencyReadRepository();
    } catch (Throwable $e) {
        echo '[SKIP] Integration DB unavailable: ' . $e->getMessage() . "\n";

        return;
    }

    $key = 'integration:expired:' . bin2hex(random_bytes(6));
    $scope = 'integration';
    $hash = hash('sha256', 'POST' . "\n" . '/catalog/products' . "\n" . '{}');
    $expiredAt = (new DateTimeImmutable('-1 hour'));

    $write->acquire($key, $scope, $hash, $expiredAt);
    $deleted = $read->deleteExpired();
    catalog_assert_true($deleted >= 1);
    catalog_assert_null($read->findByKeyAndScope($key, $scope));
});

catalog_test('Integration: Idempotency non-cacheable finalize allows reprocess', static function (): void {
    if (!catalog_integration_enabled()) {
        echo "[SKIP] Integration: Idempotency non-cacheable response (set CATALOG_INTEGRATION_TESTS=1)\n";

        return;
    }

    try {
        $write = new \Rateb\PlatformCatalog\Infrastructure\Persistence\Repositories\MysqlIdempotencyWriteRepository();
    } catch (Throwable $e) {
        echo '[SKIP] Integration DB unavailable: ' . $e->getMessage() . "\n";

        return;
    }

    $key = 'integration:422:' . bin2hex(random_bytes(6));
    $scope = 'integration';
    $hash = hash('sha256', 'POST' . "\n" . '/catalog/products' . "\n" . '{}');
    $expiresAt = (new DateTimeImmutable('+1 hour'));

    catalog_assert_same(IdempotencyAcquireResult::PROCESS, $write->acquire($key, $scope, $hash, $expiresAt)->action);
    $write->finalize($key, $scope, $hash, 422, ['data' => null, 'meta' => [], 'errors' => []], $expiresAt);

    catalog_assert_false(IdempotencyResponseCachePolicy::shouldCache(422));

    $reacquire = $write->acquire($key, $scope, $hash, $expiresAt);
    catalog_assert_same(IdempotencyAcquireResult::PROCESS, $reacquire->action);

    $write->abandon($key, $scope);
});
