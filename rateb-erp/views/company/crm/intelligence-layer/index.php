<?php
declare(strict_types=1);
$data = $data ?? [];
$activity = $activity ?? [];
$scoring = is_array($data['scoring_evolution'] ?? null) ? $data['scoring_evolution'] : [];
$anomalies = is_array($data['pipeline_anomalies'] ?? null) ? $data['pipeline_anomalies'] : [];
$risks = is_array($data['customer_risk_signals'] ?? null) ? $data['customer_risk_signals'] : [];
$patterns = is_array($activity['activity_patterns']['by_type'] ?? null) ? $activity['activity_patterns']['by_type'] : [];
$delays = is_array($activity['response_delays'] ?? null) ? $activity['response_delays'] : [];
$engagement = is_array($activity['sales_engagement'] ?? null) ? $activity['sales_engagement'] : [];
$reps = is_array($activity['rep_effectiveness'] ?? null) ? array_slice($activity['rep_effectiveness'], 0, 10) : [];
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('crm_intelligence_layer')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="get" class="row g-2 mb-4">
        <div class="col-auto">
            <select name="pipeline_id" class="form-select form-select-sm">
                <option value="0"><?php echo htmlspecialchars(__('crm_pipeline'), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php foreach (($pipelines ?? []) as $p): ?>
                <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($pipeline_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? $p['id']), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto"><input type="date" name="date_from" class="form-control form-control-sm rateb-ltr-date" dir="ltr" lang="en" value="<?php echo htmlspecialchars((string) ($date_from ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-auto"><input type="date" name="date_to" class="form-control form-control-sm rateb-ltr-date" dir="ltr" lang="en" value="<?php echo htmlspecialchars((string) ($date_to ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-auto"><button class="btn btn-sm btn-primary" type="submit"><?php echo htmlspecialchars(__('filter'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_growth_trends'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5"><?php
            $trend = strtolower((string) (($data['sales_trends']['direction'] ?? 'stable')));
            $trendKey = 'crm_trend_' . preg_replace('/[^a-z_]/', '', $trend);
            $trendLabel = __($trendKey);
            echo htmlspecialchars($trendLabel !== $trendKey ? $trendLabel : $trend, ENT_QUOTES, 'UTF-8');
        ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_customer_risk'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5"><?php echo count($risks); ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_pipeline_anomalies'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5"><?php echo count($anomalies); ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_activity_patterns'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-5"><?php echo (int) (($engagement['engagement_rate'] ?? 0)); ?>%</div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_scoring_evolution'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="border rounded p-3" style="max-height:280px;overflow:auto">
                <?php if ($scoring === []): ?>
                    <?php require __DIR__ . '/../partials/empty.php'; ?>
                <?php else: ?>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($scoring as $ev): ?>
                        <li class="mb-2 pb-2 border-bottom">
                            #<?php echo (int) ($ev['opportunity_id'] ?? 0); ?>
                            · <?php echo htmlspecialchars((string) ($ev['score_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            · <?php echo htmlspecialchars((string) (($ev['from'] ?? '') . ' → ' . ($ev['to'] ?? '') . ' (' . ($ev['trend'] ?? '') . ')'), ENT_QUOTES, 'UTF-8'); ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_pipeline_anomalies'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="border rounded p-3" style="max-height:280px;overflow:auto">
                <?php if ($anomalies === []): ?>
                    <?php require __DIR__ . '/../partials/empty.php'; ?>
                <?php else: ?>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($anomalies as $a): ?>
                        <li class="mb-2 pb-2 border-bottom">
                            #<?php echo (int) ($a['id'] ?? 0); ?>
                            · <?php echo htmlspecialchars((string) ($a['anomaly'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            · <?php echo htmlspecialchars((string) ($a['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_customer_risk'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="border rounded p-3" style="max-height:280px;overflow:auto">
                <?php if ($risks === []): ?>
                    <?php require __DIR__ . '/../partials/empty.php'; ?>
                <?php else: ?>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($risks as $r): ?>
                        <li class="mb-2 pb-2 border-bottom">
                            #<?php echo (int) ($r['id'] ?? 0); ?>
                            · <?php echo htmlspecialchars((string) ($r['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                            · <?php echo htmlspecialchars(implode(', ', array_map('strval', $r['signals'] ?? [])), ENT_QUOTES, 'UTF-8'); ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_activity_intelligence'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="border rounded p-3" style="max-height:280px;overflow:auto">
                <dl class="row small mb-3">
                    <dt class="col-7 text-muted"><?php echo htmlspecialchars(__('crm_engagement_rate'), ENT_QUOTES, 'UTF-8'); ?></dt>
                    <dd class="col-5"><?php echo htmlspecialchars((string) ($engagement['engagement_rate'] ?? 0), ENT_QUOTES, 'UTF-8'); ?>%</dd>
                    <dt class="col-7 text-muted"><?php echo htmlspecialchars(__('crm_avg_response_hours'), ENT_QUOTES, 'UTF-8'); ?></dt>
                    <dd class="col-5 rateb-ltr-num"><?php echo htmlspecialchars((string) ($delays['avg_hours'] ?? $activity['avg_response_hours'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></dd>
                    <dt class="col-7 text-muted"><?php echo htmlspecialchars(__('crm_active_touched'), ENT_QUOTES, 'UTF-8'); ?></dt>
                    <dd class="col-5 rateb-ltr-num"><?php echo (int) ($engagement['active_opps'] ?? 0); ?> / <?php echo (int) ($engagement['touched_opps'] ?? 0); ?></dd>
                </dl>
                <?php if ($patterns === [] && $reps === []): ?>
                    <?php require __DIR__ . '/../partials/empty.php'; ?>
                <?php else: ?>
                    <?php if ($patterns !== []): ?>
                    <h3 class="h6"><?php echo htmlspecialchars(__('crm_activity_patterns'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <ul class="list-unstyled small mb-3">
                        <?php foreach ($patterns as $type => $cnt): ?>
                        <li><?php echo htmlspecialchars(rateb_ui((string) $type), ENT_QUOTES, 'UTF-8'); ?>: <?php echo (int) $cnt; ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <?php if ($reps !== []): ?>
                    <h3 class="h6"><?php echo htmlspecialchars(__('crm_rep_performance'), ENT_QUOTES, 'UTF-8'); ?></h3>
                    <ul class="list-unstyled small mb-0">
                        <?php foreach ($reps as $rep): ?>
                        <li class="mb-1">#<?php echo (int) ($rep['owner_user_id'] ?? $rep['user_id'] ?? 0); ?>
                            · <?php echo htmlspecialchars((string) ($rep['won_count'] ?? $rep['activities'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
