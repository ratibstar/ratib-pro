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
    <?php if (!empty($useUnifiedHr) && ($unifiedHrApk ?? '') !== '') {
        $dlMeta = is_array($unifiedHrDl ?? null) ? $unifiedHrDl : [];
        $dlSize = (float) ($dlMeta['size_mb'] ?? 0);
        $dlVc = (int) ($dlMeta['version_code'] ?? 0);
        ?>
    <div class="alert alert-warning py-2 small mb-3" role="alert" id="rateb-unified-download"
         data-opening-msg="<?php echo $e(__('mobile_activation_opening_app')); ?>">
        <?php echo $e(__('mobile_activation_install_unified_first')); ?>
        <p class="small text-danger mb-2 mt-2"><?php echo $e(__('mobile_activation_delete_branded_sibling')); ?></p>
        <p class="small text-danger mb-2"><?php echo $e(__('mobile_activation_clear_app_data')); ?></p>
        <?php if ($dlSize > 0 || $dlVc > 0) { ?>
        <div class="small font-monospace mt-1 mb-2" dir="ltr">
            sa.rateb.hr.mobile<?php echo $dlVc > 0 ? ' · build ' . $dlVc : ''; ?><?php echo $dlSize > 0 ? ' · ~' . $e((string) $dlSize) . ' MB' : ''; ?>
            <?php if (!empty($dlMeta['sha256_short'])) { ?>
            · sha256 <?php echo $e((string) $dlMeta['sha256_short']); ?>…
            <?php } ?>
        </div>
        <div class="small text-danger mb-2"><?php echo $e(__('mobile_activation_apk_size_warning')); ?></div>
        <?php } ?>
        <a class="btn btn-sm btn-primary w-100 mt-1" href="<?php echo $e($unifiedHrApk); ?>">
            <i class="fas fa-download"></i> <?php echo $e(__('mobile_activation_download_unified_hr')); ?>
        </a>
        <?php if ($hrOpenIntent !== '') { ?>
        <a class="btn btn-sm btn-success w-100 mt-2" id="rateb-unified-open" href="<?php echo $e($hrOpenIntent); ?>">
            <i class="fas fa-mobile-screen"></i> <?php echo $e(__('mobile_activation_open_unified_hr')); ?>
        </a>
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

    <?php if (!empty($hrApkPending)) { ?>
        <div class="alert alert-warning py-2 small mb-3" role="alert" id="rateb-hr-apk-pending">
            <p class="mb-2"><?php echo $e(__('mobile_activation_company_apk_pending')); ?></p>
            <p class="mb-2"><?php echo $e(__('mobile_activation_handoff_body_dedicated')); ?></p>
            <?php if (!empty($hrExpectedPackage)) { ?>
            <p class="mb-0 font-monospace small" dir="ltr"><?php echo $e((string) $hrExpectedPackage); ?></p>
            <?php } ?>
            <p class="mb-0 mt-2 small"><?php echo $e(__('mobile_activation_hr_wrong_unified_installed')); ?></p>
        </div>
    <?php } ?>
    <?php if ($apps === []) { ?>
        <div class="alert alert-warning py-2 small"><?php echo $e(__('mobile_activation_no_apps')); ?></div>
    <?php } else { ?>
        <div class="rateb-act-apps" data-rateb-web-only>
            <?php foreach ($apps as $row) {
                $cardHref = ($row['open'] ?? '') !== '' ? (string) $row['open'] : (($row['url'] ?? '') !== '' ? (string) $row['url'] : '#');
                ?>
            <a class="rateb-act-app rateb-act-app--<?php echo $e($row['app']); ?><?php echo !empty($row['apk_pending']) ? ' rateb-act-app--pending' : ''; ?>" role="button" tabindex="0"
               href="<?php echo $e($cardHref); ?>"
               data-rateb-download="<?php echo $e($row['url'] ?? ''); ?>"
               data-rateb-open="<?php echo $e($row['open'] ?? ''); ?>"
               data-rateb-open-android="<?php echo $e($row['open_android'] ?? ''); ?>"
               <?php if (($row['app'] ?? '') === 'hr' && !empty($useUnifiedHr)) { ?>data-rateb-apk-only="1"<?php } ?>
               <?php if (!empty($row['apk_pending'])) { ?>data-rateb-apk-pending="1"<?php } ?>>
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
<?php if (is_array($company ?? null) && !empty($useUnifiedHr)) { ?>
<script src="<?php echo $e(rateb_asset('js/app-activation-handoff.js')); ?>"></script>
<?php } ?>
