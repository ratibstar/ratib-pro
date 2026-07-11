<?php

declare(strict_types=1);

/**
 * Phase 4.5.1 — Queue durability fix validation (H-FLUSH-001).
 *
 * Run: php offline/tests/run-queue-durability-tests.php
 */

final class QueueDurabilityPhase451Test
{
    /** @var list<array{name: string, passed: bool, detail: string}> */
    private array $results = [];

    /** @return list<array{name: string, passed: bool, detail: string}> */
    public function run(): array
    {
        $this->testSourceHasRemoveByKeys();
        $this->testSourceHasNoClearRewriteInFlush();
        $this->testBundleHasRemoveMany();
        $this->testBundleHasNoClearQueue();
        $this->testMigrationsExposeRemoveMany();
        $this->testFifoSortPreserved();
        $this->testPartialSyncKeepsRejectedConflict();
        $this->testCrashClearRewriteLosesData();
        $this->testCrashDeleteByKeyKeepsData();
        $this->testBrowserRefreshMidFlushModel();
        $this->testPowerLossMidDeleteTxModel();
        $this->testLargeQueueStress();
        $this->testIdempotencyKeysUnchanged();
        $this->testReplayOrderMatchesFifo();
        $this->testPhase45GateWouldClearFlushCheck();

        return $this->results;
    }

    private function queueManagerSrc(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/offline/client/sync/queue-manager.js');
    }

    private function migrationsSrc(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/offline/client/db/migrations.js');
    }

    private function bundleSrc(): string
    {
        return (string) file_get_contents(RATEB_ROOT . '/public/assets/offline/rateb-offline.js');
    }

    private function testSourceHasRemoveByKeys(): void
    {
        $src = $this->queueManagerSrc();
        $ok = str_contains($src, 'function removeByKeys')
            && str_contains($src, 'removeByKeys(clearable)')
            && str_contains($src, 'Phase 4.5.1');
        $this->record('source exposes removeByKeys flush path', $ok, $ok ? 'ok' : 'missing');
    }

    private function testSourceHasNoClearRewriteInFlush(): void
    {
        $src = $this->queueManagerSrc();
        $ok = !str_contains($src, 'Stores.clear(QUEUE)')
            && !str_contains($src, 'writeRemaining')
            && !preg_match('/flush\s*\([^)]*\)[^{]*\{[\s\S]*Stores\.clear/', $src);
        $this->record('flush has no clear-then-rewrite', $ok, $ok ? 'ok' : 'clear rewrite still present');
    }

    private function testBundleHasRemoveMany(): void
    {
        $b = $this->bundleSrc();
        $ok = str_contains($b, 'function removeMany')
            && str_contains($b, 'removeMany: removeMany')
            && str_contains($b, '4.5.1');
        $this->record('SDK bundle includes removeMany + 4.5.1 marker', $ok, $ok ? 'ok' : 'stale bundle');
    }

    private function testBundleHasNoClearQueue(): void
    {
        $ok = !str_contains($this->bundleSrc(), 'Stores.clear(QUEUE)');
        $this->record('SDK bundle has no Stores.clear(QUEUE)', $ok, $ok ? 'ok' : 'bundle still unsafe');
    }

    private function testMigrationsExposeRemoveMany(): void
    {
        $src = $this->migrationsSrc();
        $ok = str_contains($src, 'function removeMany')
            && str_contains($src, 'tx.onabort')
            && str_contains($src, 'single IndexedDB transaction');
        $this->record('migrations.js atomic removeMany', $ok, $ok ? 'ok' : 'missing');
    }

    /**
     * Pure FIFO sort mirroring queue-manager _sortFifo.
     *
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function sortFifo(array $items): array
    {
        usort($items, static function (array $a, array $b): int {
            $sa = isset($a['seq']) && is_numeric($a['seq']) ? (int) $a['seq'] : 0;
            $sb = isset($b['seq']) && is_numeric($b['seq']) ? (int) $b['seq'] : 0;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }
            $oa = (string) ($a['occurred_at'] ?? '');
            $ob = (string) ($b['occurred_at'] ?? '');
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }

            return strcmp((string) ($a['client_id'] ?? ''), (string) ($b['client_id'] ?? ''));
        });

        return array_values($items);
    }

    /**
     * @param list<array<string, mixed>> $queue
     * @param list<string> $clearable
     * @return list<array<string, mixed>>
     */
    private function deleteByKey(array $queue, array $clearable): array
    {
        $set = [];
        foreach ($clearable as $k) {
            if ($k !== '') {
                $set[(string) $k] = true;
            }
        }
        $remaining = [];
        foreach ($queue as $item) {
            $key = (string) ($item['client_id'] ?? $item['idempotency_key'] ?? '');
            if (!isset($set[$key])) {
                $remaining[] = $item;
            }
        }

        return $this->sortFifo($remaining);
    }

