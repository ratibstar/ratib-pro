<?php
/** @var int $companyId */
/** @var list<array<string, mixed>> $items */
/** @var string $statusFilter */
/** @var string $csrf */
/** @var string $routePrefix */
/** @var bool $canManage */
$companyId = (int) ($companyId ?? 0);
$items = $items ?? [];
$statusFilter = (string) ($statusFilter ?? 'all');
$csrf = (string) ($csrf ?? \Rateb\App\Core\Csrf::token());
$routePrefix = (string) ($routePrefix ?? rateb_app_route('hr/letters'));
$canManage = (bool) ($canManage ?? false);
$escape = static fn ($v): string => \Rateb\App\Core\View::escape((string) $v);
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'letters']);
?>
<?php if ($companyId < 1) { ?>
<div class="alert alert-warning mb-3"><?php echo __('hr_select_company_hint'); ?></div>
<?php } ?>

<div class="rateb-card mb-3">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="fas fa-file-pdf me-1"></i> <?php echo __('hr_letters'); ?></span>
        <div class="d-flex gap-2">
            <a href="<?php echo rateb_url(rateb_app_route('hr/requests/create')); ?>" class="btn btn-sm btn-primary"><?php echo __('hr_letter_new_request'); ?></a>
            <a href="<?php echo rateb_url(rateb_app_route('hr')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo __('back'); ?></a>
        </div>
    </div>
    <div class="rateb-card-body">
        <p class="text-muted small mb-3"><?php echo __('hr_letters_hint'); ?></p>
        <form method="get" action="<?php echo rateb_url($routePrefix); ?>" class="row g-2 mb-3">
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php
                    foreach ([
                        'all' => __('all'),
                        'pending' => __('pending'),
                        'approved' => __('approved'),
                        'rejected' => __('rejected'),
                    ] as $val => $label) {
                        $sel = $statusFilter === $val ? ' selected' : '';
                        echo '<option value="' . $escape($val) . '"' . $sel . '>' . $escape($label) . '</option>';
                    }
                    ?>
                </select>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table rateb-table mb-0 align-middle">
                <thead>
                <tr>
                    <th><?php echo __('request_no'); ?></th>
                    <th><?php echo __('hr_employees'); ?></th>
                    <th><?php echo __('request_type'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th><?php echo __('date'); ?></th>
                    <th><?php echo __('actions'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($items === []) { ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else {
                    foreach ($items as $item) {
                        $id = (int) ($item['id'] ?? 0);
                        $type = (string) ($item['request_type'] ?? '');
                        $typeLabel = __($type);
                        if ($typeLabel === $type) {
                            $typeLabel = $type;
                        }
                        $status = (string) ($item['status'] ?? '');
                        $docId = (int) ($item['document_id'] ?? 0);
                        ?>
                <tr>
                    <td class="rateb-ltr-num"><?php echo $escape((string) ($item['request_no'] ?? '')); ?></td>
                    <td><?php echo $escape(trim((string) ($item['employee_name'] ?? '') . ' · ' . (string) ($item['employee_code'] ?? ''))); ?></td>
                    <td><?php echo $escape($typeLabel); ?></td>
                    <td><span class="badge bg-light text-dark border"><?php echo $escape(__($status)); ?></span></td>
                    <td class="rateb-ltr-num small"><?php echo $escape((string) ($item['request_date'] ?? '')); ?></td>
                    <td class="d-flex flex-wrap gap-1">
                        <?php if ($status === 'approved' && $canManage) { ?>
                        <form method="post" action="<?php echo rateb_url($routePrefix . '/' . $id . '/issue'); ?>">
                            <input type="hidden" name="_csrf" value="<?php echo $escape($csrf); ?>">
                            <button type="submit" class="btn btn-sm btn-success"><?php echo $docId > 0 ? __('hr_letter_reissue') : __('hr_letter_issue'); ?></button>
                        </form>
                        <?php } ?>
                        <?php if ($docId > 0) { ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo rateb_url($routePrefix . '/' . $id . '/download'); ?>"><?php echo __('hr_letter_download'); ?></a>
                        <?php } ?>
                        <?php if ($status === 'pending') { ?>
                        <a class="btn btn-sm btn-outline-warning" href="<?php echo rateb_url(rateb_app_route('hr/approvals-inbox')); ?>?type=request"><?php echo __('hr_pending_actions'); ?></a>
                        <?php } ?>
                    </td>
                </tr>
                <?php }
                } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php Rateb\App\Core\View::partial('hr-nav-end'); ?>
