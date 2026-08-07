<?php
declare(strict_types=1);
$data = $data ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_sales_performance_mgmt')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <form method="get" class="d-flex gap-2">
            <input class="form-control" type="date" name="date_from" value="<?php echo htmlspecialchars((string) ($date_from ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <input class="form-control" type="date" name="date_to" value="<?php echo htmlspecialchars((string) ($date_to ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
    </div>
    <?php $sla = $data['response_sla'] ?? []; $eff = $data['activity_effectiveness'] ?? []; ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_response_sla'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars((string) ($sla['sla_pct'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>%</div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_activity_intelligence'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars((string) ($eff['activity_effectiveness_pct'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>%</div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted">Activities</div><div class="fs-4"><?php echo (int) ($eff['activity_count'] ?? 0); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted">Avg delay (h)</div><div class="fs-4"><?php echo htmlspecialchars((string) ($sla['avg_delay_hours'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_rep_productivity'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Rep</th><th>Acts</th><th>Tasks</th><th>Won</th><th>Score</th></tr></thead><tbody>
            <?php foreach (($data['rep_productivity'] ?? []) as $row): ?>
                <tr><td>#<?php echo (int) $row['owner_user_id']; ?></td><td><?php echo (int) $row['activities']; ?></td><td><?php echo (int) $row['tasks_completed']; ?></td><td><?php echo (int) $row['opps_won']; ?></td><td><?php echo htmlspecialchars((string) $row['productivity_score'], ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endforeach; ?>
            <?php if (($data['rep_productivity'] ?? []) === []): ?><tr><td colspan="5" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_pipeline_contribution'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Rep</th><th>Open</th><th>%</th></tr></thead><tbody>
            <?php foreach (($data['pipeline_contribution'] ?? []) as $row): ?>
                <tr><td>#<?php echo (int) $row['owner_user_id']; ?></td><td><?php echo htmlspecialchars(number_format($row['open_amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string) $row['contribution_pct'], ENT_QUOTES, 'UTF-8'); ?>%</td></tr>
            <?php endforeach; ?>
            <?php if (($data['pipeline_contribution'] ?? []) === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </div>
</div>
