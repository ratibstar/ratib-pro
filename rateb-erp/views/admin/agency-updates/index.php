<?php
/** @var list<array<string, mixed>> $agencies */
/** @var string $platformDb */
/** @var int $suggestedAgencyId */
/** @var int $opsCompanyId */
/** @var string $opsCompanyName */
/** @var array<int, string> $companyNames */
/** @var string $csrf */
/** @var string $pushUrl */
/** @var string $linkUrl */
$hasLinkedAgency = $opsCompanyId > 0 && $suggestedAgencyId > 0;
$singleAgencyId = count($agencies) === 1 ? (int) ($agencies[0]['id'] ?? 0) : 0;
?>
<div class="rateb-page-header mb-3">
    <h1 class="h4 mb-1"><i class="fas fa-cloud-upload-alt me-2"></i><?php echo __('agency_erp_push_title'); ?></h1>
    <p class="text-muted small mb-0"><?php echo __('agency_erp_push_intro'); ?></p>
</div>

<?php if ($opsCompanyId > 0 && $opsCompanyName !== '' && !$hasLinkedAgency) { ?>
<div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3" id="erpCompanyLinkWarning">
    <div>
        <i class="fas fa-link-slash me-1"></i>
        <?php echo __('agency_erp_push_unlinked_warning', ['company' => $opsCompanyName]); ?>
    </div>
    <?php if ($agencies !== [] && $singleAgencyId > 0) { ?>
    <button type="button" class="btn btn-sm btn-warning" id="erpLinkCompanyBtn"
        data-agency-id="<?php echo $singleAgencyId; ?>"
        data-company-id="<?php echo $opsCompanyId; ?>">
        <i class="fas fa-link me-1"></i><?php echo __('agency_erp_push_link_now'); ?>
    </button>
    <?php } ?>
</div>
<?php } elseif ($hasLinkedAgency && $opsCompanyName !== '') { ?>
<div class="alert alert-info py-2 mb-3">
    <i class="fas fa-link me-1"></i>
    <?php echo __('agency_erp_push_linked_ok', ['company' => $opsCompanyName]); ?>
</div>
<?php } ?>

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
    <div class="rateb-bulk-bar d-none" data-rateb-bulk-bar id="erpAgencyBulkBar">
        <span class="rateb-bulk-count" data-rateb-bulk-count data-label="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_bulk_label')); ?>">0</span>
        <button type="button" class="btn btn-primary btn-sm erp-push-bulk-btn" id="erpUpdateRunSelected">
            <i class="fas fa-play me-1"></i><?php echo __('agency_erp_push_run_selected'); ?>
        </button>
    </div>
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
                        $checked = false;
                        if ($suggestedAgencyId > 0) {
                            $checked = $id === $suggestedAgencyId;
                        } elseif ($opsCompanyId < 1 && count($agencies) === 1) {
                            $checked = true;
                        }
                        ?>
                    <tr class="erp-agency-row" data-agency-id="<?php echo $id; ?>" data-erp-company-id="<?php echo $linkedCoId; ?>">
                        <td class="rateb-bulk-td">
                            <input type="checkbox" class="form-check-input rateb-row-check erp-update-agency-cb" value="<?php echo $id; ?>" data-rateb-row-check<?php echo $checked ? ' checked' : ''; ?>>
                        </td>
                        <td>
                            <?php echo Rateb\App\Core\View::escape($name); ?>
                            <?php if ($id === $suggestedAgencyId && $opsCompanyId > 0) { ?>
                            <span class="badge bg-info ms-1"><?php echo __('agency_erp_push_linked_company'); ?></span>
                            <?php } ?>
                        </td>
                        <td>
                            <?php if ($linkedCoName !== '') { ?>
                            <?php echo Rateb\App\Core\View::escape($linkedCoName); ?>
                            <?php } else { ?>
                            <span class="text-muted">—</span>
                            <?php if ($opsCompanyId > 0 && $singleAgencyId === $id) { ?>
                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 erp-link-row-btn"
                                data-agency-id="<?php echo $id; ?>"
                                data-company-id="<?php echo $opsCompanyId; ?>"><?php echo __('agency_erp_push_link_now'); ?></button>
                            <?php } ?>
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
    data-link-url="<?php echo Rateb\App\Core\View::escape($linkUrl); ?>"
    data-csrf="<?php echo Rateb\App\Core\View::escape($csrf); ?>"
    data-confirm-selected="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_confirm_selected')); ?>"
    data-confirm-all="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_confirm_all')); ?>"
    data-confirm-subscribed="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_confirm_subscribed')); ?>"
    data-confirm-link="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_confirm_link')); ?>"
    data-running="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_running')); ?>"
    data-done-ok="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_done_ok')); ?>"
    data-done-errors="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_done_errors')); ?>"
    data-request-failed="<?php echo Rateb\App\Core\View::escape(__('agency_erp_push_request_failed')); ?>">
</div>
