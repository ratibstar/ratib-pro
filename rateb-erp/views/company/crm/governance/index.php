<?php
declare(strict_types=1);
$health = $health ?? [];
$canManage = !empty($canManage);
$opsCompanyId = (int) ($ops_company_id ?? 0);
if ($opsCompanyId < 1 && function_exists('rateb_resolve_ops_company_id')) {
    $opsCompanyId = (int) rateb_resolve_ops_company_id();
}
$govPostUrl = static function (string $route) use ($opsCompanyId): string {
    $url = rateb_url(rateb_app_route($route));
    if ($opsCompanyId > 0 && function_exists('rateb_url_set_query_param')) {
        return rateb_url_set_query_param($url, 'company_id', (string) $opsCompanyId);
    }
    if ($opsCompanyId > 0) {
        return $url . (str_contains($url, '?') ? '&' : '?') . 'company_id=' . $opsCompanyId;
    }

    return $url;
};

/**
 * @return array<string, mixed>
 */
$decodeSetting = static function (array $s): array {
    $raw = $s['setting_json'] ?? '{}';
    if (is_array($raw)) {
        return $raw;
    }
    $decoded = json_decode((string) $raw, true);

    return is_array($decoded) ? $decoded : [];
};
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_governance')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($canManage): ?>
        <form method="post" action="<?php echo htmlspecialchars($govPostUrl('crm/governance/scan'), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($opsCompanyId > 0): ?><input type="hidden" name="company_id" value="<?php echo $opsCompanyId; ?>"><?php endif; ?>
            <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('crm_run_quality_scan'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
        <?php endif; ?>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_governance_score'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo (int) ($health['score'] ?? 0); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_open_issues'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo (int) ($health['open_issues'] ?? 0); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_missing_own_dupes'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-6"><?php echo (int) ($health['missing_fields'] ?? 0); ?> / <?php echo (int) ($health['ownership_gaps'] ?? 0); ?> / <?php echo (int) ($health['duplicate_candidates'] ?? 0); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_automation_governance'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-6"><?php echo htmlspecialchars(!empty($automation_gov['ok']) ? __('crm_status_ok') : __('crm_review'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars(__('crm_always_rules'), ENT_QUOTES, 'UTF-8'); ?> <?php echo (int) ($automation_gov['always_rules'] ?? 0); ?>/<?php echo (int) ($automation_gov['max_always_rules'] ?? 0); ?>)</div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-7">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_data_quality_issues'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php foreach (($issues ?? []) as $issue): ?>
            <div class="border rounded p-3 mb-2">
                <div class="fw-semibold"><?php
                    $issueMsg = (string) ($issue['message'] ?? '');
                    $issueMeta = [];
                    if (!empty($issue['meta_json'])) {
                        $decodedMeta = json_decode((string) $issue['meta_json'], true);
                        if (is_array($decodedMeta)) {
                            $issueMeta = $decodedMeta;
                        }
                    } elseif (is_array($issue['meta'] ?? null)) {
                        $issueMeta = $issue['meta'];
                    }
                    if (($issue['issue_code'] ?? '') === 'missing_field' && !empty($issueMeta['field'])) {
                        echo htmlspecialchars(__('crm_missing_required_field', ['field' => rateb_ui((string) $issueMeta['field'])]), ENT_QUOTES, 'UTF-8');
                    } else {
                        echo htmlspecialchars(rateb_ui($issueMsg), ENT_QUOTES, 'UTF-8');
                    }
                ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars(rateb_ui((string) ($issue['entity_type'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                    #<?php echo (int) ($issue['entity_id'] ?? 0); ?>
                    · <?php echo htmlspecialchars(rateb_ui((string) ($issue['severity'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                    · <?php echo htmlspecialchars(rateb_ui((string) ($issue['issue_code'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if ($canManage): ?>
                <form method="post" action="<?php
                    $resolveUrl = rateb_url(rateb_app_route('crm/governance/issues') . '/' . (int) $issue['id'] . '/resolve');
                    if ($opsCompanyId > 0 && function_exists('rateb_url_set_query_param')) {
                        $resolveUrl = rateb_url_set_query_param($resolveUrl, 'company_id', (string) $opsCompanyId);
                    } elseif ($opsCompanyId > 0) {
                        $resolveUrl .= (str_contains($resolveUrl, '?') ? '&' : '?') . 'company_id=' . $opsCompanyId;
                    }
                    echo htmlspecialchars($resolveUrl, ENT_QUOTES, 'UTF-8');
                ?>" class="mt-2 d-flex gap-2">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <?php if ($opsCompanyId > 0): ?><input type="hidden" name="company_id" value="<?php echo $opsCompanyId; ?>"><?php endif; ?>
                    <input class="form-control form-control-sm" name="note" placeholder="<?php echo htmlspecialchars(__('note'), ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="btn btn-sm btn-outline-success" type="submit"><?php echo htmlspecialchars(__('resolve'), ENT_QUOTES, 'UTF-8'); ?></button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (($issues ?? []) === []): ?><p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        </div>
        <div class="col-lg-5">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_enterprise_admin'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <p class="small text-muted mb-3"><?php echo htmlspecialchars(__('crm_governance_settings_help'), ENT_QUOTES, 'UTF-8'); ?></p>
            <?php
            $settingsByKey = [];
            foreach (($health['settings'] ?? []) as $row) {
                $k = (string) ($row['setting_key'] ?? '');
                if ($k !== '') {
                    $settingsByKey[$k] = $decodeSetting($row);
                }
            }
            $knownKeys = ['automation_governance', 'automation_safety', 'duplicate_rules', 'export_policy'];
            foreach ($knownKeys as $key):
                $cfg = $settingsByKey[$key] ?? [];
            ?>
            <form method="post" action="<?php echo htmlspecialchars($govPostUrl('crm/governance/settings'), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-2">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <?php if ($opsCompanyId > 0): ?><input type="hidden" name="company_id" value="<?php echo $opsCompanyId; ?>"><?php endif; ?>
                <input type="hidden" name="setting_key" value="<?php echo htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="fw-semibold mb-2"><?php echo htmlspecialchars(rateb_ui($key), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if ($key === 'automation_governance'): ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="require_condition_json" value="1" id="gov-req-cond" <?php echo !empty($cfg['require_condition_json']) || $cfg === [] ? 'checked' : ''; ?> <?php echo $canManage ? '' : 'disabled'; ?>>
                        <label class="form-check-label" for="gov-req-cond"><?php echo htmlspecialchars(__('crm_gov_require_condition'), ENT_QUOTES, 'UTF-8'); ?></label>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="gov-max-always"><?php echo htmlspecialchars(__('crm_gov_max_always_rules'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <input class="form-control form-control-sm" type="number" min="0" max="99" name="max_always_rules" id="gov-max-always" value="<?php echo (int) ($cfg['max_always_rules'] ?? 3); ?>" <?php echo $canManage ? '' : 'readonly'; ?>>
                    </div>
                <?php elseif ($key === 'automation_safety'): ?>
                    <div class="mb-2">
                        <label class="form-label small" for="gov-cooldown"><?php echo htmlspecialchars(__('crm_gov_notify_cooldown_hours'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <input class="form-control form-control-sm" type="number" min="1" max="720" name="notification_cooldown_hours" id="gov-cooldown" value="<?php echo (int) ($cfg['notification_cooldown_hours'] ?? 24); ?>" <?php echo $canManage ? '' : 'readonly'; ?>>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="gov-lock"><?php echo htmlspecialchars(__('crm_gov_run_lock_minutes'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <input class="form-control form-control-sm" type="number" min="1" max="1440" name="run_lock_minutes" id="gov-lock" value="<?php echo (int) ($cfg['run_lock_minutes'] ?? 10); ?>" <?php echo $canManage ? '' : 'readonly'; ?>>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="gov-max-notify"><?php echo htmlspecialchars(__('crm_gov_max_notifies'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <input class="form-control form-control-sm" type="number" min="1" max="10000" name="max_notifies_per_run" id="gov-max-notify" value="<?php echo (int) ($cfg['max_notifies_per_run'] ?? 100); ?>" <?php echo $canManage ? '' : 'readonly'; ?>>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="include_legacy_in_revops" value="1" id="gov-legacy" <?php echo !empty($cfg['include_legacy_in_revops']) ? 'checked' : ''; ?> <?php echo $canManage ? '' : 'disabled'; ?>>
                        <label class="form-check-label" for="gov-legacy"><?php echo htmlspecialchars(__('crm_gov_include_legacy_revops'), ENT_QUOTES, 'UTF-8'); ?></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="block_always_rules_over_max" value="1" id="gov-block-always" <?php echo array_key_exists('block_always_rules_over_max', $cfg) ? (!empty($cfg['block_always_rules_over_max']) ? 'checked' : '') : 'checked'; ?> <?php echo $canManage ? '' : 'disabled'; ?>>
                        <label class="form-check-label" for="gov-block-always"><?php echo htmlspecialchars(__('crm_gov_block_always_over_max'), ENT_QUOTES, 'UTF-8'); ?></label>
                    </div>
                <?php elseif ($key === 'duplicate_rules'): ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="match_email" value="1" id="gov-match-email" <?php echo array_key_exists('match_email', $cfg) ? (!empty($cfg['match_email']) ? 'checked' : '') : 'checked'; ?> <?php echo $canManage ? '' : 'disabled'; ?>>
                        <label class="form-check-label" for="gov-match-email"><?php echo htmlspecialchars(__('crm_gov_match_email'), ENT_QUOTES, 'UTF-8'); ?></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="match_phone" value="1" id="gov-match-phone" <?php echo array_key_exists('match_phone', $cfg) ? (!empty($cfg['match_phone']) ? 'checked' : '') : 'checked'; ?> <?php echo $canManage ? '' : 'disabled'; ?>>
                        <label class="form-check-label" for="gov-match-phone"><?php echo htmlspecialchars(__('crm_gov_match_phone'), ENT_QUOTES, 'UTF-8'); ?></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="match_company_name" value="1" id="gov-match-co" <?php echo array_key_exists('match_company_name', $cfg) ? (!empty($cfg['match_company_name']) ? 'checked' : '') : 'checked'; ?> <?php echo $canManage ? '' : 'disabled'; ?>>
                        <label class="form-check-label" for="gov-match-co"><?php echo htmlspecialchars(__('crm_gov_match_company'), ENT_QUOTES, 'UTF-8'); ?></label>
                    </div>
                <?php else: /* export_policy */ ?>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="allow_csv" value="1" id="gov-allow-csv" <?php echo array_key_exists('allow_csv', $cfg) ? (!empty($cfg['allow_csv']) ? 'checked' : '') : 'checked'; ?> <?php echo $canManage ? '' : 'disabled'; ?>>
                        <label class="form-check-label" for="gov-allow-csv"><?php echo htmlspecialchars(__('crm_gov_allow_csv'), ENT_QUOTES, 'UTF-8'); ?></label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="audit_required" value="1" id="gov-audit" <?php echo array_key_exists('audit_required', $cfg) ? (!empty($cfg['audit_required']) ? 'checked' : '') : 'checked'; ?> <?php echo $canManage ? '' : 'disabled'; ?>>
                        <label class="form-check-label" for="gov-audit"><?php echo htmlspecialchars(__('crm_gov_audit_required'), ENT_QUOTES, 'UTF-8'); ?></label>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small" for="gov-export-perm"><?php echo htmlspecialchars(__('crm_gov_export_permission'), ENT_QUOTES, 'UTF-8'); ?></label>
                        <select class="form-select form-select-sm" name="require_permission" id="gov-export-perm" <?php echo $canManage ? '' : 'disabled'; ?>>
                            <?php
                            $perm = (string) ($cfg['require_permission'] ?? 'crm.export.manage');
                            $perms = [
                                'crm.export.manage' => __('crm_gov_perm_export_manage'),
                                'crm.reports.export' => __('crm_gov_perm_reports_export'),
                                'crm.manage' => __('crm_gov_perm_crm_manage'),
                            ];
                            if ($perm !== '' && !isset($perms[$perm])) {
                                $perms[$perm] = $perm;
                            }
                            foreach ($perms as $pval => $plabel):
                            ?>
                            <option value="<?php echo htmlspecialchars($pval, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $perm === $pval ? 'selected' : ''; ?>><?php echo htmlspecialchars($plabel, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <?php if ($canManage): ?>
                <button class="btn btn-sm btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
                <?php endif; ?>
            </form>
            <?php endforeach; ?>
        </div>
    </div>
</div>
