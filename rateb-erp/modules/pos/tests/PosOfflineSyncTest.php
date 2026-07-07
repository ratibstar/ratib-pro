<?php

declare(strict_types=1);

/**
 * Offline sync unit tests (Phase 2).
 *
 * Run: php modules/pos/tests/run-offline-sync-tests.php
 */

use Rateb\App\Pos\Services\PosOfflineConflictResolverService;

final class PosOfflineSyncTest
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testAcceptClientWhenServerMissing();
        $this->testRejectClientWhenServerNewer();
        $this->testAcceptClientWhenClientNewer();
        $this->testRejectClientWhenVersionsEqual();

        return $this->results;
    }

    private function testAcceptClientWhenServerMissing(): void
    {
        $resolver = new PosOfflineConflictResolverService();
        $result = $resolver->resolve(['version' => 2, 'payload' => ['a' => 1]], null);
        $ok = ($result['action'] ?? '') === 'accept_client';

        $this->record('accept client when server missing', $ok, (string) ($result['action'] ?? ''));
    }

    private function testRejectClientWhenServerNewer(): void
    {
        $resolver = new PosOfflineConflictResolverService();
        $result = $resolver->resolve(
            ['version' => 1, 'payload' => ['a' => 1]],
            ['version' => 3, 'payload' => ['a' => 2]]
        );
        $ok = ($result['action'] ?? '') === 'reject_client' && ($result['reason'] ?? '') === 'server_newer';

        $this->record('reject client when server newer', $ok, (string) ($result['reason'] ?? ''));
    }

    private function testAcceptClientWhenClientNewer(): void
    {
        $resolver = new PosOfflineConflictResolverService();
        $result = $resolver->resolve(
            ['version' => 5, 'payload' => ['a' => 1]],
            ['version' => 2, 'payload' => ['a' => 2]]
        );
        $ok = ($result['action'] ?? '') === 'accept_client';

        $this->record('accept client when client newer', $ok, (string) ($result['action'] ?? ''));
    }

    private function testRejectClientWhenVersionsEqual(): void
    {
        $resolver = new PosOfflineConflictResolverService();
        $result = $resolver->resolve(
            ['version' => 2, 'payload' => ['a' => 1]],
            ['version' => 2, 'payload' => ['a' => 2]]
        );
        $ok = ($result['action'] ?? '') === 'reject_client';

        $this->record('reject client when versions equal', $ok, (string) ($result['action'] ?? ''));
    }

    private function record(string $name, bool $passed, string $detail): void
    {
        $this->results[] = ['name' => $name, 'passed' => $passed, 'detail' => $detail];
    }
}
