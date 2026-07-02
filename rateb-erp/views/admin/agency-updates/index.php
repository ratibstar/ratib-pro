<?php
/** @var list<array<string, mixed>> $agencies */
/** @var string $platformDb */
/** @var int $suggestedAgencyId */
/** @var int $opsCompanyId */
/** @var array<int, string> $companyNames */
/** @var string $csrf */
/** @var string $pushUrl */
/** @var string $linkUrl */
/** @var string $syncUrl */
/** @var string $restoreAdminUrl */
/** @var string $resetDataUrl */
/** @var string $syncSource */
$readyCount = 0;
$subscribedCount = 0;
foreach ($agencies as $agency) {
    $st = (string) ($agency['erp_status'] ?? 'none');
    if ($st === 'ready') {
        $readyCount++;
    }
    if ($st === 'ready' && !empty($agency['is_active'])) {
        $subscribedCount++;
    }
}
?>
<div class="rateb-page-header mb-3">
    <h1 class="h4 mb-1"><i class="fas fa-cloud-upload-alt me-2"></i><?php echo __('agency_erp_push_title'); ?></h1>
    <p class="text-muted small mb-0"><?php echo __('agency_erp_control_intro'); ?></p>
</div>

<?php if ($agencies === []) { ?>
<div class="alert alert-warning"><?php echo __('agency_erp_push_no_agencies'); ?></div>
<?php } else { ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-body py-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1" for="erpAgencyFilterCompany"><?php echo __('agency_erp_filter_company'); ?></label>
                <select class="form-select form-select-sm" id="erpAgencyFilterCompany">
                    <option value=""><?php echo __('agency_erp_filter_all_companies'); ?></option>
                    <?php foreach ($companyNames as $coId => $coName) { ?>
                    <option value="<?php echo (int) $coId; ?>"<?php echo $opsCompanyId === (int) $coId ? ' selected' : ''; ?>>
                        <?php echo Rateb\App\Core\View::escape($coName); ?>
                    </option>
                    <?php } ?>
                    <option value="0"><?php echo __('agency_erp_filter_unlinked'); ?></option>
                </select>
                <p class="small text-muted mb-0 mt-1"><?php echo __('agency_erp_reset_platform_company_hint'); ?></p>
            </div>
            <div class="col-md-8 d-flex flex-wrap gap-2 align-items-center">
                <span class="small text-muted me-1"><?php echo __('agency_erp_quick_select'); ?>:</span>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="erpSelectAllRows">
                    <i class="fas fa-check-double me-1"></i><?php echo __('select_all'); ?>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="erpSelectReadyRows">
                    <i class="fas fa-layer-group me-1"></i><?php echo __('agency_erp_push_run_all_ready'); ?>
                    <span class="badge bg-secondary ms-1"><?php echo $readyCount; ?></span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="erpSelectSubscribedRows">
                    <i class="fas fa-check-circle me-1"></i><?php echo __('agency_erp_push_run_subscribed'); ?>
                    <span class="badge bg-secondary ms-1"><?php echo $subscribedCount; ?></span>
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="erpSelectNoneRows">
                    <i class="fas fa-times me-1"></i><?php echo __('agency_erp_clear_selection'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="rateb-card mb-3">
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0 rateb-table" id="erpAgencyUpdatesTable" data-rateb-bulk-table="1">
                <thead>
                    <tr>
                        <th class="rateb-bulk-th">
                            <input type="checkbox" class="form-check-input" id="erpUpdateSelectAll" data-rateb-select-all title="<?php echo __('select_all'); ?>" aria-label="<?php echo __('select_all'); ?>">
                        </th>
                        <th><?php echo __('agency_erp_push_col_agency'); ?></th>
                        <th><?php echo __('agency_erp_push_col_platform_company'); ?></th>
                        <th><?php echo __('agency_erp_push_col_db'); ?></th>
                        <th><?php echo __('agency_erp_push_col_status'); ?></th>
                        <th><?php echo __('agency_erp_push_col_site'); ?></th>
                        <th class="text-end"><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agencies as $agency) {
                        $id = (int) ($agency['id'] ?? 0);
                        $name = (string) ($agency['name'] ?? '');
                        $erpDb = (string) ($agency['erp_db_name'] ?? '');
                        $status = (string) ($agency['erp_status'] ?? 'none');
                        $site = rtrim((string) ($agency['site_url'] ?? ''), '/');
                        $linkedCoId = (int) ($agency['erp_company_id'] ?? 0);
                        $linkedCoName = $linkedCoId > 0 ? (string) ($companyNames[$linkedCoId] ?? ('#' . $linkedCoId)) : '';
                        $isReady = $status === 'ready';
                        $isSubscribed = $isReady && !empty($agency['is_active']);
                        ?>
                    <tr class="erp-agency-row"
                        data-agency-id="<?php echo $id; ?>"
                        data-erp-company-id="<?php echo $linkedCoId; ?>"
                        data-erp-status="<?php echo Rateb\App\Core\View::escape($status); ?>"
                        data-subscribed="<?php echo $isSubscribed ? '1' : '0'; ?>"
                        data-ready="<?php echo $isReady ? '1' : '0'; ?>"
                        data-resettable="<?php echo $erpDb !== '' ? '1' : '0'; ?>"
                        data-site-url="<?php echo Rateb\App\Core\View::escape($site); ?>">
                        <td class="rateb-bulk-td">
                            <input type="checkbox" class="form-check-input rateb-row-check erp-update-agency-cb" value="<?php echo $id; ?>" data-rateb-row-check>
                        </td>
                        <td><?php echo Rateb\App\Core\View::escape($name); ?></td>
                        <td>
                            <?php if ($linkedCoName !== '') { ?>
                            <?php echo Rateb\App\Core\View::escape($linkedCoName); ?>
                            <?php } else { ?>
                            <span class="text-muted">—</span>
                            <?php } ?>
                        </td>
                        <td><code><?php echo Rateb\App\Core\View::escape($erpDb); ?></code></td>
                        <td><?php echo Rateb\App\Core\View::escape($status); ?></td>
                        <td>
                            <?php if ($site !== '') {
                                $openUrl = $site;
                                if ($isReady && $erpDb !== '') {
                                    $openUrl = rtrim($site, '/') . '/rateb-erp/public/admin';
                                }
                                ?>
                            <a href="<?php echo Rateb\App\Core\View::escape($openUrl); ?>" target="_blank" rel="noopener" title="<?php echo Rateb\App\Core\View::escape($openUrl); ?>"><?php echo Rateb\App\Core\View::escape($site); ?></a>
                            <?php } else { ?>
                            <span class="text-muted">—</span>
                            <?php } ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <div class="dropdown d-inline-block">
                                <button type="button" class="btn btn-link btn-sm p-0 erp-link-row-btn"
                                    data-agency-id="<?php echo $id; ?>"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="fas fa-link me-1"></i><?php echo __('agency_erp_push_link_now'); ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <?php foreach ($companyNames as $coId => $coName) { ?>
                                    <li>
                                        <button type="button" class="dropdown-item erp-link-pick-btn"
                                            data-agency-id="<?php echo $id; ?>"
                                            data-company-id="<?php echo (int) $coId; ?>">
                                            <?php echo Rateb\App\Core\View::escape($coName); ?>
                                        </button>
                                    </li>
                                    <?php } ?>
                                </ul>
                            </div>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="rateb-card mb-3 border-primary" id="erpAgencyCommandBar">
    <div class="rateb-card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="badge bg-primary fs-6" id="erpSelectionBadge">0 <?php echo __('agency_erp_push_bulk_label'); ?></span>
            <?php if ($syncSource !== '') { ?>
            <span class="small text-muted ms-md-2">
                <i class="fas fa-folder-open me-1"></i><code class="small"><?php echo Rateb\App\Core\View::escape($syncSource); ?></code>
            </span>
            <?php } ?>
        </div>

        <div class="row g-3 align-items-end">
            <div class="col-lg-3 col-md-4">
                <label class="form-label small mb-1" for="erpSyncConfirmInput"><?php echo __('agency_erp_sync_confirm_label'); ?></label>
                <input type="text" class="form-control form-control-sm font-monospace" id="erpSyncConfirmInput" autocomplete="off" spellcheck="false" placeholder="SYNC">
            </div>
            <div class="col-lg-9 col-md-8">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="erpUpdateIncludePlatform" checked>
                    <label class="form-check-label small" for="erpUpdateIncludePlatform">
                        <?php echo __('agency_erp_push_include_platform'); ?>
                        <?php if ($platformDb !== '') { ?>
                        <code><?php echo Rateb\App\Core\View::escape($platformDb); ?></code>
                        <?php } ?>
                    </label>
                </div>
                <p class="small fw-semibold mb-2 text-muted"><?php echo __('agency_erp_actions_on_selection'); ?></p>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="erpSyncRunSelected" disabled>
                        <i class="fas fa-clone me-1"></i><?php echo __('agency_erp_sync_run_selected'); ?>
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="erpUpdateRunSelected" disabled>
                        <i class="fas fa-database me-1"></i><?php echo __('agency_erp_push_db_selected'); ?>
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="erpFullDeploySelected" disabled>
                        <i class="fas fa-rocket me-1"></i><?php echo __('agency_erp_full_deploy_selected'); ?>
                    </button>
                    <button type="button" class="btn btn-warning btn-sm" id="erpRestoreAdminSelected" disabled>
                        <i class="fas fa-user-shield me-1"></i><?php echo __('agency_erp_restore_admin_selected'); ?>
                    </button>
                </div>
                <p class="small fw-semibold mb-2 text-muted"><?php echo __('agency_erp_actions_on_all'); ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="erpSyncRunAllReady">
                        <i class="fas fa-clone me-1"></i><?php echo __('agency_erp_sync_all_ready'); ?>
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="erpUpdateRunAllReady">
                        <i class="fas fa-database me-1"></i><?php echo __('agency_erp_push_all_ready'); ?>
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" id="erpFullDeployAllReady">
                        <i class="fas fa-rocket me-1"></i><?php echo __('agency_erp_full_deploy_all_ready'); ?>
                    </button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="erpUpdateRunSubscribed">
                        <i class="fas fa-check-circle me-1"></i><?php echo __('agency_erp_push_run_subscribed'); ?>
                    </button>
                </div>
            </div>
        </div>
        <p class="text-warning small mb-0 mt-3"><?php echo __('agency_erp_sync_warning'); ?></p>
        <p class="text-muted small mb-0 mt-1"><?php echo __('agency_erp_reset_moved_hint'); ?></p>
    </div>
</div>

<div id="erpUpdateProgress" class="alert alert-secondary py-2 small d-none mb-3" role="status"></div>
<div id="erpUpdateResults" class="d-none mb-3">
    <pre class="bg-dark text-light p-3 rounded small mb-0" id="erpUpdateLog" style="max-height:420px;overflow:auto;"></pre>
</div>
<?php } ?>

<div id="erpAgencyUpdatesConfig"
    data-api-url="<?php echo Rateb\App\Core\View::escape($pushUrl); ?>"
    data-link-url="<?php echo Rateb\App\Core\View::escape($linkUrl); ?>"
    data-sync-url="<?php echo Rateb\App\Core\View::escape($syncUrl); ?>"
    data-restore-admin-url="<?php echo Rateb\App\Core\View::escape($restoreAdminUrl ?? ''); ?>"
    data-reset-data-url="<?php echo Rateb\App\Core\View::escape($resetDataUrl ?? ''); ?>"
    data-csrf="<?php echo Rateb\App\Core\View::escape($csrf); ?>"
    data-confirm-selected="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_confirm_selected')); ?>"
    data-confirm-all="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_confirm_all')); ?>"
    data-confirm-subscribed="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_confirm_subscribed')); ?>"
    data-confirm-sync-selected="<?php echo Rateb\App\Core\View::escape(__('agency_erp_sync_confirm_selected')); ?>"
    data-confirm-sync-all="<?php echo Rateb\App\Core\View::escape(__('agency_erp_sync_confirm_all')); ?>"
    data-confirm-full-selected="<?php echo Rateb\App\Core\View::escape(__('agency_erp_full_deploy_confirm_selected')); ?>"
    data-confirm-full-all="<?php echo Rateb\App\Core\View::escape(__('agency_erp_full_deploy_confirm_all')); ?>"
    data-confirm-link="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_confirm_link')); ?>"
    data-running="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_running')); ?>"
    data-sync-running="<?php echo Rateb\App\Core\View::escape(__('agency_erp_sync_running')); ?>"
    data-full-running="<?php echo Rateb\App\Core\View::escape(__('agency_erp_full_deploy_running')); ?>"
    data-done-ok="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_done_ok')); ?>"
    data-done-errors="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_done_errors')); ?>"
    data-request-failed="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_request_failed')); ?>"
    data-sync-confirm-required="<?php echo Rateb\App\Core\View::escape(__('agency_erp_sync_confirm_required')); ?>"
    data-confirm-restore-selected="<?php echo Rateb\App\Core\View::escape(__('agency_erp_restore_admin_confirm')); ?>"
    data-restore-running="<?php echo Rateb\App\Core\View::escape(__('agency_erp_restore_admin_running')); ?>"
    data-reset-running="<?php echo Rateb\App\Core\View::escape(__('agency_erp_reset_data_running')); ?>"
    data-confirm-reset-selected="<?php echo Rateb\App\Core\View::escape(__('agency_erp_reset_data_confirm_selected')); ?>"
    data-confirm-reset-all="<?php echo Rateb\App\Core\View::escape(__('agency_erp_reset_data_confirm_all')); ?>"
    data-reset-confirm-required="<?php echo Rateb\App\Core\View::escape(__('agency_erp_reset_confirm_required')); ?>"
    data-reset-logout-hint="<?php echo Rateb\App\Core\View::escape(__('agency_erp_reset_logout_hint')); ?>"
    data-reset-verify-site="<?php echo Rateb\App\Core\View::escape(__('agency_erp_reset_verify_site')); ?>"
    data-reset-shell-note="<?php echo Rateb\App\Core\View::escape(__('agency_erp_reset_shell_note')); ?>"
    data-confirm-reset-row="<?php echo Rateb\App\Core\View::escape(__('agency_erp_reset_row_confirm')); ?>"
    data-select-first="<?php echo Rateb\App\Core\View::escape(__('agency_erp_select_agencies_first')); ?>">
</div>
