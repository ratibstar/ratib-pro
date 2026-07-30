<?php
declare(strict_types=1);

/** @var list<array<string,mixed>> $rows */
/** @var array{title:string,icon:string,tone:string,desc:string} $sectionMeta */
$rows = $rows ?? [];
$sectionMeta = $sectionMeta ?? ['title' => 'agent_apps_payments', 'icon' => 'fa-credit-card', 'tone' => 'slate', 'desc' => ''];
$tone = (string) ($sectionMeta['tone'] ?? 'slate');
?>
<div class="raa" data-raa="payments">
    <header class="raa-hero raa-hero--compact">
        <div class="raa-hero__copy">
            <p class="raa-hero__eyebrow"><?php echo Rateb\App\Core\View::escape(__('agent_apps_section')); ?></p>
            <h1 class="raa-hero__title">
                <i class="fas <?php echo Rateb\App\Core\View::escape((string) ($sectionMeta['icon'] ?? 'fa-credit-card')); ?>"></i>
                <?php echo Rateb\App\Core\View::escape(__((string) $sectionMeta['title'])); ?>
            </h1>
            <p class="raa-hero__lead"><?php echo Rateb\App\Core\View::escape(__('agent_apps_payments_matrix_intro')); ?></p>
        </div>
        <a class="raa-hero__cta raa-hero__cta--ghost" href="<?php echo rateb_url('admin/agent-apps'); ?>" data-rateb-href="<?php echo rateb_url('admin/agent-apps'); ?>" data-rateb-soft-nav="1">
            <i class="fas fa-arrow-right"></i>
            <?php echo Rateb\App\Core\View::escape(__('agent_apps_back_dashboard')); ?>
        </a>
    </header>

    <div class="rateb-card" data-tone="<?php echo Rateb\App\Core\View::escape($tone); ?>">
        <div class="rateb-card-body table-responsive p-0">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th><?php echo Rateb\App\Core\View::escape(__('company')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('mobile_apps_app_name')); ?></th>
                    <th><?php echo Rateb\App\Core\View::escape(__('status')); ?></th>
                    <th>payroll</th>
                    <th>payslips</th>
                    <th>payments</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []) { ?>
                <tr>
                    <td colspan="7" class="text-muted text-center py-4">
                        <?php echo Rateb\App\Core\View::escape(__('agent_apps_list_empty')); ?>
                    </td>
                </tr>
                <?php } ?>
                <?php foreach ($rows as $row) {
                    $cid = (int) ($row['company_id'] ?? 0);
                    $active = !empty($row['mobile_active']);
                    ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['company_name'] ?? '—')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['app_name'] ?? '—')); ?></td>
                    <td>
                        <span class="badge <?php echo $active ? 'text-bg-success' : 'text-bg-secondary'; ?>">
                            <?php echo Rateb\App\Core\View::escape($active ? __('active') : __('inactive')); ?>
                        </span>
                    </td>
                    <td><?php echo !empty($row['payroll']) ? '✓' : '—'; ?></td>
                    <td><?php echo !empty($row['payslips']) ? '✓' : '—'; ?></td>
                    <td><?php echo !empty($row['payments']) ? '✓' : '—'; ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?php echo rateb_url('admin/mobile-apps/' . $cid); ?>"
                           data-rateb-href="<?php echo rateb_url('admin/mobile-apps/' . $cid); ?>"
                           data-rateb-soft-nav="1">
                            <?php echo Rateb\App\Core\View::escape(__('edit')); ?>
                        </a>
                    </td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
