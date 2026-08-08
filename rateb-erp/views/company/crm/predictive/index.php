<?php
declare(strict_types=1);
$evaluation = $evaluation ?? ['matches' => [], 'counts' => []];
$matches = is_array($evaluation['matches'] ?? null) ? $evaluation['matches'] : [];
$hasAnyMatch = false;
foreach ($matches as $rows) {
    if (is_array($rows) && $rows !== []) {
        $hasAnyMatch = true;
        break;
    }
}
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('crm_predictive_rules')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="row g-3 mb-4">
        <?php foreach (($evaluation['counts'] ?? []) as $type => $cnt): ?>
        <div class="col-6 col-md"><div class="border rounded p-3"><div class="small text-muted"><?php echo htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4"><?php echo (int) $cnt; ?></div></div></div>
        <?php endforeach; ?>
        <?php if (($evaluation['counts'] ?? []) === []): ?>
        <div class="col-12 border rounded px-3"><?php require __DIR__ . '/../../partials/crm-empty.php'; ?></div>
        <?php endif; ?>
    </div>
    <div class="row g-3">
        <div class="col-lg-5">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_rules'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php foreach (($rules ?? []) as $rule): ?>
            <div class="border rounded p-3 mb-2 small">
                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($rule['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><?php echo htmlspecialchars((string) (($rule['rule_key'] ?? '') . ' · ' . ($rule['rule_type'] ?? '') . ' · p' . ($rule['priority'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="text-muted"><?php echo htmlspecialchars((string) ($rule['config_json'] ?? '{}'), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <?php endforeach; ?>
            <?php if (($rules ?? []) === []): ?>
                <div class="border rounded px-3"><?php require __DIR__ . '/../../partials/crm-empty.php'; ?></div>
            <?php endif; ?>
            <?php if (!empty($canManage)): ?>
            <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/predictive/rules')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mt-2">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
                <input class="form-control form-control-sm mb-2" name="rule_key" placeholder="rule_key" required>
                <input class="form-control form-control-sm mb-2" name="name" placeholder="name" required>
                <input class="form-control form-control-sm mb-2" name="rule_type" placeholder="rule_type e.g. high_probability" required>
                <textarea class="form-control form-control-sm mb-2" name="config_json" rows="3">{"min_probability":70}</textarea>
                <input class="form-control form-control-sm mb-2" type="number" name="priority" value="100">
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_enabled" value="1" checked><label class="form-check-label">Enabled</label></div>
                <button class="btn btn-sm btn-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            </form>
            <?php endif; ?>
        </div>
        <div class="col-lg-7">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_rule_matches'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="border rounded p-3" style="max-height:520px;overflow:auto">
                <?php if (!$hasAnyMatch): ?>
                    <?php require __DIR__ . '/../../partials/crm-empty.php'; ?>
                <?php else: ?>
                    <?php foreach ($matches as $type => $rows): ?>
                        <?php if (!is_array($rows) || $rows === []) { continue; } ?>
                        <h3 class="h6 mt-2"><?php echo htmlspecialchars((string) $type, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <ul class="list-unstyled small mb-3">
                            <?php foreach ($rows as $row): ?>
                            <li class="mb-2 pb-2 border-bottom">
                                #<?php echo (int) ($row['id'] ?? 0); ?>
                                · <?php echo htmlspecialchars((string) ($row['name'] ?? $row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                <?php if (isset($row['probability_percent'])): ?>
                                    · <?php echo (int) $row['probability_percent']; ?>%
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
