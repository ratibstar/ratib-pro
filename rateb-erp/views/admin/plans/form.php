<?php
/** @var array<string, mixed>|null $item */
/** @var array<string, string> $moduleCatalog */
/** @var array<int, string> $selectedModules */
/** @var array<int, string> $tierPresets */

$isEdit = !empty($item);
$action = $isEdit ? rateb_url($routePrefix . '/' . (int) $item['id']) : rateb_url($routePrefix);
$tierPresets = $tierPresets ?? array_keys(\Rateb\App\Services\PlanLimitService::tierDefinitions());
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
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
        <form method="post" action="<?php echo $action; ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="row g-3">
                <?php foreach ($fields as $field) {
                    $name = (string) ($field['name'] ?? '');
                    $type = (string) ($field['type'] ?? 'text');
                    $label = (string) ($field['label'] ?? $name);
                    // Use stored DB values so admin edits stick (marketing pages use lang labels separately).
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
                    <label class="form-label"><?php echo Rateb\App\Core\View::escape(__($label)); ?></label>
                    <?php if ($type === 'textarea') { ?>
                    <textarea class="form-control" name="<?php echo Rateb\App\Core\View::escape($name); ?>" rows="2"><?php echo Rateb\App\Core\View::escape((string) $value); ?></textarea>
                    <?php } else { ?>
                    <input class="<?php echo Rateb\App\Core\View::escape($inputClass); ?>" type="<?php echo Rateb\App\Core\View::escape($renderType); ?>" name="<?php echo Rateb\App\Core\View::escape($name); ?>"
                        value="<?php echo Rateb\App\Core\View::escape((string) $value); ?>"<?php echo $isLtrField ? ' dir="ltr" lang="en"' : ''; ?><?php echo $type === 'number' ? ' inputmode="decimal" autocomplete="off"' : ''; ?><?php echo $name === 'name' || $name === 'slug' ? ' required' : ''; ?><?php echo $isEdit && $name === 'slug' ? ' readonly' : ''; ?>>
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

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
