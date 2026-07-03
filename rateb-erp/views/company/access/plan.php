<?php
/** @var array<string, mixed>|null $company */
/** @var array<string, mixed>|null $plan */
/** @var array<string, mixed>|null $subscription */
$modules = [];
if (is_array($plan) && !empty($plan['modules'])) {
    $decoded = json_decode((string) $plan['modules'], true);
    $modules = is_array($decoded) ? $decoded : [];
} elseif (is_array($company) && !empty($company['modules'])) {
    $decoded = json_decode((string) $company['modules'], true);
    $modules = is_array($decoded) ? $decoded : [];
}
$catalog = $moduleCatalog ?? [];
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('plans'); ?></div>
    <div class="rateb-card-body">
        <?php if ($plan === null) { ?>
        <p class="text-muted mb-0"><?php echo __('no_records'); ?></p>
        <?php } else { ?>
        <dl class="row mb-0">
            <dt class="col-sm-3"><?php echo __('name'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape((string) ($plan['name'] ?? '')); ?></dd>
            <dt class="col-sm-3"><?php echo __('Monthly'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape((string) ($plan['price_monthly'] ?? '0')); ?></dd>
            <dt class="col-sm-3"><?php echo __('Yearly'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape((string) ($plan['price_yearly'] ?? '0')); ?></dd>
            <?php if (is_array($subscription)) { ?>
            <dt class="col-sm-3"><?php echo __('status'); ?></dt>
            <dd class="col-sm-9"><?php echo Rateb\App\Core\View::escape((string) ($subscription['status'] ?? '')); ?></dd>
            <?php } ?>
        </dl>
        <?php if ($modules !== []) { ?>
        <h3 class="h6 mt-4"><?php echo __('modules'); ?></h3>
        <ul class="mb-0">
            <?php foreach ($modules as $mod) {
                $key = (string) $mod;
                $label = $catalog[$key]['label'] ?? $key;
                echo '<li>' . Rateb\App\Core\View::escape((string) $label) . '</li>';
            } ?>
        </ul>
        <?php } ?>
        <?php } ?>
    </div>
</div>