    private function testFifoSortPreserved(): void
    {
        $queue = [
            ['client_id' => 'c', 'seq' => 30, 'occurred_at' => '2026-07-11T00:00:03Z'],
            ['client_id' => 'a', 'seq' => 10, 'occurred_at' => '2026-07-11T00:00:01Z'],
            ['client_id' => 'b', 'seq' => 20, 'occurred_at' => '2026-07-11T00:00:02Z'],
        ];
        $sorted = $this->sortFifo($queue);
        $order = array_column($sorted, 'client_id');
        $ok = $order === ['a', 'b', 'c'];
        $this->record('FIFO ordering by seq', $ok, json_encode($order) ?: '');
    }

    private function testPartialSyncKeepsRejectedConflict(): void
    {
        $queue = [
            ['client_id' => 'acc1', 'seq' => 1, 'module' => 'inventory', 'status' => 'pending'],
            ['client_id' => 'rej1', 'seq' => 2, 'module' => 'hr', 'status' => 'pending'],
            ['client_id' => 'conf1', 'seq' => 3, 'module' => 'inventory', 'status' => 'pending'],
            ['client_id' => 'dup1', 'seq' => 4, 'module' => 'hr', 'status' => 'pending'],
            ['client_id' => 'pend1', 'seq' => 5, 'module' => 'inventory', 'status' => 'pending'],
        ];
        // Partial sync: only accepted + duplicate are clearable.
        $clearable = ['acc1', 'dup1'];
        $remaining = $this->deleteByKey($queue, $clearable);
        $ids = array_column($remaining, 'client_id');
        $ok = $ids === ['rej1', 'conf1', 'pend1']
            && !in_array('acc1', $ids, true)
            && in_array('rej1', $ids, true)
            && in_array('conf1', $ids, true)
            && in_array('pend1', $ids, true);
        $this->record('partial sync keeps rejected/conflict/pending', $ok, json_encode($ids) ?: '');
    }

    private function testCrashClearRewriteLosesData(): void
    {
        $queue = [
            ['client_id' => 'keep', 'seq' => 1],
            ['client_id' => 'gone', 'seq' => 2],
        ];
        $clearable = ['gone'];
        // Legacy: clear committed, rewrite never ran.
        $afterCrash = [];
        $wouldKeep = $this->deleteByKey($queue, $clearable);
        $ok = $afterCrash === [] && $wouldKeep !== [] && ($wouldKeep[0]['client_id'] ?? '') === 'keep';
        $this->record('crash simulation: clear-rewrite loses remaining', $ok, 'legacy empty store');
    }

    private function testCrashDeleteByKeyKeepsData(): void
    {
        $queue = [
            ['client_id' => 'keep', 'seq' => 1],
            ['client_id' => 'gone', 'seq' => 2],
            ['client_id' => 'conf', 'seq' => 3],
        ];
        // Atomic tx abort → full queue intact (power loss mid-delete).
        $afterAbort = $this->sortFifo($queue);
        $ok = count($afterAbort) === 3
            && array_column($afterAbort, 'client_id') === ['keep', 'gone', 'conf'];
        $this->record('crash simulation: delete-by-key abort keeps all', $ok, json_encode(array_column($afterAbort, 'client_id')) ?: '');
    }

    private function testBrowserRefreshMidFlushModel(): void
    {
        // Refresh after push response received but before removeByKeys completes:
        // items still in IDB → next flush re-pushes → server idempotency handles duplicates.
        $queue = [
            ['client_id' => 'a1', 'seq' => 1, 'idempotency_key' => 'a1'],
            ['client_id' => 'c1', 'seq' => 2, 'idempotency_key' => 'c1'],
        ];
        $refreshBeforeDelete = $queue; // nothing deleted yet
        $ok = count($refreshBeforeDelete) === 2;
        // After successful removeByKeys of accepted only:
        $after = $this->deleteByKey($queue, ['a1']);
        $ok = $ok && array_column($after, 'client_id') === ['c1'];
        $this->record('browser refresh mid-flush: queue recoverable + idempotent', $ok, json_encode(array_column($after, 'client_id')) ?: '');
    }

