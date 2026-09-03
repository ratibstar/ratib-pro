<?php
declare(strict_types=1);
$health = $health ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_governance')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($canManage)): ?>
        <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/governance/scan')), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
            <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('crm_run_quality_scan'), ENT_QUOTES, 'UTF-8'); ?></button>
        </form>
        <?php endif; ?>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_governance_score'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo (int) ($health['score'] ?? 0); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_open_issues'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo (int) ($health['open_issues'] ?? 0); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_missing_own_dupes'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-6"><?php echo (int) ($health['missing_fields'] ?? 0); ?> / <?php echo (int) ($health['ownership_gaps'] ?? 0); ?> / <?php echo (int) ($health['duplicate_candidates'] ?? 0); ?></div></div></div>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars(__('crm_automation_governance'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-6"><?php echo !empty($automation_gov['ok']) ? 'OK' : 'Review'; ?> (always <?php echo (int) ($automation_gov['always_rules'] ?? 0); ?>/<?php echo (int) ($automation_gov['max_always_rules'] ?? 0); ?>)</div></div></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-7">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_data_quality_issues'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php foreach (($issues ?? []) as $issue): ?>
            <div class="border rounded p-3 mb-2">
                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($issue['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars(rateb_ui((string) ($issue['entity_type'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                    #<?php echo (int) ($issue['entity_id'] ?? 0); ?>
                    · <?php echo htmlspecialchars(rateb_ui((string) ($issue['severity'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                    · <?php echo htmlspecialchars(rateb_ui((string) ($issue['issue_code'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($canManage)): ?>
                <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/governance/issues') . '/' . (int) $issue['id'] . '/resolve'), ENT_QUOTES, 'UTF-8'); ?>" class="mt-2 d-flex gap-2">
                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
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
            <?php foreach (($health['settings'] ?? []) as $s): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/governance/settings')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-2">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="setting_key" value="<?php echo htmlspecialchars((string) ($s['setting_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="fw-semibold mb-1"><?php echo htmlspecialchars((string) ($s['setting_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <textarea class="form-control form-control-sm mb-2" name="setting_json" rows="3" <?php echo empty($canManage) ? 'readonly' : ''; ?>><?php echo htmlspecialchars((string) ($s['setting_json'] ?? '{}'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <?php if (!empty($canManage)): ?><button class="btn btn-sm btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button><?php endif; ?>
            </form>
            <?php endforeach; ?>
            <?php if (($health['settings'] ?? []) === []): ?><p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        </div>
    </div>
</div>
