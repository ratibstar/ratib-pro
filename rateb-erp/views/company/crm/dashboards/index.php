<?php
declare(strict_types=1);
$dash = $dash ?? ['kpis' => [], 'extra' => [], 'role' => 'rep'];
$kpis = $dash['kpis'] ?? [];
$role = (string) ($role ?? $dash['role'] ?? 'rep');
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_advanced_dashboards')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <form method="get" class="d-flex flex-wrap gap-2">
            <select class="form-select" name="role" onchange="this.form.submit()">
                <option value="executive" <?php echo $role === 'executive' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('crm_dash_executive'), ENT_QUOTES, 'UTF-8'); ?></option>
                <option value="manager" <?php echo $role === 'manager' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('crm_dash_manager'), ENT_QUOTES, 'UTF-8'); ?></option>
                <option value="rep" <?php echo $role === 'rep' ? 'selected' : ''; ?>><?php echo htmlspecialchars(__('crm_dash_rep'), ENT_QUOTES, 'UTF-8'); ?></option>
            </select>
            <input class="form-control" style="width:8rem" type="number" name="user_id" placeholder="user" value="<?php echo (int) ($user_id ?? 0) ?: ''; ?>">
            <input class="form-control" style="width:8rem" type="number" name="team_id" placeholder="team" value="<?php echo (int) ($team_id ?? 0) ?: ''; ?>">
            <select class="form-select" name="pipeline_id">
                <option value="0"><?php echo htmlspecialchars(__('crm_pipeline'), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php foreach (($pipelines ?? []) as $p): ?>
                <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($pipeline_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_kpi_pipeline_value'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars(number_format((float) ($kpis['pipeline_value'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_win_rate'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars((string) ($kpis['win_rate'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>%</div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_pipeline_velocity'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars(number_format((float) ($kpis['sales_velocity'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_forecast_confidence'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars((string) ($kpis['forecast_confidence'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>%</div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_weighted_pipeline'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo htmlspecialchars(number_format((float) ($kpis['weighted_pipeline'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
    </div>

    <?php if ($role === 'executive'): ?>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_rep_performance'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>Rep</th><th>Won</th><th>Win%</th><th>Amt</th></tr></thead><tbody>
            <?php foreach (($dash['extra']['team_performance'] ?? []) as $row): ?>
                <tr><td>#<?php echo (int) $row['owner_user_id']; ?></td><td><?php echo (int) $row['won_count']; ?></td><td><?php echo htmlspecialchars((string) round($row['win_rate'] * 100, 1), ENT_QUOTES, 'UTF-8'); ?>%</td><td><?php echo htmlspecialchars(number_format($row['won_amount'], 2), ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_pipeline_health'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <pre class="border rounded p-3 small"><?php echo htmlspecialchars(json_encode($dash['extra']['pipeline_health'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
    </div>
    <?php elseif ($role === 'manager'): ?>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_bottlenecks'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($dash['extra']['bottlenecks'] ?? []) as $b): ?>
                <li class="list-group-item"><?php echo htmlspecialchars((string) ($b['stage'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> — <?php echo htmlspecialchars((string) ($b['avg_duration_days'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>d</li>
                <?php endforeach; ?>
                <?php if (($dash['extra']['bottlenecks'] ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_stale_opportunities'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($dash['extra']['stale'] ?? []) as $s): ?>
                <li class="list-group-item"><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/opportunities') . '/' . (int) $s['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($s['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></li>
                <?php endforeach; ?>
                <?php if (($dash['extra']['stale'] ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_daily_actions'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($dash['extra']['workspace']['daily_sales_actions'] ?? []) as $a): ?>
                <li class="list-group-item"><?php echo htmlspecialchars((string) (($a['type'] ?? '') . ': ' . ($a['label'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
                <?php if (($dash['extra']['workspace']['daily_sales_actions'] ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_activity_intelligence'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <pre class="border rounded p-3 small"><?php echo htmlspecialchars(json_encode($dash['extra']['activity'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?></pre>
        </div>
    </div>
    <?php endif; ?>
</div>
