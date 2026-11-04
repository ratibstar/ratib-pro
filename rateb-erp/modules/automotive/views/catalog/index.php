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
<?php $localeAr = function_exists('rateb_locale') && rateb_locale() === 'ar'; ?>
<h2 class="h6"><?php echo View::escape(__('auto_epc_coverage')); ?></h2>
<?php if (($coverage ?? []) === []): ?>
<p class="text-muted"><?php echo View::escape(__('auto_epc_no_coverage')); ?></p>
<?php else: ?>
<ul>
<?php foreach ($coverage as $item): ?>
<li><?php echo View::escape((string) $item['market_code'] . ' ' . (string) $item['brand'] . ' ' . (string) $item['year_from'] . '-' . (string) $item['year_to']); ?></li>
<?php endforeach; ?>
</ul>
<?php endif; ?>
<?php foreach (($providers ?? []) as $provider): ?>
<p><?php echo View::escape($localeAr ? (string) $provider['name_ar'] : (string) $provider['name_en']); ?> — <?php echo View::escape(__('auto_epc_provider_not_loaded')); ?></p>
<?php endforeach; ?>
<?php if (!empty($can_import)): ?>
<form method="post" action="<?php echo rateb_app_url('automotive/reference/imports'); ?>" class="mb-3">
    <?php echo rateb_csrf_field(); ?>
    <input class="form-control mb-2" name="source_id" required placeholder="<?php echo View::escape(__('auto_epc_record_id')); ?>">
    <textarea class="form-control mb-2" name="csv" required placeholder="<?php echo View::escape(__('auto_epc_import_licensed')); ?>"></textarea>
    <button class="btn btn-primary" type="submit"><?php echo View::escape(__('auto_epc_import_licensed')); ?></button>
</form>
<?php endif; ?>
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
