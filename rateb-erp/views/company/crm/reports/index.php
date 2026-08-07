<?php
declare(strict_types=1);
$conversions = $conversions ?? [];
$funnel = $funnel ?? [];
$sources = $sources ?? [];
$performance = $performance ?? [];
$lost = $lost ?? [];
$forecast = $forecast ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_reports')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <div class="d-flex gap-2 flex-wrap">
            <form method="get" class="d-flex gap-2">
                <select class="form-select" name="pipeline_id" onchange="this.form.submit()">
                    <option value="0"><?php echo htmlspecialchars(__('crm_pipeline'), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php foreach (($pipelines ?? []) as $p): ?>
                    <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($pipeline_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if (!empty($canForecastManage)): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/reports/snapshot')), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="pipeline_id" value="<?php echo (int) ($pipeline_id ?? 0); ?>">
                <button class="btn btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('crm_forecast_snapshot'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <?php $engine = $engine ?? []; $quote_metrics = $quote_metrics ?? []; $revenue = $revenue ?? []; ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_kpi_conversion_rate'), ENT_QUOTES, 'UTF-8'); ?> (leads)</div><div class="fs-4"><?php echo htmlspecialchars((string) ($conversions['lead_conversion_rate'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>%</div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_weighted_pipeline'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars(number_format((float) ($engine['weighted_amount'] ?? $forecast['total_expected_revenue'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_quote_acceptance_rate'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars((string) ($quote_metrics['acceptance_rate'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>%</div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_revenue_tracked'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars(number_format((float) ($revenue['total'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_sales_funnel'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th><?php echo htmlspecialchars(__('crm_pipeline_stages'), ENT_QUOTES, 'UTF-8'); ?></th><th>#</th><th><?php echo htmlspecialchars(__('amount'), ENT_QUOTES, 'UTF-8'); ?></th><th>ER</th></tr></thead><tbody>
            <?php foreach ($funnel as $row): ?>
                <tr><td><?php echo htmlspecialchars($row['stage'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $row['count']; ?></td><td><?php echo htmlspecialchars(number_format($row['amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars(number_format($row['expected_revenue'], 2), ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endforeach; ?>
            <?php if ($funnel === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_lead_sources'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th>#</th></tr></thead><tbody>
            <?php foreach ($sources as $row): ?>
                <tr><td><?php echo htmlspecialchars($row['source'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $row['count']; ?></td></tr>
            <?php endforeach; ?>
            <?php if ($sources === []): ?><tr><td colspan="2" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_sales_performance'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Owner</th><th>Opps</th><th>Won</th><th>Amount</th><th>ER</th></tr></thead><tbody>
            <?php foreach ($performance as $row): ?>
                <tr><td>#<?php echo (int) $row['owner_user_id']; ?></td><td><?php echo (int) $row['opportunities']; ?></td><td><?php echo (int) $row['won']; ?></td><td><?php echo htmlspecialchars(number_format($row['amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars(number_format($row['expected_revenue'], 2), ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endforeach; ?>
            <?php if ($performance === []): ?><tr><td colspan="5" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_lost_opportunities'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('crm_loss_reason'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('amount'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead><tbody>
            <?php foreach ($lost as $row): ?>
                <tr>
                    <td><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/opportunities') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['opportunity_no'] ?? $row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                    <td><?php echo htmlspecialchars((string) ($row['loss_reason_name'] ?? $row['loss_notes'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['amount'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($lost === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_team_forecast'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Owner</th><th>Open</th><th>Weighted</th><th>Won</th></tr></thead><tbody>
            <?php foreach (($engine['by_owner'] ?? []) as $row): ?>
                <tr><td>#<?php echo (int) $row['owner_user_id']; ?></td><td><?php echo htmlspecialchars(number_format($row['open_amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars(number_format($row['weighted_amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars(number_format($row['won_amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endforeach; ?>
            <?php if (($engine['by_owner'] ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_win_probability'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>%</th><th>#</th><th>Amount</th><th>Weighted</th></tr></thead><tbody>
            <?php foreach (($win_probability ?? []) as $row): ?>
                <tr><td><?php echo htmlspecialchars($row['bucket'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo (int) $row['count']; ?></td><td><?php echo htmlspecialchars(number_format($row['amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars(number_format($row['weighted'], 2), ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endforeach; ?>
            <?php if (($win_probability ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_forecast_accuracy'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Period</th><th>Forecast</th><th>Actual Won</th><th>%</th></tr></thead><tbody>
            <?php foreach (($accuracy ?? []) as $row): ?>
                <tr><td><?php echo htmlspecialchars($row['period_key'], ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars(number_format($row['forecast_weighted'], 2), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars(number_format($row['actual_won'], 2), ENT_QUOTES, 'UTF-8'); ?></td><td><?php echo htmlspecialchars((string) $row['accuracy_pct'], ENT_QUOTES, 'UTF-8'); ?>%</td></tr>
            <?php endforeach; ?>
            <?php if (($accuracy ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </div>
</div>
