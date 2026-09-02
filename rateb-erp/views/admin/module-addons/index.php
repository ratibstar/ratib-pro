<?php
/** @var list<array{item:array<string,mixed>,slug:string,saving:array<string,mixed>|null,purchasable:bool,features_text:string}> $modules */
/** @var string $csrf */
/** @var bool $commerceEnabled */
/** @var bool $isPreviewHost */
/** @var bool $isPlatformHost */
/** @var list<array<string,mixed>> $unpaidInvoices */
$modules = is_array($modules ?? null) ? $modules : [];
$unpaidInvoices = is_array($unpaidInvoices ?? null) ? $unpaidInvoices : [];
$esc = static fn (mixed $v): string => Rateb\App\Core\View::escape((string) $v);
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/module-addon-catalog.css'); ?>">

<section class="rateb-mac-page" aria-labelledby="rateb-mac-title">
    <header class="rateb-mac-hero">
        <div>
            <p class="rateb-mac-kicker"><?php echo $esc(__('module_addon_catalog_kicker')); ?></p>
            <h1 id="rateb-mac-title" class="rateb-mac-title"><?php echo $esc($title ?? __('module_addon_catalog')); ?></h1>
            <p class="rateb-mac-lead"><?php echo $esc(__('module_addon_catalog_help')); ?></p>
            <p class="rateb-mac-note"><?php echo $esc(__('module_addon_catalog_vs_tenant')); ?></p>
            <?php if (!empty($isPreviewHost)) { ?>
            <p class="rateb-mac-note"><a href="<?php echo $esc(rateb_url('admin/billing/addon-locks')); ?>"><?php echo $esc(__('module_addon_demo_locks')); ?></a></p>
            <?php } ?>
        </div>
        <div class="rateb-mac-flags">
            <?php if (!empty($commerceEnabled)) { ?>
            <span class="rateb-mac-chip rateb-mac-chip--on"><?php echo $esc(__('module_addon_catalog_flag_on')); ?></span>
            <?php } else { ?>
            <span class="rateb-mac-chip"><?php echo $esc(__('module_addon_catalog_flag_off')); ?></span>
            <?php } ?>
            <?php if (!empty($isPreviewHost)) { ?>
            <span class="rateb-mac-chip rateb-mac-chip--preview"><?php echo $esc(__('module_addon_catalog_preview_host')); ?></span>
            <?php } ?>
            <?php if (!empty($isPlatformHost) && empty($isPreviewHost)) { ?>
            <span class="rateb-mac-chip rateb-mac-chip--warn"><?php echo $esc(__('module_addon_catalog_production_warn')); ?></span>
            <?php } ?>
        </div>
    </header>

    <form method="post" action="<?php echo $esc((string) ($saveAction ?? rateb_url('admin/module-addons'))); ?>" class="rateb-mac-form">
        <input type="hidden" name="_csrf" value="<?php echo $esc((string) $csrf); ?>">

        <?php foreach ($modules as $row) {
            $item = is_array($row['item'] ?? null) ? $row['item'] : [];
            $slug = (string) ($row['slug'] ?? $item['slug'] ?? '');
            if ($slug === '') {
                continue;
            }
            $prefix = 'modules[' . $slug . ']';
            $saving = is_array($row['saving'] ?? null) ? $row['saving'] : null;
            $purchasable = !empty($row['purchasable']);
            $icon = (string) ($item['icon'] ?? $slug);
            ?>
        <article class="rateb-mac-card" id="module-<?php echo $esc($slug); ?>">
            <header class="rateb-mac-card-head">
                <span class="rateb-mac-icon" aria-hidden="true">
                    <?php $svgIcon = $icon; require RATEB_ROOT . '/views/billing/partials/addon-svg.php'; ?>
                </span>
                <div>
                    <h2><?php echo $esc((string) ($item['name'] ?? $slug)); ?>
                        <span class="rateb-mac-slug"><?php echo $esc($slug); ?></span>
                    </h2>
                    <?php if ($purchasable) { ?>
                    <span class="rateb-mac-state rateb-mac-state--on"><?php echo $esc(__('module_addon_catalog_purchasable')); ?></span>
                    <?php } else { ?>
                    <span class="rateb-mac-state"><?php echo $esc(__('module_addon_catalog_hidden')); ?></span>
                    <?php } ?>
                </div>
            </header>

            <div class="rateb-mac-grid">
                <label class="rateb-mac-switch">
                    <input type="hidden" name="<?php echo $esc($prefix); ?>[enabled]" value="0">
                    <input type="checkbox" name="<?php echo $esc($prefix); ?>[enabled]" value="1" <?php echo !empty($item['enabled']) ? 'checked' : ''; ?>>
                    <span><?php echo $esc(__('module_addon_catalog_commercial')); ?></span>
                </label>
                <label class="rateb-mac-switch">
                    <input type="hidden" name="<?php echo $esc($prefix); ?>[featured]" value="0">
                    <input type="checkbox" name="<?php echo $esc($prefix); ?>[featured]" value="1" <?php echo !empty($item['featured']) ? 'checked' : ''; ?>>
                    <span><?php echo $esc(__('module_addon_catalog_featured')); ?></span>
                </label>
                <label>
                    <span><?php echo $esc(__('module_addon_catalog_monthly')); ?></span>
                    <input type="number" name="<?php echo $esc($prefix); ?>[monthly]" min="0" step="0.01" value="<?php echo $esc(number_format((float) ($item['monthly'] ?? 0), 2, '.', '')); ?>">
                </label>
                <label>
                    <span><?php echo $esc(__('module_addon_catalog_yearly')); ?></span>
                    <input type="number" name="<?php echo $esc($prefix); ?>[yearly]" min="0" step="0.01" value="<?php echo $esc(number_format((float) ($item['yearly'] ?? 0), 2, '.', '')); ?>">
                </label>
                <label>
                    <span><?php echo $esc(__('module_addon_catalog_sort')); ?></span>
                    <input type="number" name="<?php echo $esc($prefix); ?>[sort_order]" min="0" max="9999" value="<?php echo $esc((string) (int) ($item['sort_order'] ?? 100)); ?>">
                </label>
                <label>
                    <span><?php echo $esc(__('module_addon_catalog_promo')); ?></span>
                    <select name="<?php echo $esc($prefix); ?>[promo_label]">
                        <option value=""><?php echo $esc(__('none')); ?></option>
                        <option value="popular" <?php echo (($item['promo_label'] ?? '') === 'popular') ? 'selected' : ''; ?>>POPULAR</option>
                        <option value="best_value" <?php echo (($item['promo_label'] ?? '') === 'best_value') ? 'selected' : ''; ?>>BEST VALUE</option>
                        <option value="recommended" <?php echo (($item['promo_label'] ?? '') === 'recommended') ? 'selected' : ''; ?>>RECOMMENDED</option>
                    </select>
                </label>
            </div>

            <?php if (is_array($saving) && (float) ($saving['percent'] ?? 0) > 0) { ?>
            <p class="rateb-mac-saving">
                <?php echo $esc(__('module_addon_catalog_saving')); ?>:
                <?php echo $esc(number_format((float) ($saving['amount'] ?? 0), 2)); ?> SAR
                (<?php echo $esc(number_format((float) $saving['percent'], 2)); ?>%)
            </p>
            <?php } ?>

            <div class="rateb-mac-copy">
                <label>
                    <span><?php echo $esc(__('name')); ?> (EN)</span>
                    <input type="text" name="<?php echo $esc($prefix); ?>[name]" value="<?php echo $esc((string) ($item['name'] ?? '')); ?>">
                </label>
                <label>
                    <span><?php echo $esc(__('name')); ?> (AR)</span>
                    <input type="text" name="<?php echo $esc($prefix); ?>[name_ar]" value="<?php echo $esc((string) ($item['name_ar'] ?? '')); ?>" dir="rtl">
                </label>
                <label class="rateb-mac-span">
                    <span><?php echo $esc(__('description')); ?> (EN)</span>
                    <textarea name="<?php echo $esc($prefix); ?>[description]" rows="2"><?php echo $esc((string) ($item['description'] ?? '')); ?></textarea>
                </label>
                <label class="rateb-mac-span">
                    <span><?php echo $esc(__('description')); ?> (AR)</span>
                    <textarea name="<?php echo $esc($prefix); ?>[description_ar]" rows="2" dir="rtl"><?php echo $esc((string) ($item['description_ar'] ?? '')); ?></textarea>
                </label>
                <label class="rateb-mac-span">
                    <span><?php echo $esc(__('module_addon_catalog_features')); ?></span>
                    <textarea name="<?php echo $esc($prefix); ?>[features]" rows="5"><?php echo $esc((string) ($row['features_text'] ?? '')); ?></textarea>
                </label>
            </div>
            <input type="hidden" name="<?php echo $esc($prefix); ?>[icon]" value="<?php echo $esc($icon); ?>">
        </article>
        <?php } ?>

        <div class="rateb-mac-actions">
            <button type="submit" class="btn btn-primary"><?php echo $esc(__('save')); ?></button>
        </div>
    </form>

    <?php if (!empty($isPreviewHost)) { ?>
    <section class="rateb-mac-invoices" aria-labelledby="rateb-mac-invoices-title">
        <h2 id="rateb-mac-invoices-title"><?php echo $esc(__('module_addon_catalog_unpaid')); ?></h2>
        <p class="rateb-mac-note"><?php echo $esc(__('module_addon_catalog_void_help')); ?></p>
        <?php if ($unpaidInvoices === []) { ?>
        <p class="rateb-mac-empty"><?php echo $esc(__('no_records')); ?></p>
        <?php } else { ?>
        <ul class="rateb-mac-invoice-list">
            <?php foreach ($unpaidInvoices as $inv) {
                $iid = (int) ($inv['id'] ?? 0);
                if ($iid < 1) {
                    continue;
                }
                ?>
            <li>
                <div>
                    <strong><?php echo $esc((string) ($inv['invoice_no'] ?? ('#' . $iid))); ?></strong>
                    <span><?php echo $esc((string) ($inv['company_name'] ?? '')); ?></span>
                    <span><?php echo $esc((string) ($inv['po_number'] ?? '')); ?></span>
                    <span><?php echo $esc((string) ($inv['payment_status'] ?? '')); ?></span>
                    <span><?php echo $esc(number_format((float) ($inv['total_amount'] ?? 0), 2)); ?> <?php echo $esc((string) ($inv['currency'] ?? 'SAR')); ?></span>
                </div>
                <form method="post" action="<?php echo $esc((string) ($voidAction ?? rateb_url('admin/module-addons/void-invoice'))); ?>">
                    <input type="hidden" name="_csrf" value="<?php echo $esc((string) $csrf); ?>">
                    <input type="hidden" name="invoice_id" value="<?php echo $iid; ?>">
                    <button type="submit" class="btn btn-outline-secondary btn-sm"><?php echo $esc(__('module_addon_catalog_void')); ?></button>
                </form>
            </li>
            <?php } ?>
        </ul>
        <?php } ?>
    </section>
    <?php } ?>
</section>
