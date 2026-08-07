<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('crm_admin_config')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/pipeline')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('crm_pipeline'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_pipeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($pipelines ?? []) as $p): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <span><?php echo htmlspecialchars((string) ($p['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                    <a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/pipeline') . '?pipeline_id=' . (int) $p['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('crm_pipeline_stage_manage'), ENT_QUOTES, 'UTF-8'); ?></a>
                </li>
                <?php endforeach; ?>
                <?php if (($pipelines ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>

            <h2 class="h5"><?php echo htmlspecialchars(__('crm_loss_reason'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group mb-3">
                <?php foreach (($loss_reasons ?? []) as $lr): ?>
                <li class="list-group-item"><?php echo htmlspecialchars((string) ($lr['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
                <?php if (($loss_reasons ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
            <p class="small text-muted"><?php echo htmlspecialchars(__('crm_admin_loss_hint'), ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_activity_types'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php if (!empty($canManage)): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/admin/activity-types')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="row g-2">
                    <div class="col-md-4"><input class="form-control" name="code" placeholder="code"></div>
                    <div class="col-md-5"><input class="form-control" name="name" placeholder="<?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?>" required></div>
                    <div class="col-md-3"><button class="btn btn-primary w-100" type="submit"><?php echo htmlspecialchars(__('create'), ENT_QUOTES, 'UTF-8'); ?></button></div>
                </div>
            </form>
            <?php endif; ?>
            <ul class="list-group mb-4">
                <?php foreach (($activity_types ?? []) as $t): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <span><?php echo htmlspecialchars((string) (($t['code'] ?? '') . ' — ' . ($t['name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="small text-muted"><?php echo !empty($t['is_active']) ? 'active' : 'off'; ?></span>
                </li>
                <?php endforeach; ?>
                <?php if (($activity_types ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>

            <h2 class="h5"><?php echo htmlspecialchars(__('crm_automation_rules'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php foreach (($automation_rules ?? []) as $rule): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/admin/automation-rules') . '/' . (int) $rule['id']), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-2">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($rule['name'] ?? $rule['rule_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="small text-muted mb-2"><?php echo htmlspecialchars((string) ($rule['rule_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($canManage)): ?>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="rule_<?php echo (int) $rule['id']; ?>" <?php echo !empty($rule['is_enabled']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="rule_<?php echo (int) $rule['id']; ?>">enabled</label>
                </div>
                <label class="form-label small"><?php echo htmlspecialchars(__('crm_rule_conditions'), ENT_QUOTES, 'UTF-8'); ?></label>
                <textarea class="form-control form-control-sm mb-2" name="condition_json" rows="2"><?php echo htmlspecialchars((string) ($rule['condition_json'] ?? '{"type":"always"}'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <label class="form-label small"><?php echo htmlspecialchars(__('crm_rule_actions'), ENT_QUOTES, 'UTF-8'); ?></label>
                <textarea class="form-control form-control-sm mb-2" name="action_json" rows="2"><?php echo htmlspecialchars((string) ($rule['action_json'] ?? '{"type":"notify"}'), ENT_QUOTES, 'UTF-8'); ?></textarea>
                <button class="btn btn-sm btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
                <?php endif; ?>
            </form>
            <?php endforeach; ?>
            <?php if (($automation_rules ?? []) === []): ?><p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>

            <h2 class="h5 mt-4"><?php echo htmlspecialchars(__('crm_automation_history'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach (($execution_history ?? []) as $h): ?>
                <li class="list-group-item small">
                    <?php echo htmlspecialchars((string) (($h['event_type'] ?? '') . ' · ' . ($h['entity_type'] ?? '') . ' #' . ($h['entity_id'] ?? '') . ' · ' . ($h['created_at'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
                </li>
                <?php endforeach; ?>
                <?php if (($execution_history ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
