<?php
declare(strict_types=1);
$data = $data ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_revenue_intelligence')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <form method="get" class="d-flex flex-wrap gap-2">
            <select class="form-select" name="pipeline_id">
                <option value="0"><?php echo htmlspecialchars(__('crm_pipeline'), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php foreach (($pipelines ?? []) as $p): ?>
                <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($pipeline_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
            <input class="form-control" type="date" name="date_from" value="<?php echo htmlspecialchars((string) ($date_from ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <input class="form-control" type="date" name="date_to" value="<?php echo htmlspecialchars((string) ($date_to ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
    </div>
    <?php $pipe = $data['pipeline'] ?? []; $wl = $data['win_loss'] ?? []; $funnel = $data['funnel'] ?? []; $cycle = $data['sales_cycle'] ?? []; ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_weighted_pipeline'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars(number_format((float) ($pipe['weighted_amount'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_revenue_tracked'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars(number_format((float) ($pipe['tracked_revenue'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_win_rate'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars((string) ($wl['win_rate'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>%</div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_sales_cycle'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars((string) ($cycle['avg_days'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>d</div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_historical_trends'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th><?php echo htmlspecialchars(__('crm_period'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('crm_won'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('crm_lost'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead><tbody>
            <?php foreach (($data['trends'] ?? []) as $row): ?>
                <tr><td><?php echo htmlspecialchars($row['period_key'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars(number_format($row['won_amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars(number_format($row['lost_amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endforeach; ?>
            <?php if (($data['trends'] ?? []) === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_win_loss_intel'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <li class="list-group-item d-flex justify-content-between"><span><?php echo htmlspecialchars(__('crm_won'), ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo (int) ($wl['won_count'] ?? 0); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span><?php echo htmlspecialchars(__('crm_lost'), ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo (int) ($wl['lost_count'] ?? 0); ?></span></li>
            </ul>
            <h3 class="h6"><?php echo htmlspecialchars(__('crm_loss_reason'), ENT_QUOTES, 'UTF-8'); ?></h3>
            <ul class="list-group">
                <?php foreach (($wl['top_loss_reasons'] ?? []) as $r): ?>
                <li class="list-group-item d-flex justify-content-between"><span><?php echo htmlspecialchars($r['reason'], ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo (int) $r['count']; ?></span></li>
                <?php endforeach; ?>
                <?php if (($wl['top_loss_reasons'] ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_conversion_funnel'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <li class="list-group-item d-flex justify-content-between"><span><?php echo htmlspecialchars(__('crm_leads'), ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo (int) ($funnel['leads'] ?? 0); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span><?php echo htmlspecialchars(__('crm_opportunities'), ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo (int) ($funnel['opportunities'] ?? 0); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span><?php echo htmlspecialchars(__('crm_quotes_accepted'), ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo (int) ($funnel['quotations_accepted'] ?? 0); ?></span></li>
                <li class="list-group-item d-flex justify-content-between"><span><?php echo htmlspecialchars(__('crm_customers'), ENT_QUOTES, 'UTF-8'); ?></span><span><?php echo (int) ($funnel['customers'] ?? 0); ?></span></li>
            </ul>
        </div>
    </div>
</div>
