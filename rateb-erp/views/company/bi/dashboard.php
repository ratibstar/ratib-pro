<?php declare(strict_types=1); /** @var array<string, array<string, int>> $board */ /** @var list<array<string, mixed>> $timeline */ ?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('bi_platform')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="d-flex flex-wrap gap-2 mb-4">
        <?php foreach ([
            'bi/dashboards' => 'bi_dashboards', 'bi/kpis' => 'bi_kpis', 'bi/reports' => 'bi_reports',
            'bi/widgets' => 'bi_widgets', 'bi/datasets' => 'bi_datasets', 'bi/alerts' => 'bi_alerts',
            'bi/schedules' => 'bi_schedules', 'bi/exports' => 'bi_exports', 'bi/trends' => 'bi_trends',
            'bi/forecasts' => 'bi_forecasts', 'bi/scopes' => 'bi_scopes', 'bi/analytics' => 'bi_analytics',
            'bi/timeline' => 'bi_timeline',
        ] as $route => $label): ?>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route($route)), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__($label), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endforeach; ?>
    </div>
    <h2 class="h5 mb-3"><?php echo htmlspecialchars(__('bi_board'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <?php foreach (($board ?? []) as $entity => $counts): ?>
    <div class="mb-3">
        <div class="text-muted small mb-2"><?php echo htmlspecialchars((string) $entity, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="row g-3">
            <?php foreach ((array) $counts as $st => $cnt): ?>
            <div class="col-6 col-md-4"><div class="border rounded p-3"><div class="text-muted small"><?php echo htmlspecialchars((string) $st, ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4 fw-semibold"><?php echo (int) $cnt; ?></div></div></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <h2 class="h5 mt-4 mb-3"><?php echo htmlspecialchars(__('bi_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <ul class="list-group list-group-flush border rounded">
        <?php foreach (($timeline ?? []) as $ev): ?>
            <li class="list-group-item"><div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div><div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></li>
        <?php endforeach; ?>
        <?php if (($timeline ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
    </ul>
</div>
