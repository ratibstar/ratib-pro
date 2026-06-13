<?php
/** @var string $wrapperClass */
$wrapperClass = (string) ($wrapperClass ?? 'rateb-barcode-actions');
?>
<div class="dropdown <?php echo Rateb\App\Core\View::escape($wrapperClass); ?>">
    <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-ellipsis-v"></i> <?php echo __('actions'); ?>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <li>
            <button type="button" class="dropdown-item" data-doc-print>
                <i class="fas fa-print"></i> <?php echo __('print_label'); ?>
            </button>
        </li>
        <li>
            <button type="button" class="dropdown-item" data-doc-download>
                <i class="fas fa-download"></i> <?php echo __('download_png'); ?>
            </button>
        </li>
    </ul>
</div>
