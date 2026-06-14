<?php
$trial = $trial ?? [];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
Rateb\App\Core\View::partial('accounting-reports-back');
if (rateb_can_export_entity('accounting')) {
    Rateb\App\Core\View::partial('export-toolbar', ['exportRoute' => rateb_app_url('accounting/export/trial-balance')]);
}
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo __('trial_balance'); ?></div>
    <div class="rateb-card-body p-0" data-rateb-server-search="0">
        <?php Rateb\App\Core\View::partial('table-search', ['mode' => 'client', 'placeholder' => __('search_table')]); ?>
        <div class="table-responsive">
            <table class="table rateb-table mb-0">
                <thead>
                <tr>
                    <th><?php echo __('code'); ?></th>
                    <th><?php echo __('name'); ?></th>
                    <th><?php echo __('account_type'); ?></th>
                    <th class="text-end"><?php echo __('debit'); ?></th>
                    <th class="text-end"><?php echo __('credit'); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($trial)) { ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
                <?php } else {
                    $totalDr = 0.0;
                    $totalCr = 0.0;
                    foreach ($trial as $row) {
                        $name = rateb_locale() === 'ar' && !empty($row['name_ar']) ? $row['name_ar'] : $row['name'];
                        $totalDr += (float) ($row['total_debit'] ?? 0);
                        $totalCr += (float) ($row['total_credit'] ?? 0);
                        ?>
                <tr>
                    <td><?php echo Rateb\App\Core\View::escape($row['code']); ?></td>
                    <td class="rateb-ar-text"><?php echo Rateb\App\Core\View::escape($name); ?></td>
                    <td><?php echo __((string) ($row['account_type'] ?? '')); ?></td>
                    <td class="text-end"><?php echo number_format((float) $row['total_debit'], 2); ?></td>
                    <td class="text-end"><?php echo number_format((float) $row['total_credit'], 2); ?></td>
                </tr>
                <?php } ?>
                <tr class="table-secondary fw-semibold">
                    <td colspan="3" class="text-end"><?php echo __('total'); ?></td>
                    <td class="text-end"><?php echo number_format($totalDr, 2); ?></td>
                    <td class="text-end"><?php echo number_format($totalCr, 2); ?></td>
                </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
