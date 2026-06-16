<?php
$rows = $rows ?? [];
Rateb\App\Core\View::partial('hr-nav', ['hrActive' => 'employees']);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('hr_employees'); ?></div>
    <div class="rateb-card-body p-0">
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('name'); ?></th>
                    <th><?php echo __('email'); ?></th>
                    <th><?php echo __('status'); ?></th>
                    <th><?php echo __('language'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []) { ?>
                <tr><td colspan="4" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else {
                    foreach ($rows as $row) { ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape((string) ($row['name'] ?? '')); ?></td>
                    <td class="rateb-ltr-num"><?php echo Rateb\App\Core\View::escape((string) ($row['email'] ?? '')); ?></td>
                    <td><?php echo __((string) ($row['status'] ?? 'active')); ?></td>
                    <td><?php echo Rateb\App\Core\View::escape(strtoupper((string) ($row['locale'] ?? 'ar'))); ?></td>
                </tr>
                <?php }
                } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
