<?php
/** @var array<int, array<string, mixed>> $items */
/** @var array<int, array{name:string,label:string,type?:string}> $columns */
/** @var bool $bulkEnabled */
/** @var bool $actionsEnabled */
/** @var string $routePrefix */
/** @var string $csrf */
$bulkEnabled = !empty($bulkEnabled);
$actionsEnabled = !empty($actionsEnabled);
$routePrefix = (string) ($routePrefix ?? '');
$csrf = (string) ($csrf ?? '');
$colspan = count($columns) + ($bulkEnabled ? 1 : 0) + ($actionsEnabled ? 1 : 0);
$hasActionLink = false;
foreach ($columns as $c) {
    if (($c['type'] ?? '') === 'action_link') {
        $hasActionLink = true;
        break;
    }
}
$formatCell = static function (mixed $val, array $col): string {
    $type = (string) ($col['type'] ?? '');
    if ($type === 'notes') {
        $text = trim((string) $val);
        if ($text === '') {
            return '—';
        }
        return mb_strlen($text) > 48 ? mb_substr($text, 0, 48) . '…' : $text;
    }
    if ($type === 'money') {
        return number_format((float) $val, 2);
    }
    if ($val === null || $val === '') {
        return '—';
    }
    return (string) $val;
};
?>
<?php if ($bulkEnabled && $routePrefix !== '') { ?>
<div class="rateb-bulk-bar d-none" data-rateb-bulk-bar>
    <span class="rateb-bulk-count" data-rateb-bulk-count data-label="<?php echo Rateb\App\Core\View::escape(__('bulk_selected')); ?>">0</span>
    <form method="post" action="<?php echo rateb_url($routePrefix . '/bulk-delete'); ?>" class="d-inline" data-rateb-bulk-form="delete" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('bulk_confirm_delete')); ?>">
        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
        <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> <?php echo __('bulk_delete'); ?></button>
    </form>
</div>
<?php } ?>
<?php Rateb\App\Core\View::partial('table-search', ['mode' => 'client']); ?>
<div class="table-responsive" data-rateb-table-search-host="1">
    <table class="table table-hover rateb-table mb-0" data-rateb-bulk-table="<?php echo $bulkEnabled ? '1' : '0'; ?>">
        <thead>
            <tr>
                <?php if ($bulkEnabled) { ?>
                <th class="rateb-bulk-th">
                    <input type="checkbox" class="form-check-input" data-rateb-select-all title="<?php echo __('select_all'); ?>">
                </th>
                <?php } ?>
                <?php foreach ($columns as $col) { ?>
                <th><?php echo Rateb\App\Core\View::escape(rateb_label((string) ($col['label'] ?? $col['name']))); ?></th>
                <?php } ?>
                <?php if ($actionsEnabled && $routePrefix !== '' && !$hasActionLink) { ?>
                <th><?php echo __('actions'); ?></th>
                <?php } ?>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($items)) { ?>
            <tr><td colspan="<?php echo $colspan; ?>" class="text-muted text-center py-4"><?php echo __('no_records'); ?></td></tr>
            <?php } else {
                foreach ($items as $row) { ?>
            <tr>
                <?php if ($bulkEnabled) { ?>
                <td class="rateb-bulk-th">
                    <input type="checkbox" class="form-check-input" name="ids[]" value="<?php echo (int) ($row['id'] ?? 0); ?>" data-rateb-row-check>
                </td>
                <?php } ?>
                <?php foreach ($columns as $col) {
                    $type = (string) ($col['type'] ?? '');
                    if ($type === 'action_link') {
                        $path = str_replace('{id}', (string) ($row['id'] ?? ''), (string) ($col['url'] ?? ''));
                        $href = str_starts_with($path, 'http') ? $path : rateb_url($path);
                        $text = rateb_label((string) ($col['text'] ?? 'view'));
                        ?>
                <td class="rateb-actions text-nowrap">
                    <a href="<?php echo Rateb\App\Core\View::escape($href); ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-eye"></i> <?php echo Rateb\App\Core\View::escape($text); ?>
                    </a>
                </td>
                        <?php
                        continue;
                    }
                    $val = $row[$col['name']] ?? '';
                    $display = $formatCell($val, $col);
                    $class = in_array($type, ['money', 'id'], true) ? ' rateb-ltr-num' : '';
                    ?>
                <td class="<?php echo trim($class); ?>"><?php echo Rateb\App\Core\View::escape($display); ?></td>
                <?php } ?>
                <?php if ($actionsEnabled && $routePrefix !== '' && !$hasActionLink) { ?>
                <td class="rateb-actions text-nowrap">
                    <form method="post" action="<?php echo rateb_url($routePrefix . '/' . (int) ($row['id'] ?? 0) . '/delete'); ?>" class="d-inline" data-confirm-delete="<?php echo Rateb\App\Core\View::escape(__('confirm_delete')); ?>">
                        <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="<?php echo __('delete'); ?>"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
                <?php } ?>
            </tr>
            <?php }
            } ?>
        </tbody>
    </table>
</div>
