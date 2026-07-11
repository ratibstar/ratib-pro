<?php
declare(strict_types=1);

/** @var array<string, mixed> $snap */
$snap = $snap ?? [];
$qh = $snap['queue_health'] ?? [];
$devices = $snap['devices'] ?? [];
$sync = $snap['sync_metrics'] ?? [];
$conflicts = $snap['conflicts'] ?? [];
$retries = $snap['retries'] ?? [];
$replay = $snap['replay_history']['items'] ?? [];
$audit = $snap['audit_logs']['items'] ?? [];
$worker = $snap['background_worker'] ?? [];
$alerts = $snap['alerts']['items'] ?? [];
$perf = $snap['performance'] ?? [];
$ready = $snap['production_readiness'] ?? [];
$flags = $snap['flags'] ?? [];

$esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$badge = static function (string $sev): string {
    return match ($sev) {
        'critical' => 'danger',
        'high' => 'warning',
        'medium' => 'info',
        default => 'secondary',
    };
};
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?php echo $esc($title ?? 'Offline Operations'); ?></h1>
            <p class="text-muted mb-0 small">Read-only enterprise offline monitoring · generated <?php echo $esc($snap['generated_at'] ?? ''); ?></p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-<?php echo !empty($snap['master_enabled']) ? 'success' : 'secondary'; ?>">master <?php echo !empty($snap['master_enabled']) ? 'ON' : 'OFF'; ?></span>
            <span class="badge bg-<?php echo !empty($snap['monitoring_enabled']) ? 'primary' : 'secondary'; ?>">monitoring <?php echo !empty($snap['monitoring_enabled']) ? 'ON' : 'OFF'; ?></span>
            <span class="badge bg-<?php echo (($ready['verdict'] ?? '') === 'READY') ? 'success' : ((($ready['verdict'] ?? '') === 'CONDITIONAL') ? 'warning' : 'danger'); ?>">
                readiness <?php echo $esc($ready['verdict'] ?? 'n/a'); ?> (<?php echo $esc($ready['score'] ?? '0'); ?>/10)
            </span>
        </div>
    </div>

    <?php if (!empty($snap['migration_required'])): ?>
        <div class="alert alert-warning">Offline sync tables are not migrated on this database.</div>
    <?php endif; ?>

    <!-- Alerts -->
    <section class="mb-4" id="alerts">
        <h2 class="h5">Alerting</h2>
        <?php if ($alerts === []): ?>
            <div class="alert alert-success py-2">No active offline alerts.</div>
        <?php else: ?>
            <div class="list-group mb-2">
                <?php foreach ($alerts as $a): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><?php echo $esc($a['message'] ?? ''); ?></span>
                        <span class="badge bg-<?php echo $badge((string) ($a['severity'] ?? 'low')); ?>"><?php echo $esc($a['code'] ?? ''); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Queue health + performance + readiness -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small">Queue depth</div>
                <div class="fs-3 fw-semibold"><?php echo (int) ($qh['depth'] ?? 0); ?></div>
                <div class="small">pending <?php echo (int) ($qh['pending'] ?? 0); ?> · failed <?php echo (int) ($qh['failed'] ?? 0); ?> · conflict <?php echo (int) ($qh['conflict'] ?? 0); ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small">Synced 24h</div>
                <div class="fs-3 fw-semibold"><?php echo (int) ($perf['synced_24h'] ?? 0); ?></div>
                <div class="small">success <?php echo $esc($perf['success_rate_24h_pct'] ?? 0); ?>%</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small">Open conflicts</div>
                <div class="fs-3 fw-semibold"><?php echo (int) ($conflicts['open'] ?? 0); ?></div>
                <div class="small">resolved <?php echo (int) ($conflicts['resolved'] ?? 0); ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small">Devices</div>
                <div class="fs-3 fw-semibold"><?php echo (int) ($devices['total'] ?? 0); ?></div>
                <div class="small">stale active <?php echo (int) ($devices['stale_active'] ?? 0); ?></div>
            </div>
        </div>
    </div>

    <!-- Production readiness -->
    <section class="mb-4" id="readiness">
        <h2 class="h5">Production readiness</h2>
        <ul class="list-group">
            <?php foreach (($ready['checks'] ?? []) as $c): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <span><?php echo $esc($c['label'] ?? ''); ?></span>
                    <span class="badge bg-<?php echo !empty($c['ok']) ? 'success' : 'danger'; ?>"><?php echo !empty($c['ok']) ? 'PASS' : 'FAIL'; ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <!-- Flags / worker -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <h2 class="h5">Feature flags</h2>
            <table class="table table-sm">
                <tbody>
                <?php foreach ($flags as $k => $v): ?>
                    <tr><td><?php echo $esc($k); ?></td><td><?php echo $v ? 'ON' : 'OFF'; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="col-lg-6">
            <h2 class="h5">Background worker metrics</h2>
            <table class="table table-sm">
                <tbody>
                    <tr><td>Pending backlog</td><td><?php echo (int) ($worker['pending_backlog'] ?? 0); ?></td></tr>
                    <tr><td>Synced last hour</td><td><?php echo (int) ($worker['synced_last_hour'] ?? 0); ?></td></tr>
                    <tr><td>Batch limit</td><td><?php echo (int) ($worker['batch_limit'] ?? 50); ?></td></tr>
                    <tr><td>Idle</td><td><?php echo !empty($worker['idle']) ? 'yes' : 'no'; ?></td></tr>
                    <tr><td>Inv / HR / Proc</td><td>
                        <?php echo !empty($worker['inventory_enabled']) ? 'Inv ON' : 'Inv OFF'; ?> ·
                        <?php echo !empty($worker['hr_enabled']) ? 'HR ON' : 'HR OFF'; ?> ·
                        <?php echo !empty($worker['procurement_enabled']) ? 'Proc ON' : 'Proc OFF'; ?>
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Queue by module -->
    <section class="mb-4" id="queue">
        <h2 class="h5">Queue health by module</h2>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead><tr><th>Module</th><th>Status</th><th>Count</th></tr></thead>
                <tbody>
                <?php foreach (($qh['by_module'] ?? []) as $row): ?>
                    <tr>
                        <td><?php echo $esc($row['module'] ?? ''); ?></td>
                        <td><?php echo $esc($row['status'] ?? ''); ?></td>
                        <td><?php echo (int) ($row['count'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (($qh['by_module'] ?? []) === []): ?>
                    <tr><td colspan="3" class="text-muted">No queue rows</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Conflicts + retries -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6" id="conflicts">
            <h2 class="h5">Conflict dashboard</h2>
            <div class="table-responsive" style="max-height:320px;overflow:auto">
                <table class="table table-sm">
                    <thead><tr><th>ID</th><th>Reason</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach (($conflicts['items'] ?? []) as $row): ?>
                        <tr>
                            <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                            <td><?php echo $esc($row['reason'] ?? ''); ?></td>
                            <td><?php echo $esc($row['status'] ?? ''); ?></td>
                            <td><?php echo $esc($row['created_at'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-6" id="retries">
            <h2 class="h5">Retry dashboard</h2>
            <p class="small text-muted">High retry (≥3): <?php echo (int) ($retries['high_retry_count'] ?? 0); ?></p>
            <div class="table-responsive" style="max-height:320px;overflow:auto">
                <table class="table table-sm">
                    <thead><tr><th>ID</th><th>Module</th><th>Action</th><th>Retries</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach (($retries['items'] ?? []) as $row): ?>
                        <tr>
                            <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                            <td><?php echo $esc($row['module'] ?? ''); ?></td>
                            <td><?php echo $esc($row['action'] ?? ''); ?></td>
                            <td><?php echo (int) ($row['retry_count'] ?? 0); ?></td>
                            <td><?php echo $esc($row['status'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Devices -->
    <section class="mb-4" id="devices">
        <h2 class="h5">Device status</h2>
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead><tr><th>Device</th><th>Label</th><th>Status</th><th>Last seen</th></tr></thead>
                <tbody>
                <?php foreach (($devices['recent'] ?? []) as $row): ?>
                    <tr>
                        <td><?php echo $esc($row['device_id'] ?? ''); ?></td>
                        <td><?php echo $esc($row['label'] ?? ''); ?></td>
                        <td><?php echo $esc($row['status'] ?? ''); ?></td>
                        <td><?php echo $esc($row['last_seen_at'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (($devices['recent'] ?? []) === []): ?>
                    <tr><td colspan="4" class="text-muted">No devices registered</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Replay history + audit -->
    <div class="row g-3 mb-4">
        <div class="col-lg-6" id="replay">
            <h2 class="h5">Replay history</h2>
            <div class="table-responsive" style="max-height:360px;overflow:auto">
                <table class="table table-sm">
                    <thead><tr><th>ID</th><th>Module</th><th>Action</th><th>Status</th><th>Synced</th></tr></thead>
                    <tbody>
                    <?php foreach ($replay as $row): ?>
                        <tr>
                            <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                            <td><?php echo $esc($row['module'] ?? ''); ?></td>
                            <td><?php echo $esc($row['action'] ?? ''); ?></td>
                            <td><?php echo $esc($row['status'] ?? ''); ?></td>
                            <td><?php echo $esc($row['synced_at'] ?? $row['created_at'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-6" id="audit">
            <h2 class="h5">Offline Audit logs</h2>
            <div class="table-responsive" style="max-height:360px;overflow:auto">
                <table class="table table-sm">
                    <thead><tr><th>When</th><th>Type</th><th>Detail</th></tr></thead>
                    <tbody>
                    <?php foreach ($audit as $row): ?>
                        <tr>
                            <td><?php echo $esc($row['at'] ?? ''); ?></td>
                            <td><?php echo $esc(($row['type'] ?? '') . '/' . ($row['action'] ?? '')); ?></td>
                            <td><?php echo $esc($row['detail'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Performance + sync metrics -->
    <section class="mb-4" id="performance">
        <h2 class="h5">Performance metrics</h2>
        <p class="small mb-2">
            success 24h: <?php echo $esc($perf['success_rate_24h_pct'] ?? 0); ?>% ·
            synced 24h: <?php echo (int) ($perf['synced_24h'] ?? 0); ?> ·
            failed 24h: <?php echo (int) ($perf['failed_24h'] ?? 0); ?> ·
            throughput/hr est: <?php echo $esc($perf['throughput_per_hour_est'] ?? 0); ?>
        </p>
    </section>

    <section class="mb-4" id="sync">
        <h2 class="h5">Synchronization metrics</h2>
        <p class="small">7d synced: <?php echo (int) ($sync['synced_7d'] ?? 0); ?> · avg retry: <?php echo $esc($sync['avg_retry'] ?? 0); ?></p>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead><tr><th>Action (7d synced)</th><th>Count</th></tr></thead>
                <tbody>
                <?php foreach (($sync['by_action'] ?? []) as $action => $count): ?>
                    <tr><td><?php echo $esc($action); ?></td><td><?php echo (int) $count; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
