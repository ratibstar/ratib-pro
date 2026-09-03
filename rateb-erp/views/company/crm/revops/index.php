<?php
declare(strict_types=1);
$data = $data ?? [];
$fc = is_array($data['forecast'] ?? null) ? $data['forecast'] : [];
$dq = is_array($data['data_quality'] ?? null) ? $data['data_quality'] : [];
$ch = is_array($data['customer_health'] ?? null) ? $data['customer_health'] : [];
$perf = is_array($data['sales_performance'] ?? null) ? $data['sales_performance'] : [];
$auto = is_array($data['automation'] ?? null) ? $data['automation'] : [];
$rev = is_array($data['revenue_pipeline'] ?? null) ? $data['revenue_pipeline'] : [];
$fcEmpty = $fc === [] || isset($fc['error']);
$dqEmpty = $dq === [] || isset($dq['error']);
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_revops_command_center')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($canRunAutomation)): ?>
        <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/revops/automation')), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
            <button class="btn btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('crm_run_revops_automation'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
        <?php endif; ?>
    </div>
    <form method="get" class="row g-2 mb-4">
        <div class="col-auto">
            <select name="role" class="form-select form-select-sm">
                <?php foreach (['executive','manager','rep'] as $r): ?>
                <option value="<?php echo $r; ?>" <?php echo (($role ?? '') === $r) ? 'selected' : ''; ?>><?php echo $r; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string) ($date_from ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-auto"><input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars((string) ($date_to ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-auto">
            <select name="team_id" class="form-select form-select-sm">
                <option value="0"><?php echo htmlspecialchars(__('crm_team'), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php foreach (($teams ?? []) as $t): ?>
                <option value="<?php echo (int) $t['id']; ?>" <?php echo ((int) ($team_id ?? 0) === (int) $t['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($t['name'] ?? $t['id']), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <select name="pipeline_id" class="form-select form-select-sm">
                <option value="0"><?php echo htmlspecialchars(__('crm_pipeline_select'), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php foreach (($pipelines ?? []) as $p): ?>
                <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($pipeline_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? $p['id']), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-sm btn-primary" type="submit"><?php echo htmlspecialchars(__('filter'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-xl-2"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_revenue_intelligence'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5"><?php echo htmlspecialchars((string) ($rev['pipeline']['open_amount'] ?? $rev['open_amount'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-6 col-md-4 col-xl-2"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_forecast_confidence'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5"><?php echo htmlspecialchars((string) ($fc['confidence_score'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>%</div></div></div>
        <div class="col-6 col-md-4 col-xl-2"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_sales_performance_mgmt'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-6"><?php echo is_array($perf['reps'] ?? null) ? count($perf['reps']) : (is_array($perf) ? count($perf) : 0); ?> reps</div></div></div>
        <div class="col-6 col-md-4 col-xl-2"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_customer_risk'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5"><?php echo is_array($ch['at_risk'] ?? null) ? count($ch['at_risk']) : 0; ?></div></div></div>
        <div class="col-6 col-md-4 col-xl-2"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_quality_score'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5"><?php echo htmlspecialchars((string) ($dq['quality_score'] ?? $dq['score'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-6 col-md-4 col-xl-2"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_automation_governance'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-6"><?php echo !empty($auto['governance']['ok']) ? 'OK' : 'Review'; ?></div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_enterprise_forecast'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="border rounded p-3">
                <?php if ($fcEmpty): ?>
                    <?php require __DIR__ . '/../partials/empty.php'; ?>
                <?php else: ?>
                    <dl class="row mb-0 small">
                        <?php foreach (['period_key','confidence_score','weighted_amount','open_amount','won_amount','opportunity_count'] as $key): ?>
                            <?php if (!array_key_exists($key, $fc)) { continue; } ?>
                            <dt class="col-6 text-muted"><?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?></dt>
                            <dd class="col-6"><?php echo htmlspecialchars(is_scalar($fc[$key]) ? (string) $fc[$key] : '', ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_data_quality_engine'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="border rounded p-3">
                <?php if ($dqEmpty): ?>
                    <?php require __DIR__ . '/../partials/empty.php'; ?>
                <?php else: ?>
                    <dl class="row mb-0 small">
                        <?php foreach (['quality_score','completeness_score','open_issues','duplicates','missing','ownership'] as $key): ?>
                            <?php if (!array_key_exists($key, $dq)) { continue; } ?>
                            <dt class="col-6 text-muted"><?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?></dt>
                            <dd class="col-6"><?php echo htmlspecialchars(is_scalar($dq[$key]) ? (string) $dq[$key] : '', ENT_QUOTES, 'UTF-8'); ?></dd>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
