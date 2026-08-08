<?php
declare(strict_types=1);
$data = $data ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_data_quality_engine')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($canManage)): ?>
        <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/data-quality/scan')), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
            <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('crm_run_quality_scan'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
        <?php endif; ?>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_quality_score'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars((string) ($data['quality_score'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_completeness_score'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars((string) ($data['completeness_score'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_open_issues'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo (int) ($data['open_issues'] ?? 0); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted">Dup / Missing / Own</div><div class="fs-6"><?php echo (int) ($data['duplicates'] ?? 0); ?> / <?php echo (int) ($data['missing'] ?? 0); ?> / <?php echo (int) ($data['ownership'] ?? 0); ?></div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_quality_trend'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php $trend = is_array($data['trend'] ?? null) ? $data['trend'] : []; ?>
            <div class="border rounded p-3" style="max-height:280px;overflow:auto">
                <?php if ($trend === []): ?>
                    <?php require __DIR__ . '/../partials/empty.php'; ?>
                <?php else: ?>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($trend as $t): ?>
                            <?php if (!is_array($t)) { continue; } ?>
                            <li class="mb-2 pb-2 border-bottom">
                                <?php echo htmlspecialchars((string) ($t['period'] ?? $t['created_at'] ?? $t['date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                · <?php echo htmlspecialchars((string) ($t['quality_score'] ?? $t['score'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5">Resolution tracking</h2>
            <?php foreach (($data['resolution'] ?? []) as $r): ?>
            <div class="border rounded p-2 mb-2 small">
                #<?php echo (int) ($r['id'] ?? 0); ?> · <?php echo htmlspecialchars((string) (($r['issue_code'] ?? '') . ' · ' . ($r['resolution_note'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                <div class="text-muted"><?php echo htmlspecialchars((string) ($r['resolved_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (($data['resolution'] ?? []) === []): ?><p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        </div>
    </div>
</div>
