<?php
/** @var mixed $value */
/** @var array<string, mixed> $col */
$meta = rateb_table_cell_meta($value ?? '', $col ?? []);
$titleAttr = ($meta['title'] ?? '') !== '' && ($meta['title'] ?? '') !== '—'
    ? ' title="' . Rateb\App\Core\View::escape((string) $meta['title']) . '"'
    : '';
$dirAttr = ($meta['dir'] ?? '') !== '' ? ' dir="' . Rateb\App\Core\View::escape((string) $meta['dir']) . '"' : '';
if (($meta['mode'] ?? '') === 'badge') {
    $badge = (string) ($meta['badge'] ?? 'secondary');
    if ($badge === '') {
        $badge = 'secondary';
    }
    $colNameAttr = (string) ($col['name'] ?? '');
    $rawVal = (string) ($value ?? '');
    ?>
<td<?php echo $titleAttr; ?><?php
    if ($colNameAttr !== '') {
        echo ' data-col-name="' . Rateb\App\Core\View::escape($colNameAttr) . '"';
        echo ' data-cell-value="' . Rateb\App\Core\View::escape($rawVal) . '"';
    }
?>><span class="badge bg-<?php echo Rateb\App\Core\View::escape($badge); ?>" data-rateb-live-badge-text="1"><?php echo Rateb\App\Core\View::escape((string) ($meta['display'] ?? '')); ?></span></td>
    <?php
    return;
}
?>
<td class="<?php echo Rateb\App\Core\View::escape(trim((string) ($meta['class'] ?? 'rateb-cell-clip'))); ?>"<?php echo $titleAttr . $dirAttr; ?>><?php echo Rateb\App\Core\View::escape((string) ($meta['display'] ?? '')); ?></td>
