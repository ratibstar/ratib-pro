<?php
declare(strict_types=1);

use Rateb\App\Core\View;

/** @var array<string, mixed> $part */
$part = $part ?? [];
$canManage = !empty($can_manage);
$partId = (int) ($part['id'] ?? 0);
$action = rateb_app_url('automotive/parts/' . $partId);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?php echo View::escape((string) ($part['item_name'] ?? __('auto_part'))); ?></h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_app_url('automotive/parts'); ?>"><?php echo View::escape(__('auto_parts')); ?></a>
</div>
<div class="card mb-3"><div class="card-body">
    <div><?php echo View::escape(__('auto_manufacturer')); ?>: <?php echo View::escape((string) ($part['manufacturer'] ?? '')); ?></div>
    <div><?php echo View::escape(__('auto_brand')); ?>: <?php echo View::escape((string) ($part['brand'] ?? '')); ?></div>
    <div><?php echo View::escape(__('auto_oem_number')); ?>: <?php echo View::escape((string) ($part['oem_number'] ?? '')); ?></div>
    <div><?php echo View::escape(__('auto_inventory_id')); ?>: <?php echo (int) ($part['inventory_id'] ?? 0); ?></div>
</div></div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><?php echo View::escape(__('auto_part_numbers')); ?></h2>
            <ul class="mb-3">
                <?php foreach (($part['numbers'] ?? []) as $row): ?>
                    <li><?php echo View::escape((string) $row['code_type'] . ' ' . $row['code']); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($canManage): ?>
            <form method="post" action="<?php echo $action; ?>/numbers" class="row g-2">
                <?php echo rateb_csrf_field(); ?>
                <div class="col-5"><input class="form-control" name="code" required maxlength="80" placeholder="<?php echo View::escape(__('auto_code')); ?>"></div>
                <div class="col-4">
                    <select class="form-select" name="code_type">
                        <option value="oem">OEM</option>
                        <option value="aftermarket"><?php echo View::escape(__('auto_aftermarket')); ?></option>
                        <option value="supplier"><?php echo View::escape(__('auto_supplier_number')); ?></option>
                    </select>
                </div>
                <div class="col-3"><button class="btn btn-primary w-100" type="submit"><?php echo View::escape(__('save')); ?></button></div>
            </form>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><?php echo View::escape(__('auto_barcodes')); ?></h2>
            <ul class="mb-3">
                <?php foreach (($part['barcodes'] ?? []) as $row): ?>
                    <li><?php echo View::escape((string) $row['code']); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($canManage): ?>
            <form method="post" action="<?php echo $action; ?>/barcodes" class="row g-2">
                <?php echo rateb_csrf_field(); ?>
                <div class="col-8"><input class="form-control" name="code" required maxlength="80"></div>
                <div class="col-4"><button class="btn btn-primary w-100" type="submit"><?php echo View::escape(__('save')); ?></button></div>
            </form>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><?php echo View::escape(__('auto_fitment')); ?></h2>
            <ul class="mb-3">
                <?php foreach (($part['fitment'] ?? []) as $row): ?>
                    <li><?php echo View::escape(trim($row['make'] . ' ' . $row['model'] . ' ' . $row['model_year'] . ' ' . $row['engine'] . ' ' . $row['trim'])); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($canManage): ?>
            <form method="post" action="<?php echo $action; ?>/fitment" class="row g-2">
                <?php echo rateb_csrf_field(); ?>
                <div class="col-8">
                    <select class="form-select" name="vehicle_id" required>
                        <option value=""><?php echo View::escape(__('auto_choose_vehicle')); ?></option>
                        <?php foreach (($vehicles ?? []) as $vehicle): ?>
                            <option value="<?php echo (int) $vehicle['id']; ?>"><?php echo View::escape($vehicle['make'] . ' ' . $vehicle['model'] . ' ' . $vehicle['model_year']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-4"><button class="btn btn-primary w-100" type="submit"><?php echo View::escape(__('save')); ?></button></div>
            </form>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><?php echo View::escape(__('auto_interchange')); ?></h2>
            <ul class="mb-3">
                <?php foreach (($part['interchange'] ?? []) as $row): ?>
                    <li><?php echo View::escape((string) ($row['item_name'] ?? '') . ' #' . $row['alternate_inventory_id']); ?></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($canManage): ?>
            <form method="post" action="<?php echo $action; ?>/interchange" class="row g-2">
                <?php echo rateb_csrf_field(); ?>
                <div class="col-8">
                    <select class="form-select" name="alternate_inventory_id" required>
                        <option value=""><?php echo View::escape(__('auto_choose_inventory')); ?></option>
                        <?php foreach (($inventory_choices ?? []) as $item): ?>
                            <option value="<?php echo (int) $item['id']; ?>"><?php echo View::escape((string) $item['item_name'] . ' #' . $item['id']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-4"><button class="btn btn-primary w-100" type="submit"><?php echo View::escape(__('save')); ?></button></div>
            </form>
            <?php endif; ?>
        </div></div>
    </div>
</div>
