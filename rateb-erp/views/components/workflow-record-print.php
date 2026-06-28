<?php
/** @var array<string, mixed> $item */
/** @var array<int, array{name:string,label:string,type?:string}> $columns */
$item = $item ?? [];
$columns = $columns ?? [];
?>
<div class="rateb-po-print-header">
    <h1 class="h4 mb-1"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></h1>
</div>
<table class="table table-bordered table-sm mb-0">
    <tbody>
    <?php foreach ($columns as $col) {
        $type = (string) ($col['type'] ?? '');
        if ($type === 'action_link') {
            continue;
        }
        $name = (string) ($col['name'] ?? '');
        if ($name === '') {
            continue;
        }
        $val = $item[$name] ?? '';
        if ($type === 'status' || $name === 'manager_approval') {
            $meta = rateb_table_cell_meta($val, $col);
            $display = (string) ($meta['display'] ?? $val);
        } elseif ($type === 'money') {
            $display = number_format((float) $val, 2);
        } else {
            $meta = rateb_table_cell_meta($val, $col);
            $display = (string) ($meta['display'] ?? $val);
        }
        if ($display === '' || $display === '0000-00-00') {
            $display = '—';
        }
        ?>
    <tr>
        <th style="width:32%"><?php echo Rateb\App\Core\View::escape(rateb_label((string) ($col['label'] ?? $name))); ?></th>
        <td><?php echo $type === 'notes' ? nl2br(Rateb\App\Core\View::escape($display)) : Rateb\App\Core\View::escape($display); ?></td>
    </tr>
    <?php } ?>
    </tbody>
</table>
