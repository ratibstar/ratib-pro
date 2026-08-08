<?php
declare(strict_types=1);
$freshness = $freshness ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_duplicate_merge')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($canManage)): ?>
        <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/merge/freshness')), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
            <button class="btn btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('crm_run_freshness_scan'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
        <?php endif; ?>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_data_freshness'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars((string) ($freshness['freshness_score'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><div class="small text-muted">Stale leads / opps / cust</div><div class="fs-6"><?php echo (int) ($freshness['stale_leads'] ?? 0); ?> / <?php echo (int) ($freshness['stale_opportunities'] ?? 0); ?> / <?php echo (int) ($freshness['stale_customers'] ?? 0); ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_quality_score'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars((string) ($freshness['automated_quality_score'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5">Suggestions</h2>
            <?php foreach (($suggestions ?? []) as $s): ?>
            <div class="border rounded p-3 mb-2 small">
                <?php echo htmlspecialchars((string) (($s['entity_type'] ?? '') . ' keep #' . ($s['target_id'] ?? '') . ' ← #' . ($s['source_id'] ?? '') . ' · ' . ($s['match'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                <?php if (!empty($canManage)): ?>
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/merge/request')), ENT_QUOTES, 'UTF-8'); ?>" class="mt-2 d-flex gap-2">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="entity_type" value="<?php echo htmlspecialchars((string) ($s['entity_type'] ?? 'lead'), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="source_id" value="<?php echo (int) ($s['source_id'] ?? 0); ?>">
                    <input type="hidden" name="target_id" value="<?php echo (int) ($s['target_id'] ?? 0); ?>">
                    <button class="btn btn-sm btn-outline-primary" type="submit">Request merge</button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (($suggestions ?? []) === []): ?><p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        </div>
        <div class="col-lg-6">
            <h2 class="h5">Pending merges</h2>
            <?php foreach (($pending ?? []) as $p): ?>
            <div class="border rounded p-3 mb-2">
                <div class="fw-semibold"><?php echo htmlspecialchars((string) (($p['entity_type'] ?? '') . ' #' . ($p['source_id'] ?? '') . ' → #' . ($p['target_id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($canManage)): ?>
                <div class="d-flex gap-2 mt-2">
                    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/merge') . '/' . (int) $p['id'] . '/execute'), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <button class="btn btn-sm btn-success" type="submit">Execute</button>
                    </form>
                    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/merge') . '/' . (int) $p['id'] . '/reject'), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Reject</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (($pending ?? []) === []): ?><p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <h2 class="h5 mt-4"><?php echo htmlspecialchars(__('crm_freshness_history'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php $hist = is_array($freshness_history ?? null) ? $freshness_history : []; ?>
            <div class="border rounded p-3" style="max-height:220px;overflow:auto">
                <?php if ($hist === []): ?>
                    <?php require __DIR__ . '/../partials/empty.php'; ?>
                <?php else: ?>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($hist as $h): ?>
                        <li class="mb-2 pb-2 border-bottom">
                            <?php echo htmlspecialchars((string) ($h['created_at'] ?? $h['checked_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            · score <?php echo htmlspecialchars((string) ($h['freshness_score'] ?? $h['score'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
