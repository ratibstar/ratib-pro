<?php
$desc = rateb_locale() === 'ar' && !empty($entry['description_ar']) ? $entry['description_ar'] : $entry['description'];
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($entry['entry_no'] ?? ''); ?></div>
    <div class="rateb-card-body">
        <p class="mb-1"><strong><?php echo __('evaluation_date'); ?>:</strong> <?php echo Rateb\App\Core\View::escape($entry['entry_date'] ?? ''); ?></p>
        <p class="mb-0"><?php echo Rateb\App\Core\View::escape($desc); ?></p>
    </div>
</div>
<div class="rateb-card">
    <div class="rateb-card-body p-0">
        <table class="table rateb-table mb-0">
            <thead>
            <tr>
                <th><?php echo __('code'); ?></th>
                <th><?php echo __('name'); ?></th>
                <th class="text-end"><?php echo __('debit'); ?></th>
                <th class="text-end"><?php echo __('credit'); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $line) {
                $name = rateb_locale() === 'ar' && !empty($line['name_ar']) ? $line['name_ar'] : $line['name'];
                ?>
            <tr>
                <td><?php echo Rateb\App\Core\View::escape($line['code']); ?></td>
                <td><?php echo Rateb\App\Core\View::escape($name); ?></td>
                <td class="text-end"><?php echo number_format((float) $line['debit'], 2); ?></td>
                <td class="text-end"><?php echo number_format((float) $line['credit'], 2); ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<a href="<?php echo rateb_app_url('journal-entries'); ?>" class="btn btn-outline-secondary mt-3"><?php echo __('cancel'); ?></a>
