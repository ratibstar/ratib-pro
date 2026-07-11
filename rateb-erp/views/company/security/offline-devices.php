<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $devices */
/** @var string $csrf */
/** @var bool $canManage */
/** @var array{branch_id: int, user_id: int, status: string} $filters */

$devices = $devices ?? [];
$csrf = $csrf ?? '';
$canManage = !empty($canManage);
$filters = $filters ?? ['branch_id' => 0, 'user_id' => 0, 'status' => ''];
$esc = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmtTs = static function ($v): string {
    $n = (int) $v;
    if ($n < 1) {
        return '—';
    }

    return date('Y-m-d H:i', $n);
};
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?php echo $esc(__('offline_devices')); ?></h1>
            <p class="text-muted mb-0 small"><?php echo $esc(__('offline_devices_help')); ?></p>
        </div>
    </div>

    <form method="get" class="row g-2 align-items-end mb-3">
        <div class="col-auto">
            <label class="form-label small mb-0"><?php echo $esc(__('branch')); ?></label>
            <input type="number" name="branch_id" class="form-control form-control-sm" value="<?php echo (int) ($filters['branch_id'] ?? 0); ?>" min="0">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0"><?php echo $esc(__('user')); ?></label>
            <input type="number" name="user_id" class="form-control form-control-sm" value="<?php echo (int) ($filters['user_id'] ?? 0); ?>" min="0">
        </div>
        <div class="col-auto">
            <label class="form-label small mb-0"><?php echo $esc(__('status')); ?></label>
            <select name="status" class="form-select form-select-sm">
                <option value=""><?php echo $esc(__('all')); ?></option>
                <?php foreach (['trusted', 'revoked', 'lost', 'disabled'] as $st): ?>
                    <option value="<?php echo $esc($st); ?>" <?php echo (($filters['status'] ?? '') === $st) ? 'selected' : ''; ?>>
                        <?php echo $esc($st); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo $esc(__('filter')); ?></button>
        </div>
    </form>

    <div class="table-responsive border rounded">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th><?php echo $esc(__('offline_device')); ?></th>
                    <th><?php echo $esc(__('user')); ?></th>
                    <th><?php echo $esc(__('branch')); ?></th>
                    <th><?php echo $esc(__('company')); ?></th>
                    <th><?php echo $esc(__('status')); ?></th>
                    <th><?php echo $esc(__('offline_last_unlock')); ?></th>
                    <th><?php echo $esc(__('offline_last_replay')); ?></th>
                    <th><?php echo $esc(__('offline_last_online')); ?></th>
                    <th><?php echo $esc(__('offline_identity_expiry')); ?></th>
                    <?php if ($canManage): ?>
                        <th><?php echo $esc(__('actions')); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($devices === []): ?>
                    <tr>
                        <td colspan="<?php echo $canManage ? 10 : 9; ?>" class="text-muted text-center py-4">
                            <?php echo $esc(__('no_records')); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($devices as $d): ?>
                        <?php
                        $did = (string) ($d['device_id'] ?? '');
                        $label = trim((string) ($d['nickname'] ?? ''));
                        if ($label === '') {
                            $label = trim((string) ($d['label'] ?? ''));
                        }
                        $trust = (string) ($d['trust_status'] ?? $d['status'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo $esc($label !== '' ? $label : $did); ?></div>
                                <div class="small text-muted font-monospace"><?php echo $esc($did); ?></div>
                            </td>
                            <td><?php echo (int) ($d['user_id'] ?? 0); ?></td>
                            <td><?php echo (int) ($d['branch_id'] ?? 0); ?></td>
                            <td><?php echo (int) ($d['company_id'] ?? 0); ?></td>
                            <td><span class="badge text-bg-secondary"><?php echo $esc($trust); ?></span></td>
                            <td class="small"><?php echo $esc((string) ($d['last_unlock_at'] ?? '—') ?: '—'); ?></td>
                            <td class="small"><?php echo $esc((string) ($d['last_replay_at'] ?? '—') ?: '—'); ?></td>
                            <td class="small"><?php echo $esc((string) ($d['last_online_at'] ?? '—') ?: '—'); ?></td>
                            <td class="small"><?php echo $esc($fmtTs($d['identity_expires_at'] ?? 0)); ?></td>
                            <?php if ($canManage): ?>
                                <td class="text-nowrap">
                                    <form method="post" action="<?php echo $esc(rateb_app_url('security/offline-devices/rename')); ?>" class="d-inline-flex gap-1 align-items-center mb-1">
                                        <input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>">
                                        <input type="hidden" name="device_id" value="<?php echo $esc($did); ?>">
                                        <input type="text" name="nickname" class="form-control form-control-sm" style="width:7rem" placeholder="<?php echo $esc(__('offline_nickname')); ?>" value="<?php echo $esc((string) ($d['nickname'] ?? '')); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary"><?php echo $esc(__('rename')); ?></button>
                                    </form>
                                    <form method="post" action="<?php echo $esc(rateb_app_url('security/offline-devices/revoke')); ?>" class="d-inline" onsubmit="return confirm(<?php echo json_encode(__('offline_confirm_revoke'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);">
                                        <input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>">
                                        <input type="hidden" name="device_id" value="<?php echo $esc($did); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo $esc(__('revoke')); ?></button>
                                    </form>
                                    <form method="post" action="<?php echo $esc(rateb_app_url('security/offline-devices/force-logout')); ?>" class="d-inline">
                                        <input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>">
                                        <input type="hidden" name="device_id" value="<?php echo $esc($did); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning"><?php echo $esc(__('offline_force_logout')); ?></button>
                                    </form>
                                    <?php if ($trust === 'revoked' || $trust === 'lost' || $trust === 'disabled'): ?>
                                        <form method="post" action="<?php echo $esc(rateb_app_url('security/offline-devices/restore')); ?>" class="d-inline">
                                            <input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>">
                                            <input type="hidden" name="device_id" value="<?php echo $esc($did); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success"><?php echo $esc(__('restore')); ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
