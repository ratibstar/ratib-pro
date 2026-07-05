<?php
declare(strict_types=1);

/** @var array<string, mixed> $status */
?>
<div class="rateb-pos-page">
    <h1 class="h3 mb-3"><?php echo \Rateb\App\Pos\Support\PosView::escape($title ?? ''); ?></h1>
    <div class="alert alert-secondary"><?php echo __('pos_scaffold_notice'); ?></div>
    <pre class="rateb-pos-json-preview"><?php echo \Rateb\App\Pos\Support\PosView::escape(json_encode($status ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
</div>
<link href="<?php echo rateb_pos_asset('css/pos-module.css'); ?>" rel="stylesheet">
