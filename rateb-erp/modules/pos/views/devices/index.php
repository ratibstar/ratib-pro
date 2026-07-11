<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $items */
/** @var list<array{value:int,label:string}> $branchOptions */
/** @var int $filterBranchId */
/** @var string $filterStatus */
/** @var bool $canManage */
$items = is_array($items ?? null) ? $items : [];
$branchOptions = is_array($branchOptions ?? null) ? $branchOptions : [];
$filterBranchId = (int) ($filterBranchId ?? 0);
$filterStatus = (string) ($filterStatus ?? '');
$canManage = (bool) ($canManage ?? false);
$flashSuccess = (string) ($flashSuccess ?? '');
$flashError = (string) ($flashError ?? '');
$indexUrl = (string) ($indexUrl ?? '');
$activateUrl = (string) ($activateUrl ?? '');
$revokeUrl = (string) ($revokeUrl ?? '');
$csrf = (string) ($csrf ?? '');

$statusLabels = [
    'pending' => __('pos_device_status_pending'),
    'active' => __('pos_device_status_active'),
    'inactive' => __('pos_device_status_inactive'),
    'revoked' => __('pos_device_status_revoked'),
];
?>
<div class="rateb-pos-page rateb-pos-devices">
    <?php if ($flashSuccess !== ''): ?>
        <div class="alert alert-success" role="status"><?php echo \Rateb\App\Pos\Support\PosView::escape($flashSuccess); ?></div>
    <?php endif; ?>
    <?php if ($flashError !== ''): ?>
        <div class="alert alert-danger" role="alert"><?php echo \Rateb\App\Pos\Support\PosView::escape($flashError); ?></div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? __('pos_devices')); ?></h1>
            <p class="text-muted mb-0 small"><?php echo __('pos_devices_hint'); ?></p>
        </div>
    </div>

    <form method="get" action="<?php echo \Rateb\App\Pos\Support\PosView::escape($indexUrl); ?>" class="rateb-card mb-3">
        <div class="rateb-card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="pos-device-branch"><?php echo __('pos_context_branch'); ?></label>
                    <select class="form-select form-select-sm" id="pos-device-branch" name="branch_id">
                        <option value=""><?php echo __('all'); ?></option>
                        <?php foreach ($branchOptions as $opt): ?>
                            <option value="<?php echo (int) ($opt['value'] ?? 0); ?>"<?php echo $filterBranchId === (int) ($opt['value'] ?? 0) ? ' selected' : ''; ?>>
                                <?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($opt['label'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="pos-device-status"><?php echo __('status'); ?></label>
                    <select class="form-select form-select-sm" id="pos-device-status" name="status">
                        <option value=""><?php echo __('all'); ?></option>
                        <?php foreach ($statusLabels as $value => $label): ?>
                            <option value="<?php echo \Rateb\App\Pos\Support\PosView::escape($value); ?>"<?php echo $filterStatus === $value ? ' selected' : ''; ?>>
                                <?php echo \Rateb\App\Pos\Support\PosView::escape($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-sm btn-primary"><?php echo __('filter'); ?></button>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo \Rateb\App\Pos\Support\PosView::escape($indexUrl); ?>"><?php echo __('reset'); ?></a>
                </div>
            </div>
        </div>
    </form>

    <div class="rateb-card">
        <div class="rateb-card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?php echo __('pos_device_id'); ?></th>
                            <th><?php echo __('pos_context_branch'); ?></th>
                            <th><?php echo __('pos_device_label'); ?></th>
                            <th><?php echo __('status'); ?></th>
                            <th><?php echo __('pos_device_last_user'); ?></th>
                            <th><?php echo __('pos_device_last_seen'); ?></th>
                            <th><?php echo __('pos_device_activated_at'); ?></th>
                            <?php if ($canManage): ?>
                                <th><?php echo __('pos_actions'); ?></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($items === []): ?>
                            <tr>
                                <td colspan="<?php echo $canManage ? 8 : 7; ?>" class="text-center text-muted py-4">
                                    <?php echo __('no_records'); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($items as $item): ?>
                            <?php
                            $status = (string) ($item['status'] ?? '');
                            $badge = match ($status) {
                                'active' => 'success',
                                'pending' => 'warning',
                                'inactive' => 'secondary',
                                'revoked' => 'danger',
                                default => 'secondary',
                            };
                            $statusLabel = $statusLabels[$status] ?? $status;
                            ?>
                            <tr>
                                <td class="font-monospace small text-truncate" style="max-width: 160px;" title="<?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($item['device_id'] ?? '')); ?>">
                                    <?php echo \Rateb\App\Pos\Support\PosView::escape((string) ($item['device_id'] ?? '')); ?>
                                </td>
                                <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) (($item['branch_name'] ?? '') !== '' ? $item['branch_name'] : '—')); ?></td>
                                <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) (($item['label'] ?? '') !== '' ? $item['label'] : '—')); ?></td>
                                <td><span class="badge bg-<?php echo $badge; ?>"><?php echo \Rateb\App\Pos\Support\PosView::escape($statusLabel); ?></span></td>
                                <td><?php echo \Rateb\App\Pos\Support\PosView::escape((string) (($item['user_label'] ?? '') !== '' ? $item['user_label'] : '—')); ?></td>
                                <td class="small"><?php echo \Rateb\App\Pos\Support\PosView::escape((string) (($item['last_seen_at'] ?? '') !== '' ? $item['last_seen_at'] : '—')); ?></td>
                                <td class="small">
                                    <?php
                                    $actAt = (string) ($item['activated_at'] ?? '');
                                    $actBy = (string) ($item['activated_by_name'] ?? '');
                                    echo \Rateb\App\Pos\Support\PosView::escape($actAt !== '' ? ($actBy !== '' ? $actAt . ' · ' . $actBy : $actAt) : '—');
                                    ?>
                                </td>
                                <?php if ($canManage): ?>
                                    <td class="text-nowrap">
                                        <?php if ($status !== 'active'): ?>
                                            <form method="post" action="<?php echo \Rateb\App\Pos\Support\PosView::escape($activateUrl); ?>" class="d-inline">
                                                <input type="hidden" name="_csrf" value="<?php echo \Rateb\App\Pos\Support\PosView::escape($csrf); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int) ($item['id'] ?? 0); ?>">
                                                <?php if ($filterBranchId > 0): ?>
                                                    <input type="hidden" name="branch_id" value="<?php echo $filterBranchId; ?>">
                                                <?php endif; ?>
                                                <?php if ($filterStatus !== ''): ?>
                                                    <input type="hidden" name="status" value="<?php echo \Rateb\App\Pos\Support\PosView::escape($filterStatus); ?>">
                                                <?php endif; ?>
                                                <button type="submit" class="btn btn-outline-success btn-sm"><?php echo __('pos_device_activate'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($status !== 'revoked'): ?>
                                            <form method="post" action="<?php echo \Rateb\App\Pos\Support\PosView::escape($revokeUrl); ?>" class="d-inline" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(__('pos_device_revoke_confirm'), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>);">
                                                <input type="hidden" name="_csrf" value="<?php echo \Rateb\App\Pos\Support\PosView::escape($csrf); ?>">
                                                <input type="hidden" name="id" value="<?php echo (int) ($item['id'] ?? 0); ?>">
                                                <?php if ($filterBranchId > 0): ?>
                                                    <input type="hidden" name="branch_id" value="<?php echo $filterBranchId; ?>">
                                                <?php endif; ?>
                                                <?php if ($filterStatus !== ''): ?>
                                                    <input type="hidden" name="status" value="<?php echo \Rateb\App\Pos\Support\PosView::escape($filterStatus); ?>">
                                                <?php endif; ?>
                                                <button type="submit" class="btn btn-outline-danger btn-sm"><?php echo __('pos_device_revoke'); ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
