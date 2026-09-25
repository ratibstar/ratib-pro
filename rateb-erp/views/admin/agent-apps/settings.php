<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $rows */
/** @var array{title:string,icon:string,tone:string,desc:string} $sectionMeta */
/** @var string $settingsApp hr|erp|customer */
$rows = $rows ?? [];
$sectionMeta = $sectionMeta ?? ['title' => 'agent_apps_settings', 'icon' => 'fa-sliders', 'tone' => 'blue', 'desc' => ''];
$tone = (string) ($sectionMeta['tone'] ?? 'blue');
$canManage = !empty($canManage);
$settingsApp = (string) ($settingsApp ?? 'hr');
$isHr = $settingsApp === 'hr';
$csrf = (string) ($csrf ?? '');
$canToggle = rateb_is_super_admin();
?>
<div class="raa" data-raa="settings">
    <header class="raa-hero raa-hero--compact">
        <div class="raa-hero__copy">
            <p class="raa-hero__eyebrow"><?php echo Rateb\App\Core\View::escape(__('agent_apps_section')); ?></p>
            <h1 class="raa-hero__title">
                <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($sectionMeta['icon'] ?? 'fa-sliders')); ?>"></i>
                <?php echo Rateb\App\Core\View::escape(__((string) $sectionMeta['title'])); ?>
            </h1>
            <p class="raa-hero__lead"><?php echo Rateb\App\Core\View::escape(__((string) ($sectionMeta['desc'] ?? ''))); ?></p>
        </div>
        <a class="raa-hero__cta" href="<?php echo rateb_url('admin/mobile-apps') . ($isHr ? '' : '?app=' . $settingsApp); ?>" data-rateb-href="<?php echo rateb_url('admin/mobile-apps') . ($isHr ? '' : '?app=' . $settingsApp); ?>" data-rateb-soft-nav="1">
            <i class="fas fa-mobile-alt"></i>
            <?php echo Rateb\App\Core\View::escape(__('agent_apps_manage_branding')); ?>
        </a>
    </header>

    <?php
    $activeApp = $settingsApp;
    $tabsRoute = 'admin/agent-apps/settings';
    $tabsWithAll = false;
    require RATEB_ROOT . '/views/admin/mobile-apps/_tabs.php';
    ?>

    <div class="rateb-card" data-tone="<?php echo Rateb\App\Core\View::escape($tone); ?>">
        <div class="rateb-card-body table-responsive p-0">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th><?php echo Rateb\App\Core\View::escape(__('company')); ?></th>
                    <?php if ($isHr) { ?>
                    <th><?php echo Rateb\App\Core\View::escape(__('mobile_apps_app_name')); ?></th>
                    <?php } ?>
                    <th><?php echo Rateb\App\Core\View::escape(__('status')); ?></th>
                    <?php if ($isHr) { ?>
                    <th><?php echo Rateb\App\Core\View::escape(__('agent_apps_features_summary')); ?></th>
                    <?php } ?>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []) { ?>
                <tr>
                    <td colspan="5" class="text-muted text-center py-4">
                        <?php echo Rateb\App\Core\View::escape(__('agent_apps_list_empty')); ?>
                    </td>
                </tr>
                <?php } ?>
                <?php foreach ($rows as $row) {
                    $cid = (int) ($row['company_id'] ?? 0);
                    $features = is_array($row['features'] ?? null) ? $row['features'] : [];
                    $onLabels = [];
                    foreach (['payroll', 'payslips', 'payments', 'requests', 'inquiries', 'ratings', 'notifications'] as $fk) {
                        if (!empty($features[$fk])) {
                            $onLabels[] = __('mobile_apps_feature_' . $fk);
                        }
                    }
                    $active = !empty($row['mobile_active']);
                    $manageUrl = rateb_url('admin/mobile-apps/' . $cid) . ($isHr ? '' : '?app=' . $settingsApp);
                    ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['company_name'] ?? '—')); ?></td>
                    <?php if ($isHr) { ?>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['app_name'] ?? '—')); ?></td>
                    <?php } ?>
                    <td>
                        <span class="badge <?php echo $active ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                            <?php echo Rateb\App\Core\View::escape($active ? __('active') : __('inactive')); ?>
                        </span>
                    </td>
                    <?php if ($isHr) { ?>
                    <td class="small text-muted">
                        <?php echo $onLabels === []
                            ? Rateb\App\Core\View::escape(__('agent_apps_features_none'))
                            : Rateb\App\Core\View::escape(implode(' · ', $onLabels)); ?>
                    </td>
                    <?php } ?>
                    <td class="text-end text-nowrap">
                        <?php if ($canToggle && $cid > 0) { ?>
                        <form method="post" action="<?php echo rateb_url('admin/mobile-apps/' . $cid . '/toggle'); ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                            <input type="hidden" name="app" value="<?php echo Rateb\App\Core\View::escape($settingsApp); ?>">
                            <input type="hidden" name="back" value="settings">
                            <input type="hidden" name="status" value="<?php echo $active ? 'inactive' : 'active'; ?>">
                            <button type="submit" class="btn btn-sm <?php echo $active ? 'btn-outline-danger' : 'btn-success'; ?>">
                                <i class="fas <?php echo $active ? 'fa-power-off' : 'fa-plus'; ?>"></i>
                                <?php echo Rateb\App\Core\View::escape($active ? __('mobile_apps_disable_btn') : __('mobile_apps_enable_btn')); ?>
                            </button>
                        </form>
                        <?php } ?>
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?php echo Rateb\App\Core\View::escape($manageUrl); ?>"
                           data-rateb-href="<?php echo Rateb\App\Core\View::escape($manageUrl); ?>"
                           data-rateb-soft-nav="1">
                            <?php echo Rateb\App\Core\View::escape($canManage ? __('mobile_apps_manage') : __('view')); ?>
                        </a>
                    </td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
