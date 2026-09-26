<?php
declare(strict_types=1);

/** @var array{name:string}|null $company */
/** @var string|null $error */
/** @var string $code */
/** @var list<array{app:string, url:string}> $apps */
/** @var string $adminUrl */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
$icons = ['hr' => 'fa-id-badge', 'erp' => 'fa-building', 'customer' => 'fa-users'];
?>
<h1 class="h5 mb-3 text-center"><i class="fas fa-key"></i> <?php echo $e(__('mobile_activation_title')); ?></h1>

<?php if (!empty($error)) { ?>
    <div class="alert alert-danger py-2 small" role="alert"><?php echo $e($error); ?></div>
<?php } ?>

<?php if (!is_array($company)) { ?>
    <form method="get" action="<?php echo $e(rateb_url('app-activate')); ?>">
        <label class="form-label" for="activation-code"><?php echo $e(__('mobile_activation_code')); ?></label>
        <input type="text" class="form-control text-center font-monospace fs-5 mb-3" id="activation-code" name="code" required
               placeholder="ABCD-2345" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="20" dir="ltr">
        <button type="submit" class="btn btn-primary w-100"><?php echo $e(__('mobile_activation_continue')); ?></button>
    </form>
    <p class="small text-muted mt-3 mb-0 text-center"><?php echo $e(__('mobile_activation_form_hint')); ?></p>
    <p class="small mt-2 mb-0 text-center d-none" data-rateb-app-only>
        <a href="<?php echo $e(rateb_url('login') . '?stay=1'); ?>" data-rateb-app-clear><?php echo $e(__('mobile_activation_use_platform')); ?></a>
    </p>
<?php } else { ?>
    <div class="text-center mb-3">
        <div class="fw-bold fs-5"><?php echo $e($company['name']); ?></div>
        <div class="small text-muted"><?php echo $e(__('mobile_activation_code')); ?></div>
        <div class="fs-3 fw-bold font-monospace" dir="ltr"><?php echo $e($code); ?></div>
    </div>

    <?php if ($adminUrl !== '') { ?>
    <div class="d-none mb-3" data-rateb-app-only>
        <a class="btn btn-primary w-100" href="<?php echo $e($adminUrl); ?>" data-rateb-app-open="<?php echo $e($adminUrl); ?>">
            <i class="fas fa-right-to-bracket"></i> <?php echo $e(__('mobile_activation_open_company')); ?>
        </a>
    </div>
    <?php } ?>

    <?php if ($apps === []) { ?>
        <div class="alert alert-warning py-2 small"><?php echo $e(__('mobile_activation_no_apps')); ?></div>
    <?php } else { ?>
        <div class="list-group mb-3" data-rateb-web-only>
            <?php foreach ($apps as $row) { ?>
            <a class="list-group-item list-group-item-action d-flex align-items-center gap-2" href="<?php echo $e($row['url']); ?>">
                <i class="fas <?php echo $e($icons[$row['app']] ?? 'fa-mobile'); ?> fa-fw"></i>
                <span class="flex-grow-1"><?php echo $e(__('mobile_apps_tab_' . $row['app'])); ?></span>
                <i class="fas fa-download"></i>
            </a>
            <?php } ?>
        </div>
        <ol class="small text-muted ps-3 mb-0" data-rateb-web-only>
            <li><?php echo $e(__('mobile_activation_step_install')); ?></li>
            <li><?php echo $e(__('mobile_activation_step_open')); ?></li>
            <li><?php echo $e(__('mobile_activation_step_code')); ?></li>
        </ol>
    <?php } ?>
<?php } ?>
<script src="<?php echo $e(rateb_asset('js/app-company-activation.js')); ?>"></script>
