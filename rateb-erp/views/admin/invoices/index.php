<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => 'admin']); ?>
<?php if (!empty($dueAlerts)) { foreach ($dueAlerts as $alert) { ?>
<div class="alert alert-<?php echo Rateb\App\Core\View::escape($alert['type'] ?? 'warning'); ?> rateb-due-alert py-2">
    <i class="fas fa-bell"></i> <?php echo Rateb\App\Core\View::escape($alert['message'] ?? ''); ?>
</div>
<?php } } ?>
<?php Rateb\App\Core\View::partial('crud-index', get_defined_vars()); ?>
