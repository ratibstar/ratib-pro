<?php
declare(strict_types=1);

use Rateb\App\Core\Csrf;
use Rateb\App\Core\View;
use Rateb\App\Services\ModuleAddonDemoPreviewService;

if (!ModuleAddonDemoPreviewService::sessionCanManageDemoLocks()) {
    return;
}

$preview = new ModuleAddonDemoPreviewService();
$rows = is_array($rows ?? null) ? $rows : $preview->lockBoard();
$context = is_array($context ?? null) ? $context : $preview->lockBoardContext();
$csrf = (string) ($csrf ?? Csrf::token());
$action = (string) ($action ?? rateb_url('admin/billing/addon-locks'));
$returnTo = (string) ($returnTo ?? 'locks');
$companyId = (int) ($context['company_id'] ?? 0);
$companyName = (string) ($context['company_name'] ?? '');
$needsCompany = !empty($context['needs_company']);
$companiesList = is_array($companies ?? null) ? $companies : [];
$pickedCompanyId = (int) ($pickedCompanyId ?? $companyId);
$isPlatformSA = $companiesList !== [] || $needsCompany;
$esc = static fn ($v): string => View::escape((string) $v);
?>
<div class="rateb-card mb-4" data-addon-lock-board>
    <div class="rateb-card-header"><?php echo $esc(__('module_addon_demo_locks')); ?></div>
    <div class="rateb-card-body">
        <p class="text-muted mb-3"><?php echo $esc(__('module_addon_demo_locks_help')); ?></p>

        <?php if ($isPlatformSA) { ?>
        <!-- Company picker for Super Admin -->
        <form method="get" action="<?php echo $esc($action); ?>" class="mb-3 d-flex flex-wrap gap-2 align-items-center">
            <label class="fw-bold mb-0" for="addon-lock-company-pick"><?php echo $esc(__('module_addon_demo_locks_company_label') ?: 'الشركة'); ?>:</label>
            <select id="addon-lock-company-pick" name="company_id" class="form-select" style="max-width:320px">
                <option value=""><?php echo $esc(__('module_addon_demo_locks_pick_company_option') ?: '— اختر شركة —'); ?></option>
                <?php foreach ($companiesList as $co) { ?>
                <option value="<?php echo $esc((string) $co['id']); ?>"<?php echo ($co['id'] === $pickedCompanyId) ? ' selected' : ''; ?>>
                    <?php echo $esc($co['name'] . ' (#' . $co['id'] . ')'); ?>
                </option>
                <?php } ?>
            </select>
            <button type="submit" class="btn btn-outline-primary"><?php echo $esc(__('show') ?: 'عرض'); ?></button>
        </form>
        <?php } ?>

        <?php if ($companyId > 0 && $companyName !== '') { ?>
        <p class="mb-3"><strong><?php echo $esc(__('module_addon_demo_locks_company', ['name' => $companyName, 'id' => (string) $companyId])); ?></strong></p>
        <?php } elseif ($needsCompany) { ?>
        <div class="alert alert-warning mb-3" role="alert">
            <?php echo $esc(__('module_addon_demo_locks_pick_company')); ?>
        </div>
        <?php } ?>
        <?php if (!$needsCompany && $companyId > 0) { ?>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <form method="post" action="<?php echo $esc($action); ?>" data-rateb-offline-writable="1">
                <input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>">
                <input type="hidden" name="return_to" value="<?php echo $esc($returnTo); ?>">
                <input type="hidden" name="picked_company_id" value="<?php echo $esc((string) $companyId); ?>">
                <input type="hidden" name="lock_action" value="unlock_all">
                <button type="submit" class="btn btn-primary"><?php echo $esc(__('module_addon_demo_unlock_all')); ?></button>
            </form>
            <form method="post" action="<?php echo $esc($action); ?>" data-rateb-offline-writable="1">
                <input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>">
                <input type="hidden" name="return_to" value="<?php echo $esc($returnTo); ?>">
                <input type="hidden" name="picked_company_id" value="<?php echo $esc((string) $companyId); ?>">
                <input type="hidden" name="lock_action" value="lock_all">
                <button type="submit" class="btn btn-outline-secondary"><?php echo $esc(__('module_addon_demo_lock_all')); ?></button>
            </form>
        </div>
        <?php } ?>
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
                            <form method="post" action="<?php echo $esc($action); ?>" data-rateb-offline-writable="1">
                                <input type="hidden" name="_csrf" value="<?php echo $esc($csrf); ?>">
                                <input type="hidden" name="return_to" value="<?php echo $esc($returnTo); ?>">
                                <input type="hidden" name="picked_company_id" value="<?php echo $esc((string) $companyId); ?>">
                                <input type="hidden" name="slug" value="<?php echo $esc($slug); ?>">
                                <input type="hidden" name="lock_action" value="<?php echo $locked ? 'unlock' : 'lock'; ?>">
                                <button type="submit" class="btn btn-sm <?php echo $locked ? 'btn-primary' : 'btn-outline-secondary'; ?>"<?php echo ($needsCompany || $companyId < 1) ? ' disabled' : ''; ?>>
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
