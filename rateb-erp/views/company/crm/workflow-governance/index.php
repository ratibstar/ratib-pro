<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('crm_workflow_governance')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="get" class="row g-2 mb-3">
        <div class="col-auto">
            <select name="pipeline_id" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach (($pipelines ?? []) as $p): ?>
                <option value="<?php echo (int) $p['id']; ?>" <?php echo ((int) ($pipeline_id ?? 0) === (int) $p['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['name'] ?? $p['id']), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    <div class="row g-3">
        <div class="col-lg-7">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_stage_rules'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php foreach (($rules ?? []) as $rule): ?>
            <div class="border rounded p-3 mb-2 small">
                <div class="fw-semibold"><?php echo htmlspecialchars((string) (($rule['stage_name'] ?? '') . ' #' . ($rule['stage_id'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                <div>fields: <?php echo htmlspecialchars((string) ($rule['required_fields_json'] ?? '[]'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div>actions: <?php echo htmlspecialchars((string) ($rule['required_actions_json'] ?? '[]'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div>SLA: <?php echo htmlspecialchars((string) ($rule['sla_hours'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?>h · ownership: <?php echo !empty($rule['ownership_required']) ? 'yes' : 'no'; ?> · approval: <?php echo !empty($rule['approval_required']) ? 'yes' : 'no'; ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (($rules ?? []) === []): ?><p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <?php if (!empty($canManage)): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/workflow-governance/rules')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mt-3">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="pipeline_id" value="<?php echo (int) ($pipeline_id ?? 0); ?>">
                <div class="mb-2">
                    <label class="form-label">Stage</label>
                    <select name="stage_id" class="form-select form-select-sm" required>
                        <?php foreach (($stages ?? []) as $s): ?>
                        <option value="<?php echo (int) $s['id']; ?>"><?php echo htmlspecialchars((string) ($s['name'] ?? $s['id']), ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-2"><label class="form-label">Required fields (comma)</label><input class="form-control form-control-sm" name="required_fields" placeholder="name,amount,owner_user_id"></div>
                <div class="mb-2"><label class="form-label">Required actions (comma)</label><input class="form-control form-control-sm" name="required_actions" placeholder="call,meeting"></div>
                <div class="mb-2"><label class="form-label">SLA hours</label><input class="form-control form-control-sm" type="number" name="sla_hours" min="1"></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="ownership_required" value="1" checked><label class="form-check-label">Ownership required</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="approval_required" value="1"><label class="form-check-label">Approval required</label></div>
                <button class="btn btn-sm btn-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
            <?php endif; ?>
        </div>
        <div class="col-lg-5">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_sla_breaches'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php foreach (($sla_breaches ?? []) as $b): ?>
            <div class="border rounded p-2 mb-2 small">
                <?php echo htmlspecialchars((string) (($b['name'] ?? '') . ' · ' . ($b['stage_name'] ?? '') . ' · SLA ' . ($b['sla_hours'] ?? '') . 'h'), ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php endforeach; ?>
            <?php if (($sla_breaches ?? []) === []): ?><p class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        </div>
    </div>
</div>
