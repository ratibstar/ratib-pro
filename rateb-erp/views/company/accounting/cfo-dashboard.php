<?php
$m = $metrics ?? [];
Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'company']);
Rateb\App\Core\View::partial('accounting-reports-back');
?>
<p class="text-muted small"><?php echo __('cfo_dashboard_help'); ?></p>
