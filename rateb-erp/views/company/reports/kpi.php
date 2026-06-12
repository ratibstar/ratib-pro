<?php $d = $data ?? []; ?>
<div class="rateb-card">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></span>
        <?php Rateb\App\Core\View::partial('export-toolbar', ['exportRoute' => $exportRoute ?? '', 'exportEnabled' => $exportEnabled ?? true]); ?>
    </div>
    <div class="rateb-card-body">
        <div class="row g-3">
            <?php
            $skip = ['pr_by_status', 'po_by_status'];
            foreach ($d as $key => $val) {
                if (in_array($key, $skip, true) || is_array($val)) {
                    continue;
                }
                ?>
            <div class="col-md-3"><div class="rateb-widget"><div class="rateb-widget-value"><?php echo is_numeric($val) ? number_format((float) $val, is_float($val + 0) && floor($val) != $val ? 2 : 0) : Rateb\App\Core\View::escape((string) $val); ?></div><div class="rateb-widget-label"><?php echo __( $key); ?></div></div></div>
            <?php } ?>
        </div>
    </div>
</div>
