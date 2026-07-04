<?php
/** @var array<int, array<string, mixed>> $companies */
/** @var string $routePrefix */
?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <a href="<?php echo rateb_url($routePrefix); ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-right"></i> <?php echo __('companies'); ?></a>
    </div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('id'); ?></th>
                    <th><?php echo __('name'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th><?php echo __('branches'); ?></th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($companies === []) { ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else { foreach ($companies as $company) {
                    $cid = (int) ($company['id'] ?? 0);
                    if ($cid < 1 || (string) ($company['status'] ?? '') !== 'active') {
                        continue;
                    }
                    ?>
                <tr>
                    <td><?php echo $cid; ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($company['name'] ?? '')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($company['status'] ?? '')); ?></td>
                    <td><?php echo (int) ($company['branch_count'] ?? 0); ?> / <?php echo (int) ($company['branch_limit_effective'] ?? 0); ?></td>
                    <td class="text-nowrap">
                        <a href="<?php echo rateb_url($routePrefix . '/' . $cid . '/branches'); ?>" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-code-branch"></i> <?php echo __('manage_branches_cp'); ?> #<?php echo $cid; ?>
                        </a>
                    </td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
