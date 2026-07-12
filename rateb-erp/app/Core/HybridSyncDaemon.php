<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase C.1 — Always-On Hybrid Sync Service (orchestrator only).
 *
 * Reuses HybridSyncEngine exactly — no business logic, no second runtime.
 * Loop: online check → resume → push → pull → sleep.
 */
final class HybridSyncDaemon
{
    public const SLEEP_OFFLINE_SEC = 5;
    public const SLEEP_IDLE_SEC = 2;
    public const SLEEP_AFTER_WORK_SEC = 2;

    private HybridSyncEngine $engine;

    private bool $stopping = false;

    private bool $wasOnline = false;

    private $lockFh = null;

    /** @var resource|null */
    private $logFh = null;

    public function __construct(?HybridSyncEngine $engine = null)
    {
        $this->engine = $engine ?? new HybridSyncEngine();
    }

    /**
     * @param array{max_cycles?:int,pull_entities?:list<string>,fast?:bool,stop_when_idle?:bool} $options
     */
    public function run(array $options = []): int
    {
        if (!HybridRuntime::shouldUseSqlite()) {
            $this->log('error', 'daemon_refused', ['reason' => 'not_branch_sqlite']);

            return 2;
        }
        if (!HybridSyncConfig::enabled()) {
            $this->log('error', 'daemon_refused', ['reason' => 'sync_disabled']);

            return 2;
        }
        if (!$this->acquireLock()) {
            $this->log('error', 'daemon_refused', ['reason' => 'already_running']);

            return 3;
        }

        $this->openLog();
        $this->installSignalHandlers();
        $this->log('info', 'startup', [
            'pid' => getmypid(),
            'sink' => HybridSyncConfig::sinkMode(),
            'sqlite' => HybridRuntime::sqlitePath(),
        ]);

        // Crash recovery on start
        $resumed = $this->engine->resumeInterrupted();
        $this->log('info', 'resume', $resumed);

        $maxCycles = isset($options['max_cycles']) ? (int) $options['max_cycles'] : 0;
        $fast = !empty($options['fast']) || $maxCycles > 0;
        $stopWhenIdle = !empty($options['stop_when_idle']);
        $sleepOffline = $fast ? 1 : self::SLEEP_OFFLINE_SEC;
        $sleepIdle = $fast ? 1 : self::SLEEP_IDLE_SEC;
        $sleepAfterWork = $fast ? 1 : self::SLEEP_AFTER_WORK_SEC;
        $pullEntities = $options['pull_entities'] ?? self::defaultPullEntities();
        $cycle = 0;

        while (!$this->stopping) {
            if ($this->shouldStopFromFile()) {
                $this->stopping = true;
                break;
            }

            $online = HybridSyncEngine::isOnline();
            if ($online !== $this->wasOnline) {
                $this->log('info', 'internet_change', ['online' => $online]);
                $this->wasOnline = $online;
            }

            if (!$online) {
                $this->log('info', 'offline', ['sleep' => $sleepOffline]);
                $this->sleepSeconds($sleepOffline);
                $cycle++;
                if ($maxCycles > 0 && $cycle >= $maxCycles) {
                    break;
                }
                continue;
            }

            try {
                $this->engine->resumeInterrupted();
                $pending = $this->engine->actionableOutboxCount();

                if ($pending < 1) {
                    $pulled = $this->pullAll($pullEntities);
                    if ($pulled > 0) {
                        $this->log('info', 'pull', ['rows' => $pulled]);
                        $this->sleepSeconds($sleepAfterWork);
                    } else {
                        if ($stopWhenIdle) {
                            $this->log('info', 'idle_exit', ['cycle' => $cycle]);
                            break;
                        }
                        $this->sleepSeconds($sleepIdle);
                    }
                } else {
                    $push = $this->engine->pushPending(null, HybridSyncConfig::BATCH_SIZE);
                    $this->log('info', 'push', [
                        'accepted' => $push['accepted'] ?? 0,
                        'duplicate' => $push['duplicate'] ?? 0,
                        'failed' => $push['failed'] ?? 0,
                        'conflict' => $push['conflict'] ?? 0,
                        'paused' => !empty($push['paused']),
                        'batch' => $push['batch'] ?? '',
                    ]);
                    if (($push['conflict'] ?? 0) > 0) {
                        $this->log('warn', 'conflict', ['count' => $push['conflict']]);
                    }
                    if (($push['failed'] ?? 0) > 0) {
                        $this->log('warn', 'retry', ['failed' => $push['failed']]);
                    }
                    $pulled = $this->pullAll($pullEntities);
                    if ($pulled > 0) {
                        $this->log('info', 'pull', ['rows' => $pulled]);
                    }
                    $this->log('info', 'success', ['pending_before' => $pending]);
                    $this->sleepSeconds($sleepAfterWork);
                }
            } catch (\Throwable $e) {
                $this->log('error', 'error', ['message' => $e->getMessage()]);
                $this->sleepSeconds($sleepOffline);
            }

            $cycle++;
            if ($maxCycles > 0 && $cycle >= $maxCycles) {
                break;
            }
        }

        $this->log('info', 'shutdown', ['cycles' => $cycle]);
        $this->releaseLock();
        $this->closeLog();

        return 0;
    }

