<?php
declare(strict_types=1);

/** @var array<string, mixed> $status */
/** @var array<int, array<string, mixed>> $items */
/** @var array<int, array<string, mixed>> $conflicts */
/** @var array<string, string> $syncApi */
/** @var string $csrf */
$status = $status ?? [];
$items = $items ?? [];
$conflicts = $conflicts ?? [];
$syncApi = $syncApi ?? [];
$isScaffold = !empty($status['scaffold']);
$conflictsUnavailable = !empty($status['conflicts_migration_required']);
?>
<script type="application/json" id="rateb-pos-sync-config"><?php echo \Rateb\App\Pos\Support\PosView::escape(json_encode([
    'csrf' => $csrf ?? '',
    'api' => $syncApi,
    'i18n' => [
        'pos_online' => __('pos_online'),
        'pos_offline' => __('pos_offline'),
        'pos_sync_offline_blocked' => __('pos_sync_offline_blocked'),
        'pos_sync_process_done' => __('pos_sync_process_done', ['synced' => ':synced', 'failed' => ':failed']),
        'invalid_request' => __('invalid_request'),
    ],
], JSON_UNESCAPED_UNICODE)); ?></script>

<div class="rateb-pos-page" data-pos-sync-admin>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? ''); ?></h1>
        <div class="d-flex align-items-center gap-2">
            <?php if (!$isScaffold): ?>
                <button type="button" class="btn btn-sm btn-primary" data-pos-sync-process>
                    <?php echo __('pos_sync_process_pending'); ?>
                </button>
            <?php endif; ?>
            <span class="badge bg-success" data-pos-sync-connection><?php echo __('pos_online'); ?></span>
        </div>
    </div>

    <div class="alert alert-warning" data-pos-sync-alert hidden></div>

    <?php if ($isScaffold): ?>
        <div class="alert alert-warning"><?php echo __('pos_sync_migration_required'); ?></div>
    <?php elseif ($conflictsUnavailable): ?>
        <div class="alert alert-warning"><?php echo __('pos_sync_conflicts_migration_required'); ?></div>
        <div class="alert alert-info"><?php echo __('pos_sync_phase4_notice'); ?></div>
    <?php else: ?>
        <div class="alert alert-info"><?php echo __('pos_sync_phase4_notice'); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="rateb-card h-100">
                <div class="rateb-card-body text-center">
                    <div class="text-muted small"><?php echo __('pos_sync_queue_depth'); ?></div>
                    <div class="fs-3 fw-semibold"><?php echo (int) ($status['queue_depth'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="rateb-card h-100">
                <div class="rateb-card-body text-center">
                    <div class="text-muted small"><?php echo __('pos_sync_pending'); ?></div>
                    <div class="fs-3 fw-semibold"><?php echo (int) ($status['pending'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="rateb-card h-100">
                <div class="rateb-card-body text-center">
                    <div class="text-muted small"><?php echo __('pos_sync_conflicts'); ?></div>
                    <div class="fs-3 fw-semibold text-warning"><?php echo (int) ($status['conflict'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="rateb-card h-100">
                <div class="rateb-card-body text-center">
                    <div class="text-muted small"><?php echo __('pos_sync_last_sync'); ?></div>
                    <div class="fw-semibold"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($status['last_sync'] ?? '—')); ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($conflicts !== []): ?>
    <div class="rateb-card mb-4">
        <div class="rateb-card-header"><strong><?php echo __('pos_sync_open_conflicts'); ?></strong></div>
        <div class="rateb-card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php echo __('pos_context_terminal'); ?></th>
                            <th><?php echo __('pos_sync_client_id'); ?></th>
                            <th><?php echo __('pos_sync_conflict_reason'); ?></th>
                            <th><?php echo __('created_at'); ?></th>
                            <th><?php echo __('pos_actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conflicts as $conflict): ?>
                            <tr>
                                <td><?php echo (int) ($conflict['id'] ?? 0); ?></td>
                                <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($conflict['terminal_id'] ?? '—')); ?></td>
                                <td class="text-truncate" style="max-width: 180px;"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($conflict['idempotency_key'] ?? '')); ?></td>
                                <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($conflict['reason'] ?? '')); ?></td>
                                <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($conflict['created_at'] ?? '')); ?></td>
                                <td class="text-nowrap">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" data-pos-sync-resolve data-conflict-id="<?php echo (int) ($conflict['id'] ?? 0); ?>" data-resolution="accept_server"><?php echo __('pos_sync_accept_server'); ?></button>
                                    <button type="button" class="btn btn-outline-primary btn-sm ms-1" data-pos-sync-resolve data-conflict-id="<?php echo (int) ($conflict['id'] ?? 0); ?>" data-resolution="accept_client"><?php echo __('pos_sync_accept_client'); ?></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="rateb-card">
        <div class="rateb-card-header d-flex justify-content-between align-items-center">
            <strong><?php echo __('pos_sync_queue_items'); ?></strong>
            <span class="text-muted small"><?php echo __('pos_sync_synced_total'); ?>: <?php echo (int) ($status['synced'] ?? 0); ?></span>
        </div>
        <div class="rateb-card-body">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php echo __('pos_context_terminal'); ?></th>
                            <th><?php echo __('pos_sync_client_id'); ?></th>
                            <th><?php echo __('status'); ?></th>
                            <th><?php echo __('created_at'); ?></th>
                            <th><?php echo __('pos_sync_last_sync'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($items === []): ?>
                            <tr><td colspan="6" class="text-muted"><?php echo __('no_records'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo (int) ($item['id'] ?? 0); ?></td>
                                    <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($item['terminal_id'] ?? '—')); ?></td>
                                    <td class="text-truncate" style="max-width: 220px;" title="<?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($item['idempotency_key'] ?? '')); ?>">
                                        <?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($item['idempotency_key'] ?? '')); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $st = (string) ($item['status'] ?? '');
                                        $badge = match ($st) {
                                            'synced' => 'bg-success',
                                            'conflict' => 'bg-warning text-dark',
                                            'failed' => 'bg-danger',
                                            default => 'bg-secondary',
                                        };
                                        ?>
                                        <span class="badge <?php echo $badge; ?>"><?php echo \Rateb\App\Pos\Support\PosView::escape($st); ?></span>
                                    </td>
                                    <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($item['created_at'] ?? '')); ?></td>
                                    <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($item['synced_at'] ?? '—')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<link href="<?php echo rateb_pos_asset('css/pos-module.css'); ?>" rel="stylesheet">
<script src="<?php echo rateb_pos_asset('js/pos-sync-admin.js'); ?>"></script>
