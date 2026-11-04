<?php
declare(strict_types=1);

use Rateb\App\Core\View;

$rows = $rows ?? [];
?>
<div class="mb-3"><h1 class="h4"><?php echo View::escape((string) ($title ?? __('auto_epc_selection'))); ?></h1></div>
<?php if ($rows === []): ?>
<p class="text-muted"><?php echo View::escape(__('auto_epc_empty')); ?></p>
<?php else: ?>
<ul>
<?php foreach ($rows as $row): ?>
    <li><?php echo View::escape((string) $row['entity_type']); ?> #<?php echo (int) $row['entity_id']; ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php if (!empty($can_manage)): ?>
<form method="post" action="<?php echo rateb_app_url('automotive/selection'); ?>" class="row g-2">
    <?php echo rateb_csrf_field(); ?>
    <div class="col-md-4">
        <select class="form-select" name="entity_type">
            <option value="market"><?php echo View::escape(__('auto_epc_markets')); ?></option>
            <option value="part"><?php echo View::escape(__('auto_parts')); ?></option>
        </select>
    </div>
    <div class="col-md-4"><input class="form-control" name="entity_id" required placeholder="<?php echo View::escape(__('auto_epc_record_id')); ?>"></div>
    <div class="col-md-4"><button class="btn btn-primary" type="submit"><?php echo View::escape(__('save')); ?></button></div>
</form>
<?php endif; ?>