    public function requestStop(): void
    {
        $this->stopping = true;
    }

    /** @param list<string> $entities */
    private function pullAll(array $entities): int
    {
        $total = 0;
        foreach ($entities as $entity) {
            $entity = trim($entity);
            if ($entity === '') {
                continue;
            }
            $r = $this->engine->pullEntity($entity, null, 100);
            $total += (int) ($r['rows'] ?? 0);
        }

        return $total;
    }

    /** @return list<string> */
    public static function defaultPullEntities(): array
    {
        $raw = $_ENV['RATEB_HYBRID_SYNC_PULL_ENTITIES'] ?? getenv('RATEB_HYBRID_SYNC_PULL_ENTITIES');
        if (is_string($raw) && trim($raw) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $raw))));
        }

        // Safe defaults — pullDelta no-ops when table missing on sink.
        return [
            'rateb_plans',
            'rateb_permissions',
            'rateb_warehouses',
        ];
    }

    private function acquireLock(): bool
    {
        HybridRuntime::ensureBranchStorage();
        $path = HybridRuntime::branchStorageDir() . '/hybrid-sync.daemon.lock';
        $fh = @fopen($path, 'c+');
        if ($fh === false) {
            return false;
        }
        if (!@flock($fh, LOCK_EX | LOCK_NB)) {
            fclose($fh);

            return false;
        }
        ftruncate($fh, 0);
        fwrite($fh, (string) getmypid() . "\n" . gmdate('c') . "\n");
        fflush($fh);
        $this->lockFh = $fh;

        return true;
    }

    private function releaseLock(): void
    {
        if (is_resource($this->lockFh)) {
            @flock($this->lockFh, LOCK_UN);
            @fclose($this->lockFh);
            $this->lockFh = null;
        }
        $stop = HybridRuntime::branchStorageDir() . '/hybrid-sync.stop';
        if (is_file($stop)) {
            @unlink($stop);
        }
    }

    private function shouldStopFromFile(): bool
    {
        return is_file(HybridRuntime::branchStorageDir() . '/hybrid-sync.stop');
    }

    private function installSignalHandlers(): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }
        pcntl_async_signals(true);
        $stop = function (): void {
            $this->stopping = true;
        };
        if (defined('SIGTERM')) {
            pcntl_signal(SIGTERM, $stop);
        }
        if (defined('SIGINT')) {
            pcntl_signal(SIGINT, $stop);
        }
    }

    private function openLog(): void
    {
        $dir = HybridRuntime::branchStorageDir() . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        $this->logFh = @fopen($dir . '/hybrid-sync.jsonl', 'ab');
    }

    private function closeLog(): void
    {
        if (is_resource($this->logFh)) {
            fclose($this->logFh);
            $this->logFh = null;
        }
    }

    /** @param array<string, mixed> $ctx */
    private function log(string $level, string $event, array $ctx = []): void
    {
        $row = [
            'ts' => gmdate('c'),
            'level' => $level,
            'event' => $event,
            'service' => 'rateb-hybrid-sync',
            'ctx' => $ctx,
        ];
        $line = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        if (is_resource($this->logFh)) {
            fwrite($this->logFh, $line);
            fflush($this->logFh);
        }
        // Also stderr for systemd/journald / WinSW
        fwrite(STDERR, $line);
    }

    private function sleepSeconds(int $seconds): void
    {
        $end = time() + max(1, $seconds);
        while (!$this->stopping && time() < $end) {
            if ($this->shouldStopFromFile()) {
                $this->stopping = true;
                break;
            }
            sleep(1);
        }
    }
}
