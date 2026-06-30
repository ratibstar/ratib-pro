<?php
/** @var array<string, mixed>|null $item */
$routePrefix = $routePrefix ?? rateb_app_route('supplier-comms');
$item = is_array($item ?? null) ? $item : [];
$isEdit = !empty($item['id']);
$commId = $isEdit ? (int) $item['id'] : 0;
$commSvc = $commSvc ?? new \Rateb\App\Services\SupplierCommService();
$supplierHistory = $supplierHistory ?? [];
$commTimeline = $commTimeline ?? [];
$archived = $isEdit && (int) ($item['is_archived'] ?? 0) === 1;
$formAction = $isEdit ? rateb_url($routePrefix . '/' . $commId) : rateb_app_url('supplier-comms');
?>
<?php if (!empty($moduleCss)) { ?>
<link href="<?php echo Rateb\App\Core\View::escape($moduleCss); ?>" rel="stylesheet">
<?php } ?>

<div class="rateb-sc-page">
    <div class="rateb-sc-page-header">
        <div>
            <nav class="rateb-sc-breadcrumb" aria-label="breadcrumb">
                <a href="<?php echo rateb_app_url('dashboard'); ?>"><?php echo __('dashboard'); ?></a>
                <span class="mx-1">/</span>
                <a href="<?php echo rateb_app_url('supplier-comms'); ?>"><?php echo __('supplier_comms'); ?></a>
                <span class="mx-1">/</span>
                <span><?php echo $isEdit ? __('edit') : __('create'); ?></span>
            </nav>
            <h2 class="h4 mb-0"><?php echo Rateb\App\Core\View::escape($title ?? ($isEdit ? __('edit') : __('create')) . ' ' . __('supplier_comms')); ?></h2>
        </div>
        <?php if ($isEdit) { ?>
        <div class="d-flex gap-2">
            <a href="<?php echo rateb_url($routePrefix . '/' . $commId . '/print'); ?>" class="btn btn-outline-secondary btn-sm" target="_blank"><i class="fas fa-print"></i> <?php echo __('print'); ?></a>
            <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $commId . '/archive'); ?>" class="d-inline">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-outline-warning btn-sm"><i class="fas fa-box-archive"></i> <?php echo $archived ? __('comm_unarchive') : __('comm_archive'); ?></button>
            </form>
        </div>
        <?php } ?>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="rateb-sc-card">
                <div class="rateb-sc-card-header">
                    <span>
                        <i class="fas <?php echo $isEdit ? 'fa-edit' : 'fa-plus'; ?> text-primary"></i>
                        <?php echo $isEdit ? __('edit') : __('create'); ?> <?php echo __('supplier_comms'); ?>
                    </span>
                    <?php if ($archived) { ?><span class="badge bg-secondary"><?php echo __('archived'); ?></span><?php } ?>
                </div>
                <div class="rateb-sc-card-body">
                    <form method="post" action="<?php echo $formAction; ?>" enctype="multipart/form-data"
                        data-supplier-comm-form="1"
                        data-history-url="<?php echo Rateb\App\Core\View::escape($historyUrl ?? ''); ?>"
                        data-supplier-profile-url="<?php echo Rateb\App\Core\View::escape($supplierProfileUrl ?? ''); ?>"
                        data-comm-id="<?php echo $commId; ?>">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <?php if ($isEdit && (int) ($item['company_id'] ?? 0) > 0) { ?>
                        <input type="hidden" name="company_id" value="<?php echo (int) $item['company_id']; ?>">
                        <?php } ?>
                        <?php Rateb\App\Core\View::partial('supplier-comm-form-fields', [
                            'item' => $item,
                            'fields' => $fields,
                            'lookups' => $lookups,
                            'responsibleDefault' => $responsibleDefault ?? '',
                            'showAttachments' => true,
                            'existingDocuments' => $existingDocuments ?? [],
                            'commSvc' => $commSvc,
                        ]); ?>
                        <div class="rateb-sc-form-actions">
                            <p class="text-muted small w-100 mb-2"><?php echo __('comm_save_send_hint'); ?></p>
                            <button type="submit" name="form_action" value="save" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo __('save'); ?></button>
                            <button type="submit" name="form_action" value="save_send" class="btn btn-outline-primary"><i class="fas fa-paper-plane"></i> <?php echo __('save_and_send'); ?></button>
                            <a href="<?php echo rateb_app_url('supplier-comms'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4 d-flex flex-column gap-3">
            <?php if ($isEdit) { ?>
            <div class="rateb-sc-card">
                <div class="rateb-sc-card-header"><?php echo __('comm_timeline'); ?></div>
                <div class="rateb-sc-card-body p-0">
                    <?php if ($commTimeline === []) { ?>
                    <p class="text-muted small p-3 mb-0"><?php echo __('comm_timeline_hint'); ?></p>
                    <?php } else { ?>
                    <ul class="rateb-sc-timeline list-unstyled mb-0">
                        <?php foreach ($commTimeline as $ev) {
                            $etype = (string) ($ev['event_type'] ?? '');
                            $icon = match ($etype) {
                                'email_send' => 'fa-envelope',
                                'whatsapp' => 'fa-whatsapp',
                                'sms' => 'fa-sms',
                                'attachment' => 'fa-paperclip',
                                'reminder', 'no_response' => 'fa-bell',
                                default => 'fa-circle-dot',
                            }; ?>
                        <li class="rateb-sc-timeline-item">
                            <div class="rateb-sc-timeline-icon"><i class="fas <?php echo $icon; ?>"></i></div>
                            <div class="rateb-sc-timeline-body">
                                <div class="rateb-sc-timeline-summary"><?php echo Rateb\App\Core\View::escape((string) ($ev['summary'] ?? '')); ?></div>
                                <?php if (!empty($ev['details'])) { ?>
                                <div class="rateb-sc-timeline-details text-muted small"><?php echo Rateb\App\Core\View::escape((string) $ev['details']); ?></div>
                                <?php } ?>
                                <div class="rateb-sc-timeline-meta text-muted small">
                                    <?php echo Rateb\App\Core\View::formatDate((string) ($ev['created_at'] ?? '')); ?>
                                    <?php if (!empty($ev['user_name'])) { ?>
                                    · <?php echo Rateb\App\Core\View::escape((string) $ev['user_name']); ?>
                                    <?php } ?>
                                </div>
                            </div>
                        </li>
                        <?php } ?>
                    </ul>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
            <div class="rateb-sc-card flex-grow-1" id="sc_supplier_history"
                data-empty="<?php echo Rateb\App\Core\View::escape(__('no_records')); ?>"
                data-hint="<?php echo Rateb\App\Core\View::escape(__('comm_history_hint')); ?>"
                data-col-date="<?php echo Rateb\App\Core\View::escape(__('comm_date')); ?>"
                data-col-subject="<?php echo Rateb\App\Core\View::escape(__('subject')); ?>"
                data-col-status="<?php echo Rateb\App\Core\View::escape(__('comm_status')); ?>">
                <div class="rateb-sc-card-header"><?php echo __('comm_supplier_history'); ?></div>
                <div class="rateb-sc-card-body p-0" id="sc_supplier_history_body">
                    <?php if ($supplierHistory === []) { ?>
                    <p class="text-muted small p-3 mb-0"><?php echo __('comm_history_hint'); ?></p>
                    <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm rateb-table mb-0">
                            <thead><tr>
                                <th><?php echo __('comm_date'); ?></th>
                                <th><?php echo __('subject'); ?></th>
                                <th><?php echo __('comm_status'); ?></th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($supplierHistory as $hist) {
                                $st = (string) ($hist['comm_status'] ?? 'new'); ?>
                            <tr>
                                <td><?php echo Rateb\App\Core\View::formatDate((string) ($hist['comm_date'] ?? '')); ?></td>
                                <td class="rateb-cell-clip"><?php echo Rateb\App\Core\View::escape((string) ($hist['subject'] ?? '')); ?></td>
                                <td><span class="badge bg-<?php echo $commSvc->statusBadgeClass($st); ?>"><?php echo Rateb\App\Core\View::escape(__('comm_status_' . $st)); ?></span></td>
                            </tr>
                            <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
$mailto = \Rateb\App\Core\SessionManager::get('rateb_comm_mailto');
if (is_string($mailto) && $mailto !== '') {
    \Rateb\App\Core\SessionManager::set('rateb_comm_mailto', null);
}
$externalUrl = \Rateb\App\Core\SessionManager::get('rateb_comm_external_url');
if (is_string($externalUrl) && $externalUrl !== '') {
    \Rateb\App\Core\SessionManager::set('rateb_comm_external_url', null);
}
?>
<?php if (!empty($moduleJs)) { ?>
<script src="<?php echo Rateb\App\Core\View::escape($moduleJs); ?>"></script>
<?php } ?>
<?php if (!empty($mailto)) { ?>
<script>window.addEventListener('load', function () { window.location.href = <?php echo json_encode($mailto, JSON_UNESCAPED_UNICODE); ?>; });</script>
<?php } ?>
<?php if (!empty($externalUrl)) { ?>
<script>window.addEventListener('load', function () { window.open(<?php echo json_encode($externalUrl, JSON_UNESCAPED_UNICODE); ?>, '_blank'); });</script>
<?php } ?>
