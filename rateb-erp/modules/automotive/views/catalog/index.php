<?php
declare(strict_types=1);

use Rateb\App\Core\View;

$rows = $rows ?? [];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?php echo View::escape((string) ($title ?? __('auto_epc_catalog'))); ?></h1>
</div>
<form method="get" action="<?php echo rateb_app_url('automotive/catalog'); ?>" class="row g-2 mb-3">
    <div class="col-md-6"><input class="form-control" name="q" value="<?php echo View::escape((string) ($q ?? '')); ?>" placeholder="<?php echo View::escape(__('auto_epc_search')); ?>"></div>
    <div class="col-md-3"><input class="form-control" name="market" value="<?php echo View::escape((string) ($market ?? '')); ?>" placeholder="<?php echo View::escape(__('auto_epc_markets')); ?>"></div>
    <div class="col-md-3"><button class="btn btn-primary" type="submit"><?php echo View::escape(__('auto_epc_search')); ?></button></div>
</form>
<?php if ($rows === []): ?>
<p class="text-muted"><?php echo View::escape(__('auto_epc_no_licensed_data')); ?></p>
<?php else: ?>
<div class="table-responsive"><table class="table">
    <thead><tr><th><?php echo View::escape(__('auto_epc_name_en')); ?></th><th><?php echo View::escape(__('auto_epc_status')); ?></th><th><?php echo View::escape(__('auto_epc_fitment')); ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <tr>
            <td><?php echo View::escape((string) $row['name_en']); ?></td>
            <td><?php echo View::escape((string) $row['status']); ?></td>
            <td><?php echo View::escape((string) ($row['fitment_status'] ?? '')); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div>
<?php endif; ?>
