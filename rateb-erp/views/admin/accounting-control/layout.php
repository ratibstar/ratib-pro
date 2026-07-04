<?php
/** @var string $accSection */
/** @var string $csrf */
/** @var int $companyId */
/** @var string $apiBase */
/** @var list<array{slug:string,label:string,route:string,icon:string,permission:string}> $accNav */
$accSection = $accSection ?? 'dashboard';
$route = defined('RATEB_CP_ROUTE') ? (string) RATEB_CP_ROUTE : rateb_current_public_path('admin/accounting-control');
?>
<link href="<?php echo rateb_asset('css/accounting-control/control-center.css'); ?>" rel="stylesheet">
<div class="acc-control-wrap" id="acc-control-app"
     data-section="<?php echo Rateb\App\Core\View::escape($accSection); ?>"
     data-api-base="<?php echo Rateb\App\Core\View::escape($apiBase); ?>"
     data-csrf="<?php echo Rateb\App\Core\View::escape($csrf); ?>"
     data-company-id="<?php echo (int) $companyId; ?>"
     data-lang="<?php echo Rateb\App\Core\View::escape(rateb_locale()); ?>"
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
                <input type="number" class="form-control form-control-sm acc-filter-company" value="<?php echo (int) $companyId ?: ''; ?>" min="0">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('branch'); ?></label>
                <input type="number" class="form-control form-control-sm acc-filter-branch" min="0">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('from_date'); ?></label>
                <input type="date" class="form-control form-control-sm acc-filter-from" value="<?php echo date('Y-m-01'); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo __('to_date'); ?></label>
                <input type="date" class="form-control form-control-sm acc-filter-to" value="<?php echo date('Y-m-d'); ?>">
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
                    <h5 class="modal-title">JSON</h5>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="<?php echo rateb_asset('js/accounting-control/control-center.js'); ?>"></script>
