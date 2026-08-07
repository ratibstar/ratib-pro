<?php
declare(strict_types=1);
$data = $data ?? [];
$activity = $activity ?? [];
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('crm_intelligence_layer')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="get" class="row g-2 mb-4">
        <div class="col-auto">
            <select name="pipeline_id" class="form-select form-select-sm">
                <option value="0">Pipeline</option>
                <?php foreach (($pipelines ?? []) as $p): ?>
                <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($pipeline_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? $p['id']), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string) ($date_from ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-auto"><input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string) ($date_to ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary" type="submit"><?php echo htmlspecialchars(__('filter'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_growth_trends'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5"><?php echo htmlspecialchars((string) (($data['sales_trends']['direction'] ?? 'stable')), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_customer_risk'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5"><?php echo count($data['customer_risk_signals'] ?? []); ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_pipeline_anomalies'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5"><?php echo count($data['pipeline_anomalies'] ?? []); ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_activity_patterns'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5"><?php echo (int) (($activity['sales_engagement']['engagement_rate'] ?? 0)); ?>%</div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_scoring_evolution'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <pre class="border rounded p-3 small bg-light" style="max-height:280px;overflow:auto"><?php echo htmlspecialchars(json_encode($data['scoring_evolution'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_pipeline_anomalies'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <pre class="border rounded p-3 small bg-light" style="max-height:280px;overflow:auto"><?php echo htmlspecialchars(json_encode($data['pipeline_anomalies'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_customer_risk'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <pre class="border rounded p-3 small bg-light" style="max-height:280px;overflow:auto"><?php echo htmlspecialchars(json_encode($data['customer_risk_signals'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
        <div class="col-lg-6">
            <h2 class="h5">Activity intelligence</h2>
            <pre class="border rounded p-3 small bg-light" style="max-height:280px;overflow:auto"><?php echo htmlspecialchars(json_encode([
                'patterns' => $activity['activity_patterns'] ?? [],
                'delays' => $activity['response_delays'] ?? [],
                'engagement' => $activity['sales_engagement'] ?? [],
                'reps' => array_slice($activity['rep_effectiveness'] ?? [], 0, 10),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '{}', ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
    </div>
</div>
