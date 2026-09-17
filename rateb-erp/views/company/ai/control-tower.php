<?php
declare(strict_types=1);
/**
 * Control Tower panel partial — rendered with live snapshot only.
 * @var array $controlTower
 * @var string $towerEndpoint
 * @var string $locale
 */
$ct = is_array($controlTower ?? null) ? $controlTower : [];
$towerEndpoint = (string) ($towerEndpoint ?? '');
$ov = is_array($ct['overview'] ?? null) ? $ct['overview'] : (is_array($ct['current_state'] ?? null) ? $ct['current_state'] : []);
$eff = is_array($ct['agent_effectiveness'] ?? null) ? $ct['agent_effectiveness'] : [];
$esc = static function ($v): string {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
};
$badgeClass = static function (string $p): string {
    $p = strtoupper($p);
    if (in_array($p, ['CRITICAL', 'HIGH', 'FAILED', 'NEGATIVE_OUTCOME'], true)) {
        return 'is-high';
    }
    if (in_array($p, ['MEDIUM', 'PARTIAL_SUCCESS', 'UNKNOWN'], true)) {
        return 'is-medium';
    }
    if (in_array($p, ['LOW', 'SUCCESS', 'VERIFIED', 'RESOLVED'], true)) {
        return 'is-ok';
    }
    return '';
};
$renderEvidence = static function (array $ev) use ($esc): string {
    $parts = [];
    if (!empty($ev['domain'])) {
        $parts[] = __('ai_ct_domain') . ': ' . $esc($ev['domain']);
    }
    if (!empty($ev['source']) || !empty($ev['tool'])) {
        $parts[] = __('ai_ct_source') . ': ' . $esc((string) ($ev['source'] ?? $ev['tool'] ?? ''));
    }
    if (!empty($ev['timestamp'])) {
        $parts[] = __('ai_ct_timestamp') . ': ' . $esc($ev['timestamp']);
    }
    if (!empty($ev['data_sufficiency']) || !empty($ev['confidence'])) {
        $parts[] = __('ai_ct_confidence') . ': ' . $esc((string) ($ev['data_sufficiency'] ?? $ev['confidence']));
    }
    return $parts !== [] ? '<div class="rateb-ct-evidence">' . implode(' · ', $parts) . '</div>' : '';
};
?>
<section
    class="rateb-ct"
    id="ratebControlTower"
    data-tower-endpoint="<?php echo $esc($towerEndpoint); ?>"
    data-company-id="<?php echo (int) ($ct['company_id'] ?? 0); ?>"
    aria-label="<?php echo $esc(__('ai_ct_title')); ?>"
