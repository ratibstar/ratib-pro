<?php
declare(strict_types=1);
/**
 * Push RATEB ERP database updates (migrations) from platform to agency ERP databases.
 */
if (!defined('IS_CONTROL_PANEL')) {
    define('IS_CONTROL_PANEL', true);
}
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/control-permissions.php';
require_once __DIR__ . '/../../includes/control/ErpProvisioningService.php';
require_once __DIR__ . '/../../includes/control/rateb-erp-bridge.php';

if (empty($_SESSION['control_logged_in'])) {
    header('Location: ' . pageUrl('login.php'));
    exit;
}
requireControlPermission(CONTROL_PERM_AGENCIES, 'view_control_agencies', 'edit_control_agency');

$ctrl = $GLOBALS['control_conn'] ?? null;
if (!$ctrl || !($ctrl instanceof mysqli)) {
    die('Control panel database unavailable.');
}

ErpProvisioningService::ensureErpColumns($ctrl);
$agencies = ErpProvisioningService::listErpAgencies($ctrl, false);
$platformDb = control_rateb_erp_db_name();
$apiUrl = function_exists('control_control_api_base_url')
    ? rtrim(control_control_api_base_url(), '/') . '/agencies-erp-migrate.php'
    : '/api/control/agencies-erp-migrate.php';

require_once __DIR__ . '/../../includes/control/layout-wrapper.php';
startControlLayout(cp_t('erp_updates.title'), ['css/control/system.css', 'css/control/rateb-erp-hub.css'], ['js/control/erp-agency-updates.js']);
?>
<div class="card gov-card mb-3">
    <div class="card-body">
        <h2 class="h5 mb-2"><i class="fas fa-cloud-upload-alt me-2"></i><?php echo htmlspecialchars(cp_t('erp_updates.title'), ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="text-muted small mb-2"><?php echo htmlspecialchars(cp_t('erp_updates.intro'), ENT_QUOTES, 'UTF-8'); ?></p>
        <ul class="small text-muted mb-0">
            <li><?php echo htmlspecialchars(cp_t('erp_updates.note_code'), ENT_QUOTES, 'UTF-8'); ?></li>
            <li><?php echo htmlspecialchars(cp_t('erp_updates.note_db'), ENT_QUOTES, 'UTF-8'); ?></li>
        </ul>
    </div>
</div>

<div class="card gov-card mb-3">
    <div class="card-body">
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="erpUpdateIncludePlatform" checked>
            <label class="form-check-label" for="erpUpdateIncludePlatform">
                <?php echo htmlspecialchars(cp_t('erp_updates.include_platform'), ENT_QUOTES, 'UTF-8'); ?>
                <code><?php echo htmlspecialchars($platformDb, ENT_QUOTES, 'UTF-8'); ?></code>
            </label>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <button type="button" class="btn btn-primary btn-sm" id="erpUpdateRunSelected" disabled>
                <i class="fas fa-play me-1"></i><?php echo htmlspecialchars(cp_t('erp_updates.run_selected'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="erpUpdateRunAllReady">
                <i class="fas fa-layer-group me-1"></i><?php echo htmlspecialchars(cp_t('erp_updates.run_all_ready'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <button type="button" class="btn btn-outline-success btn-sm" id="erpUpdateRunSubscribed">
                <i class="fas fa-check-circle me-1"></i><?php echo htmlspecialchars(cp_t('erp_updates.run_subscribed'), ENT_QUOTES, 'UTF-8'); ?>
            </button>
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(control_rateb_erp_public_url('admin'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                <i class="fas fa-external-link-alt me-1"></i><?php echo htmlspecialchars(cp_t('erp_updates.open_platform_admin'), ENT_QUOTES, 'UTF-8'); ?>
            </a>
        </div>
        <div id="erpUpdateProgress" class="alert alert-secondary py-2 small d-none" role="status"></div>
        <div id="erpUpdateResults" class="d-none">
            <pre class="rateb-erp-migrate-log bg-dark text-light p-3 rounded small mb-0" id="erpUpdateLog"></pre>
        </div>
    </div>
</div>

<div class="card gov-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" id="erpAgencyUpdatesTable">
                <thead>
                    <tr>
                        <th style="width:2.5rem"><input type="checkbox" id="erpUpdateSelectAll" title="Select all"></th>
                        <th><?php echo htmlspecialchars(cp_t('erp_updates.col_agency'), ENT_QUOTES, 'UTF-8'); ?></th>
                        <th><?php echo htmlspecialchars(cp_t('erp_updates.col_erp_db'), ENT_QUOTES, 'UTF-8'); ?></th>
                        <th><?php echo htmlspecialchars(cp_t('erp_updates.col_status'), ENT_QUOTES, 'UTF-8'); ?></th>
                        <th><?php echo htmlspecialchars(cp_t('erp_updates.col_plan'), ENT_QUOTES, 'UTF-8'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($agencies === []): ?>
                    <tr><td colspan="6" class="text-muted p-3"><?php echo htmlspecialchars(cp_t('erp_updates.empty'), ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($agencies as $row): ?>
                    <?php
                        $aid = (int) ($row['id'] ?? 0);
                        $erpSt = strtolower(trim((string) ($row['erp_status'] ?? 'none')));
                        $siteUrl = trim((string) ($row['site_url'] ?? ''));
                        $erpAdmin = $siteUrl !== '' ? rtrim($siteUrl, '/') . '/rateb-erp/public/admin' : '';
                    ?>
                    <tr>
                        <td><input type="checkbox" class="erp-update-agency-cb" value="<?php echo $aid; ?>"></td>
                        <td><?php echo htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> <span class="text-muted">#<?php echo $aid; ?></span></td>
                        <td><code><?php echo htmlspecialchars((string) ($row['erp_db_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></code></td>
                        <td><span class="badge bg-<?php echo $erpSt === 'ready' ? 'success' : ($erpSt === 'failed' ? 'danger' : 'secondary'); ?>"><?php echo htmlspecialchars($erpSt, ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><?php echo htmlspecialchars((string) ($row['erp_plan_slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="text-end">
                            <?php if ($erpAdmin !== ''): ?>
                            <a href="<?php echo htmlspecialchars($erpAdmin, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-link btn-sm" target="_blank" rel="noopener">ERP</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="erpAgencyUpdatesConfig" class="d-none"
     data-api-url="<?php echo htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8'); ?>"
     data-confirm-selected="<?php echo htmlspecialchars(cp_t('erp_updates.confirm_selected'), ENT_QUOTES, 'UTF-8'); ?>"
     data-confirm-all="<?php echo htmlspecialchars(cp_t('erp_updates.confirm_all'), ENT_QUOTES, 'UTF-8'); ?>"
     data-confirm-subscribed="<?php echo htmlspecialchars(cp_t('erp_updates.confirm_subscribed'), ENT_QUOTES, 'UTF-8'); ?>"></div>
<?php
endControlLayout();
