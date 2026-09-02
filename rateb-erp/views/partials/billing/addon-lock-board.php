<?php
declare(strict_types=1);

use Rateb\App\Core\Csrf;
use Rateb\App\Core\View;
use Rateb\App\Services\ModuleAddonDemoPreviewService;

if (!ModuleAddonDemoPreviewService::sessionCanManageDemoLocks()) {
    return;
}

$rows = is_array($rows ?? null) ? $rows : (new ModuleAddonDemoPreviewService())->lockBoard();
$csrf = (string) ($csrf ?? Csrf::token());
$action = (string) ($action ?? rateb_url('admin/billing/addon-locks'));
$returnTo = (string) ($returnTo ?? 'locks');
$esc = static fn ($v): string => View::escape((string) $v);
?>
<div class="rateb-card mb-4" data-addon-lock-board>
    <div class="rateb-card-header"><?php echo $esc(__('module_addon_demo_locks')); ?></div>
    <div class="rateb-card-body">
        <p class="text-muted mb-3"><?php echo $esc(__('module_addon_demo_locks_help')); ?></p>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <form method="post" action="<?php echo $esc($action); ?>">
                <input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>">
                <input type="hidden" name="return_to" value="<?php echo $esc($returnTo); ?>">
                <input type="hidden" name="lock_action" value="unlock_all">
                <button type="submit" class="btn btn-primary"><?php echo $esc(__('module_addon_demo_unlock_all')); ?></button>
            </form>
            <form method="post" action="<?php echo $esc($action); ?>">
                <input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>">
                <input type="hidden" name="return_to" value="<?php echo $esc($returnTo); ?>">
                <input type="hidden" name="lock_action" value="lock_all">
                <button type="submit" class="btn btn-outline-secondary"><?php echo $esc(__('module_addon_demo_lock_all')); ?></button>
            </form>
        </div>
        <?php if ($rows === []) { ?>
        <p class="text-muted mb-0"><?php echo $esc(__('module_addon_demo_locks_empty')); ?></p>
        <?php } else { ?>
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                    <tr>
                        <th><?php echo $esc(__('module')); ?></th>
                        <th><?php echo $esc(__('status')); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row) {
                        $slug = (string) ($row['slug'] ?? '');
                        if ($slug === '') {
                            continue;
                        }
                        $locked = !empty($row['locked']);
                        ?>
                    <tr>
                        <td><?php echo $esc((string) ($row['name'] ?? $slug)); ?></td>
                        <td>
                            <?php if ($locked) { ?>
                            <span class="text-warning"><i class="fas fa-lock" aria-hidden="true"></i> <?php echo $esc(__('module_addon_demo_locked')); ?></span>
                            <?php } else { ?>
                            <span class="text-success"><i class="fas fa-lock-open" aria-hidden="true"></i> <?php echo $esc(__('module_addon_demo_unlocked')); ?></span>
                            <?php } ?>
                        </td>
                        <td class="text-end">
                            <form method="post" action="<?php echo $esc($action); ?>">
                                <input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>">
                                <input type="hidden" name="return_to" value="<?php echo $esc($returnTo); ?>">
                                <input type="hidden" name="slug" value="<?php echo $esc($slug); ?>">
                                <input type="hidden" name="lock_action" value="<?php echo $locked ? 'unlock' : 'lock'; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $locked ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                                    <?php echo $esc($locked ? __('module_addon_demo_lock_open') : __('module_addon_demo_lock_close')); ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php } ?>
    </div>
</div>
