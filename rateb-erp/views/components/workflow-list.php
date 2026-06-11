<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<int, array{name:string,label:string}> $columns */
?>
<div class="table-responsive">
    <table class="table table-hover rateb-table mb-0">
        <thead>
            <tr>
                <?php foreach ($columns as $col) { ?>
                <th><?php echo Rateb\App\Core\View::escape(__( $col['label'])); ?></th>
                <?php } ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)) { ?>
            <tr><td colspan="<?php echo count($columns); ?>" class="text-muted text-center py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else {
                foreach ($items as $row) { ?>
            <tr>
                <?php foreach ($columns as $col) {
                    $val = $row[$col['name']] ?? '';
                    ?>
                <td><?php echo Rateb\App\Core\View::escape((string) $val); ?></td>
                <?php } ?>
            </tr>
            <?php }
            } ?>
        </tbody>
    </table>
</div>
