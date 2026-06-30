<?php
/** @var list<array<string, mixed>> $agencies */
/** @var string $platformDb */
/** @var int $suggestedAgencyId */
/** @var int $opsCompanyId */
/** @var string $csrf */
/** @var string $pushUrl */
?>
<div class="rateb-page-header mb-3">
    <h1 class="h4 mb-1"><i class="fas fa-cloud-upload-alt me-2"></i><?php echo __('agency_erp_push_title'); ?></h1>
    <p class="text-muted small mb-0"><?php echo __('agency_erp_push_intro'); ?></p>
</div>

<div class="rateb-card mb-3">
    <div class="rateb-card-body">
        <ul class="small text-muted mb-0">
            <li><?php echo __('agency_erp_push_note_code'); ?></li>
            <li><?php echo __('agency_erp_push_note_db'); ?></li>
        </ul>
    </div>
</div>

<?php if ($agencies === []) { ?>
<div class="alert alert-warning"><?php echo __('agency_erp_push_no_agencies'); ?></div>
<?php } else { ?>
<div class="rateb-card mb-3">
    <div class="rateb-card-body">
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="erpUpdateIncludePlatform" checked>
            <label class="form-check-label" for="erpUpdateIncludePlatform">
                <?php echo __('agency_erp_push_include_platform'); ?>
                <?php if ($platformDb !== '') { ?>
                <code><?php echo Rateb\App\Core\View::escape($platformDb); ?></code>
                <?php } ?>
            </label>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <button type="button" class="btn btn-primary btn-sm" id="erpUpdateRunSelected" disabled>
                <i class="fas fa-play me-1"></i><?php echo __('agency_erp_push_run_selected'); ?>
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="erpUpdateRunAllReady">
                <i class="fas fa-layer-group me-1"></i><?php echo __('agency_erp_push_run_all_ready'); ?>
            </button>
            <button type="button" class="btn btn-outline-success btn-sm" id="erpUpdateRunSubscribed">
                <i class="fas fa-check-circle me-1"></i><?php echo __('agency_erp_push_run_subscribed'); ?>
            </button>
        </div>
        <div id="erpUpdateProgress" class="alert alert-secondary py-2 small d-none" role="status"></div>
        <div id="erpUpdateResults" class="d-none">
            <pre class="bg-dark text-light p-3 rounded small mb-0" id="erpUpdateLog" style="max-height:420px;overflow:auto;"></pre>
        </div>
    </div>
</div>

<div class="rateb-card">
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0" id="erpAgencyUpdatesTable">
                <thead>
                    <tr>
                        <th style="width:2.5rem;">
                            <input type="checkbox" class="form-check-input" id="erpUpdateSelectAll" title="<?php echo __('select_all'); ?>">
                        </th>
                        <th><?php echo __('agency_erp_push_col_agency'); ?></th>
                        <th><?php echo __('agency_erp_push_col_db'); ?></th>
                        <th><?php echo __('agency_erp_push_col_status'); ?></th>
                        <th><?php echo __('agency_erp_push_col_site'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agencies as $agency) {
                        $id = (int) ($agency['id'] ?? 0);
                        $name = (string) ($agency['name'] ?? '');
                        $erpDb = (string) ($agency['erp_db_name'] ?? '');
                        $status = (string) ($agency['erp_status'] ?? 'none');
                        $site = rtrim((string) ($agency['site_url'] ?? ''), '/');
                        $checked = $id > 0 && ($id === $suggestedAgencyId || ($suggestedAgencyId < 1 && count($agencies) === 1));
                        ?>
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input erp-update-agency-cb" value="<?php echo $id; ?>"<?php echo $checked ? ' checked' : ''; ?>>
                        </td>
                        <td>
                            <?php echo Rateb\App\Core\View::escape($name); ?>
                            <?php if ($id === $suggestedAgencyId && $opsCompanyId > 0) { ?>
                            <span class="badge bg-info ms-1"><?php echo __('agency_erp_push_linked_company'); ?></span>
                            <?php } ?>
                        </td>
                        <td><code><?php echo Rateb\App\Core\View::escape($erpDb); ?></code></td>
                        <td><?php echo Rateb\App\Core\View::escape($status); ?></td>
                        <td>
                            <?php if ($site !== '') { ?>
                            <a href="<?php echo Rateb\App\Core\View::escape($site); ?>" target="_blank" rel="noopener"><?php echo Rateb\App\Core\View::escape($site); ?></a>
                            <?php } else { ?>
                            <span class="text-muted">—</span>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<div id="erpAgencyUpdatesConfig"
    data-api-url="<?php echo Rateb\App\Core\View::escape($pushUrl); ?>"
    data-csrf="<?php echo Rateb\App\Core\View::escape($csrf); ?>"
    data-confirm-selected="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_confirm_selected')); ?>"
    data-confirm-all="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_confirm_all')); ?>"
    data-confirm-subscribed="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_confirm_subscribed')); ?>"
    data-running="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_running')); ?>"
    data-done-ok="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_done_ok')); ?>"
    data-done-errors="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_done_errors')); ?>"
    data-request-failed="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_request_failed')); ?>">
</div>