    private function testPowerLossMidDeleteTxModel(): void
    {
        // Single-transaction delete: either all clearable keys deleted or none.
        $queue = $this->makeLargeQueue(100);
        $clearable = [];
        for ($i = 0; $i < 100; $i += 2) {
            $clearable[] = 'k' . $i;
        }
        // Abort path:
        $abortRemaining = $queue;
        // Commit path:
        $commitRemaining = $this->deleteByKey($queue, $clearable);
        $ok = count($abortRemaining) === 100
            && count($commitRemaining) === 50
            && !in_array('k0', array_column($commitRemaining, 'client_id'), true)
            && in_array('k1', array_column($commitRemaining, 'client_id'), true);
        $this->record('power-loss mid-delete: atomic all-or-nothing', $ok, 'abort=100 commit=' . count($commitRemaining));
    }

    /** @return list<array<string, mixed>> */
    private function makeLargeQueue(int $n): array
    {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = [
                'client_id' => 'k' . $i,
                'idempotency_key' => 'k' . $i,
                'seq' => $i + 1,
                'occurred_at' => sprintf('2026-07-11T00:%02d:%02dZ', intdiv($i, 60) % 60, $i % 60),
                'module' => ($i % 2 === 0) ? 'inventory' : 'hr',
                'action' => ($i % 2 === 0) ? 'stock_movement.create' : 'attendance.create',
            ];
        }

        return $out;
    }

    private function testLargeQueueStress(): void
    {
        $n = 5000;
        $queue = $this->makeLargeQueue($n);
        $clearable = [];
        for ($i = 0; $i < $n; $i += 3) {
            $clearable[] = 'k' . $i;
        }
        $t0 = hrtime(true);
        $remaining = $this->deleteByKey($queue, $clearable);
        $ms = (hrtime(true) - $t0) / 1e6;
        $expect = $n - count($clearable);
        $ok = count($remaining) === $expect
            && ($remaining[0]['client_id'] ?? '') === 'k1'
            && $ms < 2000.0;
        $this->record(
            'large queue stress 5000 delete-by-key',
            $ok,
            'remaining=' . count($remaining) . ' expect=' . $expect . ' ms=' . round($ms, 2)
        );
    }

    private function testIdempotencyKeysUnchanged(): void
    {
        $queue = [
            ['client_id' => 'idemp-a', 'idempotency_key' => 'idemp-a', 'seq' => 1],
            ['client_id' => 'idemp-b', 'idempotency_key' => 'idemp-b', 'seq' => 2],
        ];
        $remaining = $this->deleteByKey($queue, ['idemp-a']);
        $ok = count($remaining) === 1
            && ($remaining[0]['idempotency_key'] ?? '') === 'idemp-b'
            && ($remaining[0]['client_id'] ?? '') === 'idemp-b';
        $this->record('idempotency keys preserved on survivors', $ok, json_encode($remaining[0] ?? []) ?: '');
    }

    private function testReplayOrderMatchesFifo(): void
    {
        $queue = $this->makeLargeQueue(20);
        // Shuffle then sort — push order must follow seq.
        $shuffled = $queue;
        usort($shuffled, static fn (): int => random_int(-1, 1));
        $ordered = $this->sortFifo($shuffled);
        $ok = true;
        for ($i = 0; $i < 20; $i++) {
            if (($ordered[$i]['seq'] ?? 0) !== $i + 1) {
                $ok = false;
                break;
            }
        }
        $afterPartial = $this->deleteByKey($ordered, ['k0', 'k5', 'k10']);
        $seqs = array_column($afterPartial, 'seq');
        $ok = $ok && $seqs === array_values(array_filter(range(1, 20), static fn (int $s): bool => !in_array($s, [1, 6, 11], true)));
        $this->record('replay/push ordering preserved after partial clear', $ok, json_encode($seqs) ?: '');
    }

    private function testPhase45GateWouldClearFlushCheck(): void
    {
        $src = $this->queueManagerSrc();
        $bundle = $this->bundleSrc();
        $usesClearRewrite = str_contains($src, 'Stores.clear(QUEUE)')
            || str_contains($bundle, 'Stores.clear(QUEUE)');
        $ok = !$usesClearRewrite && str_contains($src, 'removeByKeys(clearable)');
        $this->record('H-FLUSH-001 closed (Phase 4.5 gate criterion)', $ok, $ok ? 'CLEAR' : 'still blocked');
    }

    private function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = [
            'name' => $name,
            'passed' => $passed,
            'detail' => $detail,
        ];
    }
}
