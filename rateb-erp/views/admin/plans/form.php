<?php
/** @var array<string, mixed>|null $item */
/** @var array<string, string> $moduleCatalog */
/** @var array<int, string> $selectedModules */
/** @var array<int, string> $tierPresets */

$isEdit = !empty($item);
$action = $isEdit ? rateb_url($routePrefix . '/' . (int) $item['id']) : rateb_url($routePrefix);
$tierPresets = $tierPresets ?? array_keys(\Rateb\App\Services\PlanLimitService::tierDefinitions());
$slug = strtolower(trim((string) ($item['slug'] ?? '')));
// Repair obviously corrupted labels in the form display (empty / "label" / slug leaked into name).
if ($isEdit && is_array($item)) {
    $dbName = trim((string) ($item['name'] ?? ''));
    $dbDesc = trim((string) ($item['description'] ?? ''));
    if ($dbName === '' || strtolower($dbName) === 'label' || ($slug !== '' && strtolower($dbName) === $slug)) {
        $item['name'] = \Rateb\App\Models\Plan::marketingName(array_merge($item, ['slug' => $slug]));
    }
    if ($dbDesc === '' || $dbDesc === '. ERP' || str_starts_with($dbDesc, '. ')) {
        $item['description'] = \Rateb\App\Models\Plan::marketingDescription(array_merge($item, ['slug' => $slug]));
    }
}
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <div class="d-flex gap-2">
            <button type="submit" form="ratebPlanForm" class="btn btn-primary btn-sm"><?php echo __('save'); ?></button>
            <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary btn-sm"><?php echo __('cancel'); ?></a>
        </div>
    </div>
    <div class="rateb-card-body">
        <?php if (!$isEdit && $tierPresets !== []) { ?>
        <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
            <span class="text-muted small"><?php echo __('plan_tier_presets'); ?>:</span>
            <?php foreach ($tierPresets as $tierSlug) {
                $tier = \Rateb\App\Services\PlanLimitService::tierForSlug((string) $tierSlug);
                if ($tier === null) {
                    continue;
                }
                ?>
            <a href="<?php echo rateb_url($routePrefix . '/create?tier=' . urlencode((string) $tierSlug)); ?>" class="btn btn-sm btn-outline-primary">
                <?php echo Rateb\App\Core\View::escape(\Rateb\App\Models\Plan::marketingName(['slug' => $tierSlug, 'name' => (string) ($tier['name'] ?? $tierSlug)])); ?>
            </a>
            <?php } ?>
        </div>
        <?php } ?>
        <form method="post" action="<?php echo $action; ?>" id="ratebPlanForm" accept-charset="UTF-8">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <input type="hidden" name="_utf8" value="✓">
            <div class="row g-3">
                <?php foreach ($fields as $field) {
                    $name = (string) ($field['name'] ?? '');
                    $type = (string) ($field['type'] ?? 'text');
                    $label = (string) ($field['label'] ?? $name);
                    $value = $item[$name] ?? '';
                    $isLtrField = $type === 'number' || $name === 'slug';
                    $inputClass = 'form-control' . ($isLtrField ? ' rateb-ltr-num' : '');
                    if ($type === 'number' && $value !== '' && $value !== null) {
                        $western = rateb_western_digits((string) $value);
                        $value = is_numeric($western) ? (string) (0 + $western) : $western;
                    }
                    $renderType = $type === 'number' ? 'text' : $type;
                    ?>
                <div class="col-md-<?php echo $type === 'textarea' ? '12' : '6'; ?>">
                    <label class="form-label" for="plan_field_<?php echo Rateb\App\Core\View::escape($name); ?>"><?php echo Rateb\App\Core\View::escape(__($label)); ?></label>
                    <?php if ($type === 'textarea') { ?>
                    <textarea class="form-control" id="plan_field_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>" rows="2" dir="auto"><?php echo Rateb\App\Core\View::escape((string) $value); ?></textarea>
                    <?php } else { ?>
                    <input class="<?php echo Rateb\App\Core\View::escape($inputClass); ?>" type="<?php echo Rateb\App\Core\View::escape($renderType); ?>" id="plan_field_<?php echo Rateb\App\Core\View::escape($name); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>"
                        value="<?php echo Rateb\App\Core\View::escape((string) $value); ?>"<?php echo $isLtrField ? ' dir="ltr" lang="en"' : ' dir="auto"'; ?><?php echo $type === 'number' ? ' inputmode="decimal" autocomplete="off"' : ''; ?><?php echo $name === 'name' || $name === 'slug' ? ' required' : ''; ?><?php echo $isEdit && $name === 'slug' ? ' readonly' : ''; ?>>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>

            <div class="mt-4">
                <h3 class="h6 mb-2"><?php echo __('plan_modules'); ?></h3>
                <p class="text-muted small"><?php echo __('plan_modules_help'); ?></p>
                <div class="row g-2">
                    <?php foreach ($moduleCatalog as $modKey => $modLabel) { ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="modules[]" value="<?php echo Rateb\App\Core\View::escape($modKey); ?>" id="plan_mod_<?php echo Rateb\App\Core\View::escape($modKey); ?>"
                                <?php echo in_array($modKey, $selectedModules, true) ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="plan_mod_<?php echo Rateb\App\Core\View::escape($modKey); ?>">
                                <?php echo __(is_string($modLabel) ? $modLabel : $modKey); ?>
                            </label>
                        </div>
                    </div>
                    <?php } ?>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2 sticky-bottom py-3 rateb-plan-form-actions">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
<style>
.rateb-plan-form-actions {
    position: sticky;
    bottom: 0;
    background: var(--bs-body-bg, #111827);
    border-top: 1px solid rgba(255,255,255,0.08);
    z-index: 5;
}
</style>
