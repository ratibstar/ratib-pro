<?php
declare(strict_types=1);

/** @var string $app */
/** @var string $appLabel */
/** @var string $companyName */
/** @var string $companyLogo */
/** @var string $openUrl */
/** @var string $apkUrl */
/** @var string $activationCode */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
$icons = ['hr' => 'fa-id-badge', 'erp' => 'fa-building', 'customer' => 'fa-users'];
$apkUrl = trim($apkUrl);
?>
<div class="rateb-act-head">
    <?php if ($companyLogo !== '') { ?>
        <img class="rateb-act-logo" src="<?php echo $e($companyLogo); ?>" alt="">
    <?php } else { ?>
        <span class="rateb-act-logo rateb-act-logo--icon"><i class="fas fa-building"></i></span>
    <?php } ?>
    <div class="rateb-act-name"><bdi><?php echo $e($companyName); ?></bdi></div>
</div>

<div class="rateb-act-app rateb-act-app--<?php echo $e($app); ?> mb-3">
    <span class="rateb-act-app-icon"><i class="fas <?php echo $e($icons[$app] ?? 'fa-mobile'); ?>"></i></span>
    <span class="rateb-act-app-text">
        <b><?php echo $e($appLabel); ?></b>
        <small><bdi><?php echo $e($companyName); ?></bdi></small>
    </span>
</div>

<?php if ($openUrl !== '') { ?>
<a class="btn btn-primary btn-lg w-100 mb-2" href="<?php echo $e($openUrl); ?>" data-rateb-full-nav="1">
    <i class="fas fa-arrow-up-right-from-square"></i> <?php echo $e(__('mobile_open_app_button')); ?>
</a>
<?php } ?>
<?php if ($apkUrl !== '') { ?>
<a class="btn btn-outline-secondary w-100 mb-3" href="<?php echo $e($apkUrl); ?>" data-rateb-full-nav="1">
    <i class="fas fa-download"></i> <?php echo $e(__('mobile_open_app_download')); ?>
</a>
<?php } ?>
<p class="small text-muted mb-2"><?php echo $e(__('mobile_open_app_steps')); ?></p>
<p class="small mb-0"><?php echo $e(__('mobile_activation_launch_manual_code')); ?>
    <bdi dir="ltr" class="rateb-act-code font-monospace"><?php echo $e($activationCode); ?></bdi></p>
