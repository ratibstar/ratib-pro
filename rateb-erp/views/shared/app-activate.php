<?php
declare(strict_types=1);

/** @var array{name:string, logo:string}|null $company */
/** @var string|null $error */
/** @var string $code */
/** @var list<array{app:string, url:string, open:string}> $apps */
/** @var string $adminUrl */
$e = static fn ($v): string => Rateb\App\Core\View::escape((string) $v);
$icons = ['hr' => 'fa-id-badge', 'erp' => 'fa-building', 'customer' => 'fa-users'];
?>
<?php if (!is_array($company)) { ?>
<h1 class="h5 mb-3 text-center"><i class="fas fa-key"></i> <?php echo $e(__('mobile_activation_title')); ?></h1>
<?php } ?>

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
<?php } else {
    $hrOpenIntent = '';
    $hrAndroidIntent = '';
    foreach ($apps as $row) {
        if (($row['app'] ?? '') === 'hr') {
            if (($row['open'] ?? '') !== '') {
                $hrOpenIntent = (string) $row['open'];
            }
            if (($row['open_android'] ?? '') !== '') {
                $hrAndroidIntent = (string) $row['open_android'];
            }
            if ($hrOpenIntent !== '') {
                break;
            }
        }
    }
    ?>
    <?php if (!empty($useUnifiedHr) && ($unifiedHrApk ?? '') !== '') { ?>
    <div class="alert alert-warning py-2 small mb-3" role="alert">
        <?php echo $e(__('mobile_activation_install_unified_first')); ?>
        <a class="btn btn-sm btn-primary w-100 mt-2" id="rateb-unified-download" href="<?php echo $e($unifiedHrApk); ?>">
            <i class="fas fa-download"></i> <?php echo $e(__('mobile_activation_download_unified_hr')); ?>
        </a>
        <?php if ($hrOpenIntent !== '') { ?>
        <a class="btn btn-sm btn-success w-100 mt-2" id="rateb-unified-open"
           href="<?php echo $e($hrAndroidIntent !== '' ? $hrAndroidIntent : $hrOpenIntent); ?>">
            <i class="fas fa-mobile-screen"></i> <?php echo $e(__('mobile_activation_open_unified_hr')); ?>
        </a>
        <a class="btn btn-sm btn-outline-light w-100 mt-2 d-none" id="rateb-unified-open-https"
           href="<?php echo $e($hrOpenIntent); ?>"><?php echo $e(__('mobile_activation_open_unified_https')); ?></a>
        <p class="small text-muted mt-2 mb-0"><?php echo $e(__('mobile_activation_open_unified_hint')); ?></p>
        <?php } ?>
    </div>
    <?php } ?>
    <div class="rateb-act-head" data-rateb-act-code="<?php echo $e($code); ?>"
         data-rateb-unified-hr="<?php echo !empty($useUnifiedHr) ? '1' : '0'; ?>"
         <?php echo $hrOpenIntent !== '' ? ' data-rateb-hr-open="' . $e($hrOpenIntent) . '"' : ''; ?>
         <?php echo $hrAndroidIntent !== '' ? ' data-rateb-hr-android-intent="' . $e($hrAndroidIntent) . '"' : ''; ?>>
        <?php if (($company['logo'] ?? '') !== '') { ?>
            <img class="rateb-act-logo" src="<?php echo $e($company['logo']); ?>" alt="">
        <?php } else { ?>
            <span class="rateb-act-logo rateb-act-logo--icon"><i class="fas fa-building"></i></span>
        <?php } ?>
        <div class="rateb-act-name"><bdi><?php echo $e($company['name']); ?></bdi></div>
        <?php if (!empty($company['id']) || !empty($erpHost)) { ?>
        <div class="rateb-act-sub small text-muted">
            <?php if (!empty($company['id'])) { ?>
                <?php echo $e(str_replace(':id', (string) (int) $company['id'], __('mobile_activation_page_company_id'))); ?>
            <?php } ?>
            <?php if (!empty($erpHost)) { ?>
                · <span dir="ltr"><?php echo $e($erpHost); ?></span>
            <?php } ?>
        </div>
        <?php } ?>
        <div class="rateb-act-sub"><?php echo $e(__('mobile_activation_apps_title')); ?></div>
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
        <div class="rateb-act-apps" data-rateb-web-only>
            <?php foreach ($apps as $row) { ?>
            <a class="rateb-act-app rateb-act-app--<?php echo $e($row['app']); ?>" href="<?php echo $e($row['url']); ?>"
               <?php if (($row['open'] ?? '') !== '' && empty($useUnifiedHr)) { ?>data-rateb-app-intent="<?php echo $e($row['open']); ?>"<?php } ?>
               <?php if (($row['app'] ?? '') === 'hr' && !empty($useUnifiedHr)) { ?>data-rateb-apk-only="1"<?php } ?>>
                <span class="rateb-act-app-icon"><i class="fas <?php echo $e($icons[$row['app']] ?? 'fa-mobile'); ?>"></i></span>
                <span class="rateb-act-app-text">
                    <b><?php echo $e(__('mobile_app_short_' . $row['app'])); ?></b>
                    <small><bdi><?php echo $e($company['name']); ?></bdi></small>
                </span>
                <span class="rateb-act-app-dl"><i class="fas fa-download"></i></span>
            </a>
            <?php } ?>
        </div>
        <ol class="rateb-act-steps" data-rateb-web-only>
            <li><?php echo $e(__('mobile_activation_step_install')); ?></li>
            <li><?php echo $e(__('mobile_activation_step_open')); ?></li>
            <li><?php echo str_replace('%s', '<bdi dir="ltr" class="rateb-act-code">' . $e($code) . '</bdi>', $e(__('mobile_activation_step_code'))); ?></li>
        </ol>
    <?php } ?>
<?php } ?>
<script src="<?php echo $e(rateb_asset('js/app-company-activation.js')); ?>"></script>
<?php if (!empty($useUnifiedHr) && ($hrOpenIntent ?? '') !== '') { ?>
<script>
(function () {
    var https = document.getElementById('rateb-unified-open-https');
    if (https && /Android/i.test(navigator.userAgent || '')) {
        https.classList.remove('d-none');
    }
})();
</script>
<?php } ?>
