<?php
declare(strict_types=1);
$data = $data ?? [];
$trends = is_array($data['trend_indicators'] ?? null) ? $data['trend_indicators'] : [];
$risks = is_array($data['risk_alerts'] ?? null) ? $data['risk_alerts'] : [];
$growth = is_array($data['growth_opportunities'] ?? null) ? $data['growth_opportunities'] : [];
$stored = is_array($data['stored'] ?? null) ? $data['stored'] : [];
$cards = is_array($data['cards'] ?? null) ? $data['cards'] : [];
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('crm_executive_insights')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="row g-3 mb-4">
        <?php foreach ($cards as $card): ?>
        <div class="col-md-4 col-xl-2">
            <div class="border rounded p-3 h-100">
                <div class="small text-muted"><?php echo htmlspecialchars((string) ($card['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($card['severity'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($card['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="small"><?php echo htmlspecialchars((string) ($card['body'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if ($cards === []): ?>
        <div class="col-12 border rounded px-3"><?php require __DIR__ . '/../../partials/crm-empty.php'; ?></div>
        <?php endif; ?>
    </div>
    <div class="row g-3">
        <div class="col-lg-4">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_trend_indicators'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="border rounded p-3">
                <?php if ($trends === []): ?>
                    <?php require __DIR__ . '/../../partials/crm-empty.php'; ?>
                <?php else: ?>
                    <dl class="row mb-0 small">
                        <?php foreach ($trends as $k => $v): ?>
                        <dt class="col-6 text-muted"><?php echo htmlspecialchars((string) $k, ENT_QUOTES, 'UTF-8'); ?></dt>
                        <dd class="col-6"><?php echo htmlspecialchars(is_scalar($v) ? (string) $v : '', ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-4">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_risk_alerts'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="border rounded p-3" style="max-height:280px;overflow:auto">
                <?php if ($risks === []): ?>
                    <?php require __DIR__ . '/../../partials/crm-empty.php'; ?>
                <?php else: ?>
                    <ul class="list-unstyled mb-0 small">
                        <?php foreach ($risks as $r): ?>
                        <li class="mb-2 pb-2 border-bottom">
                            <div class="fw-semibold">#<?php echo (int) ($r['id'] ?? 0); ?> · <?php echo htmlspecialchars((string) ($r['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="text-muted"><?php echo htmlspecialchars(implode(', ', array_map('strval', $r['signals'] ?? [])), ENT_QUOTES, 'UTF-8'); ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-4">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_growth_opportunities'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="border rounded p-3" style="max-height:280px;overflow:auto">
                <?php if ($growth === []): ?>
                    <?php require __DIR__ . '/../../partials/crm-empty.php'; ?>
                <?php else: ?>
                    <ul class="list-unstyled mb-0 small">
                        <?php foreach ($growth as $g): ?>
                        <li class="mb-2 pb-2 border-bottom">
                            <div class="fw-semibold"><?php echo htmlspecialchars((string) ($g['name'] ?? ('#' . ($g['id'] ?? ''))), ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="text-muted"><?php echo htmlspecialchars((string) (($g['probability_percent'] ?? $g['score'] ?? '') . ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <h2 class="h5 mt-4"><?php echo htmlspecialchars(__('crm_stored_insights'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <?php if ($stored === []): ?>
        <div class="border rounded px-3"><?php require __DIR__ . '/../../partials/crm-empty.php'; ?></div>
    <?php endif; ?>
    <?php foreach ($stored as $ins): ?>
    <div class="border rounded p-3 mb-2 d-flex justify-content-between gap-2">
        <div>
            <div class="fw-semibold"><?php echo htmlspecialchars((string) ($ins['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="small text-muted"><?php echo htmlspecialchars((string) (($ins['insight_type'] ?? '') . ' · ' . ($ins['severity'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <?php if (!empty($canManage)): ?>
        <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/insights') . '/' . (int) $ins['id'] . '/dismiss'), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
            <button class="btn btn-sm btn-outline-secondary" type="submit"><?php echo htmlspecialchars(__('dismiss'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
