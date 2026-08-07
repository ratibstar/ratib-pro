<?php
declare(strict_types=1);
$data = $data ?? [];
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('crm_executive_cockpit')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="get" class="row g-2 mb-4">
        <div class="col-auto"><input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string) ($date_from ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-auto"><input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string) ($date_to ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-auto">
            <select name="pipeline_id" class="form-select form-select-sm">
                <option value="0">Pipeline</option>
                <?php foreach (($pipelines ?? []) as $p): ?>
                <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($pipeline_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? $p['id']), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><input type="number" name="team_id" class="form-control form-control-sm" placeholder="team_id" value="<?php echo (int) ($team_id ?? 0) ?: ''; ?>"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary" type="submit"><?php echo htmlspecialchars(__('filter'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted">Pipeline value</div><div class="fs-4"><?php echo number_format((float) ($data['pipeline_value'] ?? 0), 2); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_forecast_confidence'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo number_format((float) ($data['forecast_confidence'] ?? 0), 1); ?>%</div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted">Win rate</div><div class="fs-4"><?php echo number_format((float) ($data['win_rate'] ?? 0), 1); ?>%</div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_sales_velocity'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo number_format((float) ($data['sales_velocity'] ?? 0), 1); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_customer_risk'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo is_array($data['customer_risk']['at_risk'] ?? null) ? count($data['customer_risk']['at_risk']) : 0; ?></div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_growth_trends'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <pre class="border rounded p-3 small bg-light" style="max-height:320px;overflow:auto"><?php echo htmlspecialchars(json_encode($data['growth_trends'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_win_loss_intel'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <pre class="border rounded p-3 small bg-light" style="max-height:320px;overflow:auto"><?php echo htmlspecialchars(json_encode($data['win_loss'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
    </div>
</div>
