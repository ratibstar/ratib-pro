<?php
declare(strict_types=1);

use Rateb\App\Core\View;

$markets = $markets ?? [];
$providers = $providers ?? [];
$rights = $rights ?? [];
$coverage = $coverage ?? [];
$canManage = !empty($can_manage);
$canImport = !empty($can_import);
$localeAr = function_exists('rateb_locale') && rateb_locale() === 'ar';
?>
<div class="d-flex justify-content-between align-items-center mb-3" dir="<?php echo function_exists('rateb_is_rtl') && rateb_is_rtl() ? 'rtl' : 'ltr'; ?>">
    <h1 class="h4 mb-0"><?php echo View::escape((string) ($title ?? '')); ?></h1>
</div>
<p class="text-muted"><?php echo View::escape(__('auto_gulf_scope')); ?></p>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><?php echo View::escape(__('auto_gulf_markets')); ?></h2>
            <ul class="mb-0">
                <?php foreach ($markets as $row): ?>
                    <li><?php echo View::escape($localeAr ? (string) $row['name_ar'] : (string) $row['name_en']); ?> (<?php echo View::escape((string) $row['code']); ?>)</li>
                <?php endforeach; ?>
            </ul>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100"><div class="card-body">
            <h2 class="h6"><?php echo View::escape(__('auto_gulf_providers')); ?></h2>
            <?php if ($providers === []): ?>
                <p class="text-muted"><?php echo View::escape(__('auto_epc_empty')); ?></p>
            <?php else: ?>
                <ul>
                    <?php foreach ($providers as $row): ?>
                        <li><?php echo View::escape($localeAr ? (string) $row['name_ar'] : (string) $row['name_en']); ?> — <?php echo View::escape(__('auto_gulf_status_' . (string) $row['status'])); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($canManage): ?>
            <form method="post" action="<?php echo rateb_app_url('automotive/gulf/providers'); ?>">
                <?php echo rateb_csrf_field(); ?>
                <input class="form-control mb-2" name="code" required maxlength="40" placeholder="<?php echo View::escape(__('auto_epc_code')); ?>">
                <input class="form-control mb-2" name="name_en" required maxlength="150" placeholder="<?php echo View::escape(__('auto_epc_name_en')); ?>">
                <input class="form-control mb-2" name="name_ar" required maxlength="150" placeholder="<?php echo View::escape(__('auto_epc_name_ar')); ?>">
                <input class="form-control mb-2" name="source_note" required maxlength="250" placeholder="<?php echo View::escape(__('auto_gulf_source')); ?>">
                <input class="form-control mb-2" name="website" maxlength="200" placeholder="<?php echo View::escape(__('auto_gulf_website')); ?>">
                <input class="form-control mb-2" name="review_note" maxlength="300" placeholder="<?php echo View::escape(__('auto_gulf_review')); ?>">
                <select class="form-select mb-2" name="provider_type">
                    <option value="data_provider"><?php echo View::escape(__('auto_gulf_type_provider')); ?></option>
                    <option value="manufacturer"><?php echo View::escape(__('auto_gulf_type_manufacturer')); ?></option>
                    <option value="data_owner"><?php echo View::escape(__('auto_gulf_type_owner')); ?></option>
                </select>
                <select class="form-select mb-2" name="status">
                    <option value="pending"><?php echo View::escape(__('auto_gulf_status_pending')); ?></option>
                    <option value="active"><?php echo View::escape(__('auto_gulf_status_active')); ?></option>
                    <option value="suspended"><?php echo View::escape(__('auto_gulf_status_suspended')); ?></option>
                    <option value="rejected"><?php echo View::escape(__('auto_gulf_status_rejected')); ?></option>
                </select>
                <select class="form-select mb-2" name="markets[]">
                    <?php foreach ($markets as $row): ?>
                        <option value="<?php echo View::escape((string) $row['code']); ?>"><?php echo View::escape((string) $row['code']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select mb-2" name="categories[]">
                    <option value="vehicle_identity"><?php echo View::escape(__('auto_gulf_cat_vehicle_identity')); ?></option>
                    <option value="oem_parts"><?php echo View::escape(__('auto_gulf_cat_oem_parts')); ?></option>
                    <option value="diagrams"><?php echo View::escape(__('auto_gulf_cat_diagrams')); ?></option>
                    <option value="fitment"><?php echo View::escape(__('auto_gulf_cat_fitment')); ?></option>
                    <option value="aftermarket"><?php echo View::escape(__('auto_gulf_cat_aftermarket')); ?></option>
                    <option value="vin_decoding"><?php echo View::escape(__('auto_gulf_cat_vin_decoding')); ?></option>
                </select>
                <button class="btn btn-primary btn-sm" type="submit"><?php echo View::escape(__('save')); ?></button>
            </form>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <h2 class="h6"><?php echo View::escape(__('auto_gulf_rights')); ?></h2>
            <?php if ($rights === []): ?>
                <p class="text-muted"><?php echo View::escape(__('auto_epc_empty')); ?></p>
            <?php else: ?>
                <ul>
                    <?php foreach ($rights as $row): ?>
                        <li><?php echo View::escape((string) $row['provider_code']); ?> — <?php echo View::escape(__('auto_gulf_right_' . (string) $row['commercial_display'])); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($canManage && $providers !== []): ?>
            <form method="post" action="<?php echo rateb_app_url('automotive/gulf/rights'); ?>">
                <?php echo rateb_csrf_field(); ?>
                <select class="form-select mb-2" name="provider_id">
                    <?php foreach ($providers as $row): ?>
                        <option value="<?php echo (int) $row['id']; ?>"><?php echo View::escape((string) $row['code']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="form-control mb-2" name="evidence" required maxlength="300" placeholder="<?php echo View::escape(__('auto_gulf_evidence')); ?>">
                <input class="form-control mb-2" name="territory_codes" maxlength="40" placeholder="<?php echo View::escape(__('auto_gulf_territory')); ?>">
                <button class="btn btn-primary btn-sm" type="submit"><?php echo View::escape(__('save')); ?></button>
            </form>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card"><div class="card-body">
            <h2 class="h6"><?php echo View::escape(__('auto_gulf_coverage')); ?></h2>
            <?php if ($coverage === []): ?>
                <p class="text-muted"><?php echo View::escape(__('auto_gulf_no_coverage')); ?></p>
            <?php else: ?>
                <ul>
                    <?php foreach ($coverage as $row): ?>
                        <li><?php echo View::escape((string) $row['market_code']); ?> — <?php echo View::escape(__('auto_gulf_cat_' . (string) $row['data_category'])); ?> — <?php echo View::escape(__('auto_gulf_verify_' . (string) $row['verification'])); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ($canManage && $providers !== []): ?>
            <form method="post" action="<?php echo rateb_app_url('automotive/gulf/coverage'); ?>">
                <?php echo rateb_csrf_field(); ?>
                <select class="form-select mb-2" name="provider_id">
                    <?php foreach ($providers as $row): ?>
                        <option value="<?php echo (int) $row['id']; ?>"><?php echo View::escape((string) $row['code']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select mb-2" name="market_code">
                    <?php foreach ($markets as $row): ?>
                        <option value="<?php echo View::escape((string) $row['code']); ?>"><?php echo View::escape((string) $row['code']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select mb-2" name="data_category">
                    <option value="vehicle_identity"><?php echo View::escape(__('auto_gulf_cat_vehicle_identity')); ?></option>
                </select>
                <select class="form-select mb-2" name="verification">
                    <option value="unverified"><?php echo View::escape(__('auto_gulf_verify_unverified')); ?></option>
                    <option value="unavailable"><?php echo View::escape(__('auto_gulf_verify_unavailable')); ?></option>
                </select>
                <select class="form-select mb-2" name="license_status">
                    <option value="unknown"><?php echo View::escape(__('auto_gulf_right_unknown')); ?></option>
                </select>
                <button class="btn btn-primary btn-sm" type="submit"><?php echo View::escape(__('save')); ?></button>
            </form>
            <?php endif; ?>
            <?php if ($canImport): ?>
            <form method="post" action="<?php echo rateb_app_url('automotive/gulf/import-dry-run'); ?>">
                <?php echo rateb_csrf_field(); ?>
                <input class="form-control mb-2" name="rights_id" required placeholder="<?php echo View::escape(__('auto_gulf_rights_id')); ?>">
                <input class="form-control mb-2" name="market_code" required maxlength="2" placeholder="<?php echo View::escape(__('auto_epc_code')); ?>">
                <textarea class="form-control mb-2" name="payload" required placeholder="<?php echo View::escape(__('auto_gulf_fixture')); ?>"></textarea>
                <button class="btn btn-outline-primary btn-sm" type="submit"><?php echo View::escape(__('auto_gulf_dry_run')); ?></button>
            </form>
            <?php endif; ?>
        </div></div>
    </div>
</div>
