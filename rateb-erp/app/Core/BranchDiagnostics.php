<?php
declare(strict_types=1);

namespace Rateb\App\Core;

/**
 * Phase D — Appliance diagnostics (read-only probes; Core only).
 */
final class BranchDiagnostics
{
    /**
     * @return array{ok:bool,checks:list<array{id:string,ok:bool,detail:string}>,health:string}
     */
    public function run(): array
    {
        $checks = [];
        $add = static function (string $id, bool $ok, string $detail) use (&$checks): void {
            $checks[] = compact('id', 'ok', 'detail');
        };

        $add('php', version_compare(PHP_VERSION, BranchApplianceInstaller::MIN_PHP, '>='), PHP_VERSION);

        $sqliteExt = extension_loaded('pdo_sqlite') && extension_loaded('sqlite3');
        $add('sqlite', $sqliteExt, $sqliteExt ? 'pdo_sqlite+sqlite3' : 'missing');

        BranchAppliancePaths::ensureLayout();
        $root = BranchAppliancePaths::root();
        $add('filesystem', is_dir($root) && is_writable($root), $root);

        $diskFree = @disk_free_space($root);
        $diskTotal = @disk_total_space($root);
        $diskOk = is_float($diskFree) && $diskFree > 50 * 1024 * 1024;
        $add('disk_space', $diskOk, sprintf('free=%s total=%s', self::bytes($diskFree), self::bytes($diskTotal)));

        $memLimit = ini_get('memory_limit') ?: 'unknown';
        $add('memory', true, 'limit=' . $memLimit . ' peak=' . self::bytes(memory_get_peak_usage(true)));

        $add('cpu', true, 'cores=' . (string) (function_exists('swoole_cpu_num') ? swoole_cpu_num() : (getenv('NUMBER_OF_PROCESSORS') ?: 'n/a')));

        $add('permissions', is_writable($root . '/logs') && is_writable($root . '/backups'), 'logs+backups writable');

        $internet = $this->probeInternet();
        $add('internet', $internet['ok'], $internet['detail']);

        $cloud = $this->probeCloudMysql();
        $add('cloud_connectivity', $cloud['ok'] || HybridSyncConfig::sinkMode() === 'mirror', $cloud['detail']);
        $add('mysql_connectivity', $cloud['ok'] || HybridSyncConfig::sinkMode() !== 'mysql', $cloud['detail']);

        $runtime = HybridRuntime::snapshot();
        $add('hybrid_runtime', ($runtime['mode'] ?? '') === 'branch' && !empty($runtime['sqlite_extension']), json_encode($runtime) ?: '');

        $syncOk = false;
        $syncDetail = 'not_branch';
        $outboxDetail = 'n/a';
        $pending = 0;
        $conflicts = 0;
        $audit = 0;
        if (HybridRuntime::shouldUseSqlite() && is_file(HybridRuntime::sqlitePath())) {
            try {
                $pdo = Database::connection();
                $syncOk = HybridSyncConfig::enabled();
                $status = (new HybridSyncEngine())->status($pdo);
                $pending = (int) ($status['outbox']['pending'] ?? 0);
                $conflicts = (int) ($status['outbox']['conflict'] ?? 0);
                $outboxDetail = json_encode($status['outbox'] ?? []) ?: '';
                $audit = (int) $pdo->query('SELECT COUNT(*) FROM rateb_sync_audit')->fetchColumn();
                $syncDetail = 'sink=' . ($status['sink'] ?? '') . ' online=' . (!empty($status['online']) ? '1' : '0');
            } catch (\Throwable $e) {
                $syncDetail = $e->getMessage();
            }
        }
        $add('hybrid_sync', $syncOk, $syncDetail);
        $add('outbox', $syncOk, $outboxDetail);
        $add('pending_sync', true, 'pending=' . $pending);
        $add('conflict_queue', true, 'conflict=' . $conflicts);
        $add('audit', $audit >= 0, 'rows=' . $audit);

        $encOk = false;
        try {
            $enc = HybridSyncCrypto::encrypt('diag');
            $encOk = HybridSyncCrypto::decrypt($enc) === 'diag';
        } catch (\Throwable $e) {
            $encOk = false;
        }
        $add('encryption', $encOk, $encOk ? 'encrypt/decrypt ok' : 'failed');

        $lock = $root . '/hybrid-sync.daemon.lock';
        $serviceRunning = is_file($lock) && filesize($lock) > 0;
        // flock held by another process is "running"; absence is ok for diagnostics
        $add('service_status', true, $serviceRunning ? 'lock_present' : 'not_running_or_unlocked');

        $failed = 0;
        foreach ($checks as $c) {
            if (!$c['ok']) {
                $failed++;
            }
        }
        $health = $failed === 0 ? 'green' : ($failed <= 2 ? 'amber' : 'red');
        $result = ['ok' => $failed === 0, 'checks' => $checks, 'health' => $health, 'failed' => $failed];
        @file_put_contents(
            $root . '/diagnostics/last-diagnostics.json',
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        return $result;
    }

    /** @return array{ok:bool,detail:string} */
    private function probeInternet(): array
    {
        if (HybridSyncConfig::sinkMode() === 'mirror') {
            return ['ok' => true, 'detail' => 'mirror_mode_skip'];
        }
        $fp = @fsockopen('1.1.1.1', 53, $errno, $errstr, 1.5);
        if (is_resource($fp)) {
            fclose($fp);

            return ['ok' => true, 'detail' => 'dns_53_ok'];
        }

        return ['ok' => false, 'detail' => "unreachable ({$errno}) {$errstr}"];
    }

    /** @return array{ok:bool,detail:string} */
    private function probeCloudMysql(): array
    {
        if (HybridSyncConfig::sinkMode() === 'mirror') {
            return ['ok' => true, 'detail' => 'mirror'];
        }
        if (!HybridSyncConfig::cloudMysqlConfigured()) {
            return ['ok' => false, 'detail' => 'mysql_not_configured'];
        }
        try {
            (new HybridSyncSink())->connection()->query('SELECT 1');

            return ['ok' => true, 'detail' => 'mysql_select_1'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => $e->getMessage()];
        }
    }

    private static function bytes(float|int|false|null $n): string
    {
        if (!is_numeric($n)) {
            return 'n/a';
        }
        $n = (float) $n;
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($n >= 1024 && $i < count($u) - 1) {
            $n /= 1024;
            $i++;
        }

        return round($n, 1) . $u[$i];
    }
}
