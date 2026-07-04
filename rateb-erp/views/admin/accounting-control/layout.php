<?php
/** @var string $accSection */
/** @var string $csrf */
/** @var int $companyId */
/** @var string $apiBase */
/** @var list<array{slug:string,label:string,route:string,icon:string,permission:string}> $accNav */
$accSection = $accSection ?? 'dashboard';
$accLocale = rateb_locale();
$accInputLang = $accLocale === 'ar' ? 'ar-SA' : 'en';
$accDateManaged = $accLocale === 'ar' ? ' data-acc-locale-managed="1"' : '';
$accFromIso = date('Y-m-01');
$accToIso = date('Y-m-d');
$accFromEn = date('m/d/Y', strtotime($accFromIso));
$accToEn = date('m/d/Y', strtotime($accToIso));
$accI18n = require RATEB_VIEWS_PATH . '/admin/accounting-control/i18n-payload.php';
$route = defined('RATEB_CP_ROUTE') ? (string) RATEB_CP_ROUTE : rateb_current_public_path('admin/accounting-control');
$accAssetVer = defined('RATEB_ASSET_BUILD') ? (string) RATEB_ASSET_BUILD : '1';
$accAssetsBase = rateb_site_origin() . rateb_erp_app_prefix() . '/assets/accounting-control';
$accCssUrl = $accAssetsBase . '/control-center.css?v=' . rawurlencode($accAssetVer);
$accJsUrl = $accAssetsBase . '/control-center.js?v=' . rawurlencode($accAssetVer);
?>
<link href="<?php echo Rateb\App\Core\View::escape($accCssUrl); ?>" rel="stylesheet">
<div class="acc-control-wrap" id="acc-control-app"
     data-section="<?php echo Rateb\App\Core\View::escape($accSection); ?>"
     data-api-base="<?php echo Rateb\App\Core\View::escape($apiBase); ?>"
     data-csrf="<?php echo Rateb\App\Core\View::escape($csrf); ?>"
     data-company-id="<?php echo (int) $companyId; ?>"
     data-lang="<?php echo Rateb\App\Core\View::escape($accLocale); ?>"
     data-dir="<?php echo rateb_is_rtl() ? 'rtl' : 'ltr'; ?>">
    <header class="acc-control-header">
        <div class="acc-control-brand">
            <i class="fas fa-shield-halved"></i>
            <div>
                <h1><?php echo __('accounting_control_center'); ?></h1>
                <p class="text-muted mb-0"><?php echo __('accounting_control_subtitle'); ?></p>
            </div>
        </div>
        <div class="acc-control-toolbar">
            <input type="search" class="form-control form-control-sm acc-global-search" placeholder="<?php echo __('search'); ?>…" aria-label="<?php echo __('search'); ?>">
            <button type="button" class="btn btn-sm btn-outline-secondary acc-btn-refresh" title="<?php echo __('refresh'); ?>">
                <i class="fas fa-sync"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary acc-btn-export" title="CSV">
                <i class="fas fa-file-csv"></i>
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary acc-btn-print" title="<?php echo __('print'); ?>">
                <i class="fas fa-print"></i>
            </button>
        </div>
    </header>
    <nav class="acc-control-nav" aria-label="<?php echo __('accounting_control_center'); ?>">
        <?php foreach ($accNav as $item) {
            if (!rateb_is_super_admin() && !rateb_can($item['permission'])) {
                continue;
            }
            $href = rateb_app_url($item['route']);
            $active = ($accSection === $item['slug']) ? ' active' : '';
            ?>
        <a href="<?php echo $href; ?>" class="acc-nav-item<?php echo $active; ?>">
            <i class="fas <?php echo Rateb\App\Core\View::escape($item['icon']); ?>"></i>
            <span><?php echo Rateb\App\Core\View::escape($item['label']); ?></span>
        </a>
        <?php } ?>
    </nav>
    <main class="acc-control-main">
        <div class="acc-filters row g-2 mb-3">
            <div class="col-md-2">
                <label class="form-label"><?php echo __('company'); ?></label>
                <input type="text" inputmode="numeric" pattern="[0-9]*" class="form-control form-control-sm acc-filter-company acc-locale-num" lang="<?php echo Rateb\App\Core\View::escape($accInputLang); ?>" dir="ltr" translate="no" autocomplete="off" value="<?php echo (int) $companyId ?: ''; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('branch'); ?></label>
                <input type="text" inputmode="numeric" pattern="[0-9]*" class="form-control form-control-sm acc-filter-branch acc-locale-num" lang="<?php echo Rateb\App\Core\View::escape($accInputLang); ?>" dir="ltr" translate="no" autocomplete="off">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('from_date'); ?></label>
                <?php if ($accLocale === 'ar') { ?>
                <input type="date" class="form-control form-control-sm acc-filter-from acc-locale-date" lang="ar-SA" dir="ltr" translate="no"<?php echo $accDateManaged; ?> autocomplete="off" value="<?php echo Rateb\App\Core\View::escape($accFromIso); ?>">
                <?php } else { ?>
                <input type="text" class="form-control form-control-sm acc-filter-from acc-locale-date acc-date-text rateb-ltr-date" lang="en" dir="ltr" translate="no" autocomplete="off" placeholder="MM/DD/YYYY" data-iso="<?php echo Rateb\App\Core\View::escape($accFromIso); ?>" value="<?php echo Rateb\App\Core\View::escape($accFromEn); ?>">
                <?php } ?>
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('to_date'); ?></label>
                <?php if ($accLocale === 'ar') { ?>
                <input type="date" class="form-control form-control-sm acc-filter-to acc-locale-date" lang="ar-SA" dir="ltr" translate="no"<?php echo $accDateManaged; ?> autocomplete="off" value="<?php echo Rateb\App\Core\View::escape($accToIso); ?>">
                <?php } else { ?>
                <input type="text" class="form-control form-control-sm acc-filter-to acc-locale-date acc-date-text rateb-ltr-date" lang="en" dir="ltr" translate="no" autocomplete="off" placeholder="MM/DD/YYYY" data-iso="<?php echo Rateb\App\Core\View::escape($accToIso); ?>" value="<?php echo Rateb\App\Core\View::escape($accToEn); ?>">
                <?php } ?>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-primary btn-sm w-100 acc-btn-apply-filters"><?php echo __('apply'); ?></button>
            </div>
        </div>
        <div id="acc-alert" class="alert d-none" role="alert"></div>
        <div id="acc-content" class="acc-content" aria-live="polite">
            <?php
            $sectionFile = RATEB_VIEWS_PATH . '/admin/accounting-control/sections/' . preg_replace('/[^a-z0-9_-]/', '', $accSection) . '.php';
            if (is_file($sectionFile)) {
                include $sectionFile;
            }
            ?>
        </div>
    </main>
    <div class="modal fade" id="accJsonModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('accounting_control_btn_json'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <pre class="modal-body acc-json-viewer mb-0"></pre>
            </div>
        </div>
    </div>
    <div class="modal fade" id="accConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('confirm_action'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body acc-confirm-message"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="button" class="btn btn-danger acc-confirm-proceed"><?php echo __('confirm'); ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="application/json" id="acc-control-i18n"><?php echo json_encode($accI18n, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?php echo Rateb\App\Core\View::escape($accJsUrl); ?>"></script>
