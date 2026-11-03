<?php
declare(strict_types=1);

use Rateb\App\Core\View;

/** @var list<array<string, mixed>> $vehicles */
$vehicles = $vehicles ?? [];
$canManage = !empty($can_manage);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?php echo View::escape((string) ($title ?? __('auto_vehicles'))); ?></h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_app_url('automotive'); ?>"><?php echo View::escape(__('automotive_dashboard')); ?></a>
</div>
<?php if ($canManage): ?>
<form method="post" action="<?php echo rateb_app_url('automotive/vehicles'); ?>" class="card mb-4">
    <?php echo rateb_csrf_field(); ?>
    <div class="card-body row g-3">
        <div class="col-md-3"><label class="form-label"><?php echo View::escape(__('auto_make')); ?></label><input class="form-control" name="make" required maxlength="80"></div>
        <div class="col-md-3"><label class="form-label"><?php echo View::escape(__('auto_model')); ?></label><input class="form-control" name="model" required maxlength="80"></div>
        <div class="col-md-2"><label class="form-label"><?php echo View::escape(__('auto_year')); ?></label><input class="form-control" name="model_year" required type="number" min="1900" max="2100"></div>
        <div class="col-md-2"><label class="form-label"><?php echo View::escape(__('auto_engine')); ?></label><input class="form-control" name="engine" maxlength="80"></div>
        <div class="col-md-2"><label class="form-label"><?php echo View::escape(__('auto_trim')); ?></label><input class="form-control" name="trim" maxlength="80"></div>
        <div class="col-12"><button class="btn btn-primary" type="submit"><?php echo View::escape(__('save')); ?></button></div>
    </div>
</form>
<?php endif; ?>
<div class="card"><div class="table-responsive"><table class="table mb-0">
    <thead><tr>
        <th><?php echo View::escape(__('auto_make')); ?></th>
        <th><?php echo View::escape(__('auto_model')); ?></th>
        <th><?php echo View::escape(__('auto_year')); ?></th>
        <th><?php echo View::escape(__('auto_engine')); ?></th>
        <th><?php echo View::escape(__('auto_trim')); ?></th>
    </tr></thead>
    <tbody>
    <?php if ($vehicles === []): ?><tr><td colspan="5" class="text-muted"><?php echo View::escape(__('no_records')); ?></td></tr><?php endif; ?>
    <?php foreach ($vehicles as $vehicle): ?>
        <tr>
            <td><?php echo View::escape((string) $vehicle['make']); ?></td>
            <td><?php echo View::escape((string) $vehicle['model']); ?></td>
            <td><?php echo (int) $vehicle['model_year']; ?></td>
            <td><?php echo View::escape((string) ($vehicle['engine'] ?? '')); ?></td>
            <td><?php echo View::escape((string) ($vehicle['trim'] ?? '')); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
