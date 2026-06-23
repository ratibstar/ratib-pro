<?php
/** @var array<int, array{name:string,label:string,type?:string,class?:string}> $columns */
/** @var array<int, array<string, mixed>> $rows */
$columns = $columns ?? [];
$rows = $rows ?? [];
$colCount = count($columns);
?>
<div class="table-responsive rateb-oversight-table-wrap">
    <table class="table rateb-table rateb-oversight-table mb-0">
        <thead>
        <tr>
            <?php foreach ($columns as $col) { ?>
            <th><?php echo Rateb\App\Core\View::escape(rateb_label((string) ($col['label'] ?? $col['name']))); ?></th>
            <?php } ?>
        </tr>
        </thead>
        <tbody>
        <?php if ($rows === []) { ?>
        <tr><td colspan="<?php echo $colCount; ?>" class="text-center text-muted py-4"><?php echo __('no_records'); ?></td></tr>
        <?php } else {
            foreach ($rows as $row) { ?>
        <tr>
            <?php foreach ($columns as $col) {
                $name = (string) ($col['name'] ?? '');
                $type = (string) ($col['type'] ?? '');
                $val = $row[$name] ?? '';
                if ($type === 'status') {
                    $meta = rateb_table_cell_meta($val, $col);
                    $badge = (string) ($meta['badge'] ?? 'secondary');
                    if ($badge === '') {
                        $badge = 'secondary';
                    }
                    ?>
            <td><span class="badge bg-<?php echo Rateb\App\Core\View::escape($badge); ?>"><?php echo Rateb\App\Core\View::escape((string) ($meta['display'] ?? '')); ?></span></td>
                    <?php
                } elseif ($type === 'money') {
                    ?>
            <td class="rateb-ltr-num text-end"><?php echo number_format((float) $val, 2); ?></td>
                    <?php
                } else {
                    $cellClass = trim('rateb-oversight-cell ' . (string) ($col['class'] ?? ''));
                    $display = (string) $val;
                    if ($display === '') {
                        $display = '—';
                    }
                    ?>
            <td class="<?php echo Rateb\App\Core\View::escape($cellClass); ?>"><?php echo Rateb\App\Core\View::escape($display); ?></td>
                    <?php
                }
            } ?>
        </tr>
            <?php }
        } ?>
        </tbody>
    </table>
</div>