>
    <div class="rateb-ct-head">
        <div>
            <h2 class="rateb-ct-title"><i class="fa-solid fa-tower-observation" aria-hidden="true"></i> <?php echo $esc(__('ai_ct_title')); ?></h2>
            <div class="rateb-ct-meta">
                <?php echo $esc(__('ai_ct_as_of')); ?>: <?php echo $esc((string) ($ct['as_of'] ?? '')); ?>
                · <?php echo $esc(__('ai_ct_live_only')); ?>
            </div>
        </div>
        <div class="rateb-ct-actions">
            <button type="button" class="rateb-ct-refresh" data-ct-refresh="1"><?php echo $esc(__('ai_ct_refresh')); ?></button>
        </div>
    </div>

    <div class="rateb-ct-tabs" role="tablist">
        <?php
        $tabs = [
            'overview' => __('ai_ct_tab_overview'),
            'kpis' => __('ai_ct_tab_kpis'),
            'warnings' => __('ai_ct_tab_warnings'),
            'forecasts' => __('ai_ct_tab_forecasts'),
            'recommendations' => __('ai_ct_tab_recommendations'),
            'actions' => __('ai_ct_tab_actions'),
            'outcomes' => __('ai_ct_tab_outcomes'),
            'learning' => __('ai_ct_tab_learning'),
            'memory' => __('ai_ct_tab_memory'),
            'activity' => __('ai_ct_tab_activity'),
        ];
        foreach ($tabs as $id => $label):
        ?>
            <button type="button" class="rateb-ct-tab<?php echo $id === 'overview' ? ' is-active' : ''; ?>" role="tab" data-ct-tab="<?php echo $esc($id); ?>" aria-selected="<?php echo $id === 'overview' ? 'true' : 'false'; ?>"><?php echo $esc($label); ?></button>
        <?php endforeach; ?>
    </div>

    <div class="rateb-ct-body">
        <div class="rateb-ct-panel is-active" data-ct-panel="overview" role="tabpanel">
            <div class="rateb-ct-grid">
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_kpis')); ?></div><div class="rateb-ct-stat-value"><?php echo (int) ($ov['kpi_count'] ?? 0); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_critical_risks')); ?></div><div class="rateb-ct-stat-value"><?php echo (int) ($ov['critical_risks'] ?? 0); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_open_warnings')); ?></div><div class="rateb-ct-stat-value"><?php echo (int) ($ov['open_warnings'] ?? 0); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_forecasts')); ?></div><div class="rateb-ct-stat-value"><?php echo (int) ($ov['forecasts_available'] ?? 0); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_recommendations')); ?></div><div class="rateb-ct-stat-value"><?php echo (int) ($ov['recommendations'] ?? 0); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_outcomes')); ?></div><div class="rateb-ct-stat-value"><?php echo (int) ($ov['outcomes'] ?? 0); ?></div></div>
            </div>
            <p class="rateb-ct-note"><?php echo $esc(__('ai_ct_no_direct_writes')); ?></p>
        </div>

        <div class="rateb-ct-panel" data-ct-panel="kpis" role="tabpanel" hidden>
            <ul class="rateb-ct-list">
                <?php if (empty($ct['kpis'])): ?>
                    <li class="rateb-ct-empty"><?php echo $esc(__('ai_ct_empty')); ?></li>
                <?php else: foreach ($ct['kpis'] as $k): if (!is_array($k)) continue; ?>
                    <li class="rateb-ct-item">
                        <div class="rateb-ct-item-head">
                            <span class="rateb-ct-item-title"><?php echo $esc((string) ($k['label'] ?? $k['code'] ?? '')); ?></span>
                            <span class="rateb-ct-badge"><?php echo $esc((string) ($k['domain'] ?? '')); ?></span>
                        </div>
                        <div><?php echo $esc((string) ($k['available'] ? ($k['value'] ?? '—') : __('ai_ct_insufficient'))); ?> <?php echo $esc((string) ($k['unit'] ?? '')); ?></div>
                        <?php echo $renderEvidence(is_array($k['evidence'] ?? null) ? $k['evidence'] : []); ?>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>

        <div class="rateb-ct-panel" data-ct-panel="warnings" role="tabpanel" hidden>
            <ul class="rateb-ct-list">
                <?php
                $warnRows = array_merge(
                    is_array($ct['risks'] ?? null) ? $ct['risks'] : [],
                    is_array($ct['warnings'] ?? null) ? $ct['warnings'] : []
                );
                if ($warnRows === []): ?>
                    <li class="rateb-ct-empty"><?php echo $esc(__('ai_ct_empty')); ?></li>
                <?php else: foreach ($warnRows as $w): if (!is_array($w)) continue;
                    $sev = (string) ($w['severity'] ?? $w['priority'] ?? '');
                    $title = (string) ($w['signal'] ?? $w['code'] ?? $w['decision_summary'] ?? '');
                ?>
                    <li class="rateb-ct-item">
                        <div class="rateb-ct-item-head">
                            <span class="rateb-ct-item-title"><?php echo $esc($title); ?></span>
                            <span class="rateb-ct-badge <?php echo $esc($badgeClass($sev)); ?>"><?php echo $esc($sev !== '' ? $sev : (string) ($w['status'] ?? '')); ?></span>
                        </div>
                        <div><?php echo $esc(__('ai_ct_impact')); ?>: <?php echo $esc((string) ($w['impact'] ?? '—')); ?>
                            · <?php echo $esc(__('ai_ct_status')); ?>: <?php echo $esc((string) ($w['status'] ?? '—')); ?>
                            <?php if (isset($w['age_hours'])): ?> · <?php echo $esc(__('ai_ct_age')); ?>: <?php echo (int) $w['age_hours']; ?>h<?php endif; ?>
                        </div>
                        <div><?php echo $esc(__('ai_ct_recommended')); ?>: <?php echo $esc((string) ($w['recommended_action'] ?? '—')); ?></div>
                        <?php echo $renderEvidence(is_array($w['evidence'] ?? null) ? $w['evidence'] : []); ?>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>

        <div class="rateb-ct-panel" data-ct-panel="forecasts" role="tabpanel" hidden>
            <ul class="rateb-ct-list">
                <?php if (empty($ct['forecasts'])): ?>
                    <li class="rateb-ct-empty"><?php echo $esc(__('ai_ct_empty')); ?></li>
                <?php else: foreach ($ct['forecasts'] as $f): if (!is_array($f)) continue; ?>
                    <li class="rateb-ct-item">
                        <div class="rateb-ct-item-head">
                            <span class="rateb-ct-item-title"><?php echo $esc((string) ($f['code'] ?? '')); ?></span>
                            <span class="rateb-ct-badge"><?php echo !empty($f['available']) ? $esc(__('ai_ct_available')) : $esc(__('ai_ct_unavailable')); ?></span>
                        </div>
                        <div><?php echo $esc((string) ($f['decision_summary'] ?? '')); ?></div>
                        <?php if (!empty($f['available'])): ?>
                            <div><?php echo $esc(__('ai_ct_prediction')); ?>: <?php echo $esc((string) ($f['prediction'] ?? '')); ?> (<?php echo $esc(__('ai_ct_not_fact')); ?>)</div>
                        <?php endif; ?>
                        <?php echo $renderEvidence(is_array($f['evidence'] ?? null) ? $f['evidence'] : []); ?>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>

        <div class="rateb-ct-panel" data-ct-panel="recommendations" role="tabpanel" hidden>
            <ul class="rateb-ct-list">
                <?php if (empty($ct['recommendations'])): ?>
                    <li class="rateb-ct-empty"><?php echo $esc(__('ai_ct_empty')); ?></li>
                <?php else: foreach ($ct['recommendations'] as $r): if (!is_array($r)) continue; ?>
                    <li class="rateb-ct-item">
                        <div class="rateb-ct-item-head">
                            <span class="rateb-ct-item-title"><?php echo $esc((string) ($r['recommended_action'] ?? $r['insight'] ?? '')); ?></span>
                            <span class="rateb-ct-badge <?php echo $esc($badgeClass((string) ($r['priority'] ?? ''))); ?>"><?php echo $esc((string) ($r['priority'] ?? 'PROPOSED')); ?></span>
                        </div>
                        <div><?php echo $esc(__('ai_ct_lifecycle')); ?>: <?php echo $esc((string) ($r['lifecycle'] ?? 'PROPOSED')); ?> · <?php echo $esc(__('ai_ct_requires_confirm')); ?></div>
                        <?php echo $renderEvidence(is_array($r['evidence'] ?? null) ? $r['evidence'] : []); ?>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>

        <div class="rateb-ct-panel" data-ct-panel="actions" role="tabpanel" hidden>
            <?php $act = is_array($ct['actions'] ?? null) ? $ct['actions'] : []; ?>
            <div class="rateb-ct-grid">
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_proposed')); ?></div><div class="rateb-ct-stat-value"><?php echo count($act['proposed'] ?? []); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_executed')); ?></div><div class="rateb-ct-stat-value"><?php echo count($act['executed'] ?? []); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_verified')); ?></div><div class="rateb-ct-stat-value"><?php echo count($act['verified'] ?? []); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_failed')); ?></div><div class="rateb-ct-stat-value"><?php echo count($act['failed'] ?? []); ?></div></div>
            </div>
            <p class="rateb-ct-note"><?php echo $esc(__('ai_ct_action_path')); ?></p>
        </div>

        <div class="rateb-ct-panel" data-ct-panel="outcomes" role="tabpanel" hidden>
            <ul class="rateb-ct-list">
                <?php if (empty($ct['outcomes'])): ?>
                    <li class="rateb-ct-empty"><?php echo $esc(__('ai_ct_empty')); ?></li>
                <?php else: foreach ($ct['outcomes'] as $o): if (!is_array($o)) continue; ?>
                    <li class="rateb-ct-item">
                        <div class="rateb-ct-item-head">
                            <span class="rateb-ct-item-title"><?php echo $esc((string) ($o['tool'] ?? $o['outcome_id'] ?? '')); ?></span>
                            <span class="rateb-ct-badge <?php echo $esc($badgeClass((string) ($o['business_outcome'] ?? $o['outcome_status'] ?? ''))); ?>"><?php echo $esc((string) ($o['business_outcome'] ?? $o['outcome_status'] ?? '')); ?></span>
                        </div>
                        <div><?php echo $esc(__('ai_ct_lifecycle')); ?>: <?php echo $esc((string) ($o['lifecycle'] ?? '')); ?>
                            · <?php echo $esc(__('ai_ct_domain')); ?>: <?php echo $esc((string) ($o['domain'] ?? '')); ?>
                        </div>
                        <?php echo $renderEvidence(is_array($o['evidence'] ?? null) ? $o['evidence'] : []); ?>
                    </li>
                <?php endforeach; endif; ?>
            </ul>
        </div>

        <div class="rateb-ct-panel" data-ct-panel="learning" role="tabpanel" hidden>
            <?php $learn = is_array($ct['learning'] ?? null) ? $ct['learning'] : []; ?>
            <div class="rateb-ct-grid">
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_signals')); ?></div><div class="rateb-ct-stat-value"><?php echo count($learn['signals'] ?? []); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_patterns')); ?></div><div class="rateb-ct-stat-value"><?php echo count($learn['patterns'] ?? []); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_sufficiency')); ?></div><div class="rateb-ct-stat-value"><?php echo $esc((string) ($learn['data_sufficiency'] ?? '—')); ?></div></div>
            </div>
            <p class="rateb-ct-note"><?php echo $esc(__('ai_ct_learning_immutable')); ?></p>
        </div>

        <div class="rateb-ct-panel" data-ct-panel="memory" role="tabpanel" hidden>
            <?php
            $mem = is_array($ct['operational_memory'] ?? null) ? $ct['operational_memory'] : [];
            $groups = is_array($mem['groups'] ?? null) ? $mem['groups'] : [];
            $memSections = [
                'decisions' => __('ai_ct_mem_decisions'),
                'actions' => __('ai_ct_mem_actions'),
                'outcomes' => __('ai_ct_mem_outcomes'),
                'patterns' => __('ai_ct_mem_patterns'),
                'unresolved' => __('ai_ct_mem_unresolved'),
                'warnings' => __('ai_ct_mem_warnings'),
                'learning_signals' => __('ai_ct_mem_learning'),
            ];
            $anyMem = false;
            foreach ($memSections as $gk => $_label) {
                if (!empty($groups[$gk])) {
                    $anyMem = true;
                    break;
                }
            }
            ?>
            <p class="rateb-ct-note"><?php echo $esc(__('ai_ct_mem_note')); ?></p>
            <?php if (!$anyMem): ?>
                <ul class="rateb-ct-list"><li class="rateb-ct-empty"><?php echo $esc(__('ai_ct_empty')); ?></li></ul>
            <?php else: foreach ($memSections as $gk => $glabel):
                $rows = is_array($groups[$gk] ?? null) ? $groups[$gk] : [];
                if ($rows === []) {
                    continue;
                }
            ?>
                <h3 class="rateb-ct-subtitle"><?php echo $esc($glabel); ?></h3>
                <ul class="rateb-ct-list">
                    <?php foreach ($rows as $m): if (!is_array($m)) continue; ?>
                        <li class="rateb-ct-item">
                            <div class="rateb-ct-item-head">
                                <span class="rateb-ct-item-title"><?php echo $esc((string) ($m['title'] ?? '')); ?></span>
                                <span class="rateb-ct-badge"><?php echo $esc((string) ($m['freshness_band'] ?? '')); ?></span>
                            </div>
                            <div><?php echo $esc(__('ai_ct_domain')); ?>: <?php echo $esc((string) ($m['domain'] ?? '—')); ?>
                                <?php if (!empty($m['is_historical'])): ?> · <?php echo $esc(__('ai_ct_mem_historical')); ?><?php endif; ?>
                                <?php if ($m['outcome'] !== null && $m['outcome'] !== ''): ?> · <?php echo $esc(__('ai_ct_outcomes')); ?>: <?php echo $esc((string) $m['outcome']); ?><?php endif; ?>
                            </div>
                            <?php echo $renderEvidence(is_array($m['evidence'] ?? null) ? $m['evidence'] : []); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; endif; ?>
        </div>

        <div class="rateb-ct-panel" data-ct-panel="activity" role="tabpanel" hidden>
            <div class="rateb-ct-grid">
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_requests')); ?></div><div class="rateb-ct-stat-value"><?php echo (int) ($eff['requests'] ?? 0); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_reads')); ?></div><div class="rateb-ct-stat-value"><?php echo (int) ($eff['reads_analyses'] ?? 0); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_writes')); ?></div><div class="rateb-ct-stat-value"><?php echo (int) ($eff['writes'] ?? 0); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_verified')); ?></div><div class="rateb-ct-stat-value"><?php echo (int) ($eff['verified_actions'] ?? 0); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_blocked')); ?></div><div class="rateb-ct-stat-value"><?php echo (int) ($eff['blocked_actions'] ?? 0); ?></div></div>
                <div class="rateb-ct-stat"><div class="rateb-ct-stat-label"><?php echo $esc(__('ai_ct_failures')); ?></div><div class="rateb-ct-stat-value"><?php echo (int) ($eff['failures'] ?? 0); ?></div></div>
            </div>
            <?php if (!empty($eff['avg_duration_ms'])): ?>
                <p class="rateb-ct-note"><?php echo $esc(__('ai_ct_avg_duration')); ?>: <?php echo $esc((string) round((float) $eff['avg_duration_ms'])); ?> ms (7d)</p>
            <?php endif; ?>
            <?php echo $renderEvidence(is_array($eff['evidence'] ?? null) ? $eff['evidence'] : []); ?>
        </div>
    </div>
</section>
