<?php
declare(strict_types=1);

use Rateb\App\Core\View;

$markets = $markets ?? [];
$organizations = $organizations ?? [];
$brands = $brands ?? [];
$ready = !empty($ready);
$canManage = !empty($can_manage);
$localeAr = function_exists('rateb_locale') && rateb_locale() === 'ar';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0"><?php echo View::escape((string) ($title ?? __('auto_epc_reference'))); ?></h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo rateb_app_url('automotive'); ?>"><?php echo View::escape(__('automotive_dashboard')); ?></a>
</div>
<?php if (!$ready): ?>
<div class="alert alert-warning"><?php echo View::escape(__('auto_epc_schema_pending')); ?></div>
<?php else: ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><?php echo View::escape(__('auto_epc_markets')); ?></h2>
            <?php if ($markets === []): ?>
                <p class="text-muted mb-3"><?php echo View::escape(__('auto_epc_empty')); ?></p>
            <?php else: ?>
                <ul class="mb-3">
                    <?php foreach ($markets as $row): ?>
                        <li><?php echo View::escape($localeAr ? (string) $row['name_ar'] : (string) $row['name_en']); ?> (<?php echo View::escape((string) $row['code']); ?>)</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($canManage): ?>
            <form method="post" action="<?php echo rateb_app_url('automotive/reference/markets'); ?>">
                <?php echo rateb_csrf_field(); ?>
                <input class="form-control mb-2" name="code" maxlength="8" required placeholder="<?php echo View::escape(__('auto_epc_code')); ?>">
                <input class="form-control mb-2" name="name_en" maxlength="80" required placeholder="<?php echo View::escape(__('auto_epc_name_en')); ?>">
                <input class="form-control mb-2" name="name_ar" maxlength="80" required placeholder="<?php echo View::escape(__('auto_epc_name_ar')); ?>">
                <button class="btn btn-primary btn-sm" type="submit"><?php echo View::escape(__('save')); ?></button>
            </form>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><?php echo View::escape(__('auto_epc_organizations')); ?></h2>
            <?php if ($organizations === []): ?>
                <p class="text-muted mb-3"><?php echo View::escape(__('auto_epc_empty')); ?></p>
            <?php else: ?>
                <ul class="mb-3">
                    <?php foreach ($organizations as $row): ?>
                        <li><?php echo View::escape($localeAr ? (string) $row['name_ar'] : (string) $row['name_en']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($canManage): ?>
            <form method="post" action="<?php echo rateb_app_url('automotive/reference/organizations'); ?>">
                <?php echo rateb_csrf_field(); ?>
                <select class="form-select mb-2" name="org_role" required>
                    <option value="vehicle_manufacturer"><?php echo View::escape(__('auto_epc_role_vehicle_manufacturer')); ?></option>
                    <option value="parts_manufacturer"><?php echo View::escape(__('auto_epc_role_parts_manufacturer')); ?></option>
                    <option value="brand_owner"><?php echo View::escape(__('auto_epc_role_brand_owner')); ?></option>
                    <option value="distributor"><?php echo View::escape(__('auto_epc_role_distributor')); ?></option>
                    <option value="authorized_dealer"><?php echo View::escape(__('auto_epc_role_dealer')); ?></option>
                    <option value="branch"><?php echo View::escape(__('auto_epc_role_branch')); ?></option>
                </select>
                <select class="form-select mb-2" name="home_market_id" required>
                    <?php foreach ($markets as $row): ?>
                        <option value="<?php echo (int) $row['id']; ?>"><?php echo View::escape((string) $row['code']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control mb-2" name="name_en" required maxlength="150" placeholder="<?php echo View::escape(__('auto_epc_name_en')); ?>">
                <input class="form-control mb-2" name="name_ar" required maxlength="150" placeholder="<?php echo View::escape(__('auto_epc_name_ar')); ?>">
                <button class="btn btn-primary btn-sm" type="submit"><?php echo View::escape(__('save')); ?></button>
            </form>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><?php echo View::escape(__('auto_epc_vehicle_brands')); ?></h2>
            <?php if ($brands === []): ?>
                <p class="text-muted mb-3"><?php echo View::escape(__('auto_epc_empty')); ?></p>
            <?php else: ?>
                <ul class="mb-3">
                    <?php foreach ($brands as $row): ?>
                        <li><?php echo View::escape($localeAr ? (string) $row['name_ar'] : (string) $row['name_en']); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($canManage): ?>
            <form method="post" action="<?php echo rateb_app_url('automotive/reference/brands'); ?>">
                <?php echo rateb_csrf_field(); ?>
                <select class="form-select mb-2" name="vehicle_mfr_org_id" required>
                    <?php foreach ($organizations as $row): ?>
                        <?php if (($row['org_role'] ?? '') !== 'vehicle_manufacturer') { continue; } ?>
                        <option value="<?php echo (int) $row['id']; ?>"><?php echo View::escape((string) $row['name_en']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control mb-2" name="name_en" required maxlength="120" placeholder="<?php echo View::escape(__('auto_epc_name_en')); ?>">
                <input class="form-control mb-2" name="name_ar" required maxlength="120" placeholder="<?php echo View::escape(__('auto_epc_name_ar')); ?>">
                <button class="btn btn-primary btn-sm" type="submit"><?php echo View::escape(__('save')); ?></button>
            </form>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?php endif; ?>
