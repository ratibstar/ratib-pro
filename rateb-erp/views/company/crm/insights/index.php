<?php
declare(strict_types=1);
$data = $data ?? [];
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('crm_executive_insights')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="row g-3 mb-4">
        <?php foreach (($data['cards'] ?? []) as $card): ?>
        <div class="col-md-4 col-xl-2">
            <div class="border rounded p-3 h-100">
                <div class="small text-muted"><?php echo htmlspecialchars((string) ($card['type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars((string) ($card['severity'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($card['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="small"><?php echo htmlspecialchars((string) ($card['body'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="row g-3">
        <div class="col-lg-4">
            <h2 class="h5">Trend indicators</h2>
            <pre class="border rounded p-3 small bg-light"><?php echo htmlspecialchars(json_encode($data['trend_indicators'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}', ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
        <div class="col-lg-4">
            <h2 class="h5">Risk alerts</h2>
            <pre class="border rounded p-3 small bg-light" style="max-height:280px;overflow:auto"><?php echo htmlspecialchars(json_encode($data['risk_alerts'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
        <div class="col-lg-4">
            <h2 class="h5">Growth opportunities</h2>
            <pre class="border rounded p-3 small bg-light" style="max-height:280px;overflow:auto"><?php echo htmlspecialchars(json_encode($data['growth_opportunities'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
    </div>
    <h2 class="h5 mt-4">Stored insights</h2>
    <?php foreach (($data['stored'] ?? []) as $ins): ?>
    <div class="border rounded p-3 mb-2 d-flex justify-content-between gap-2">
        <div>
            <div class="fw-semibold"><?php echo htmlspecialchars((string) ($ins['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="small text-muted"><?php echo htmlspecialchars((string) (($ins['insight_type'] ?? '') . ' · ' . ($ins['severity'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <?php if (!empty($canManage)): ?>
        <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/insights') . '/' . (int) $ins['id'] . '/dismiss'), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
            <button class="btn btn-sm btn-outline-secondary" type="submit">Dismiss</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
