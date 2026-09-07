<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $menus */
/** @var array<string,mixed>|null $current */
/** @var list<array<string,mixed>> $items */
/** @var list<array<string,mixed>> $footerColumns */
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rateb_asset('css/website-builder.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="container-fluid py-3 wb-admin" id="websiteMenusRoot"
     data-csrf="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-save-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/menus/items')), ENT_QUOTES, 'UTF-8'); ?>"
     data-footer-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/menus/footer')), ENT_QUOTES, 'UTF-8'); ?>"
     data-menu-id="<?php echo (int) ($current['id'] ?? 0); ?>">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('website_menus')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="mb-3 d-flex gap-2 flex-wrap">
        <?php foreach (($menus ?? []) as $m) { ?>
        <a class="btn btn-sm <?php echo ((int) ($current['id'] ?? 0) === (int) $m['id']) ? 'btn-primary' : 'btn-outline-primary'; ?>"
           href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/menus') . '?menu_id=' . (int) $m['id']), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars(rateb_website_pick($m, 'name_en', 'name_ar') ?: (string) ($m['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <?php } ?>
    </div>
    <?php if ($current) { ?>
    <div class="rateb-card mb-3">
        <div class="rateb-card-header d-flex justify-content-between"><strong><?php echo htmlspecialchars(__('website_header_menu'), ENT_QUOTES, 'UTF-8'); ?></strong>
            <button type="button" class="btn btn-sm btn-primary" id="wbMenuSave"><?php echo htmlspecialchars(__('website_save_menu'), ENT_QUOTES, 'UTF-8'); ?></button>
        </div>
        <div class="rateb-card-body">
            <div id="wbMenuItems">
                <?php foreach (($items ?? []) as $i => $item) { ?>
                <div class="row g-2 mb-2 wb-menu-row" data-key="k<?php echo $i; ?>">
                    <div class="col-md-3"><input class="form-control form-control-sm" data-field="label_en" value="<?php echo htmlspecialchars((string) ($item['label_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(__('website_label_en'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="col-md-3"><input class="form-control form-control-sm" data-field="label_ar" value="<?php echo htmlspecialchars((string) ($item['label_ar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(__('website_label_ar'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="col-md-3"><input class="form-control form-control-sm" data-field="url" value="<?php echo htmlspecialchars((string) ($item['url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="URL"></div>
                    <div class="col-md-2"><input class="form-control form-control-sm" data-field="parent_key" value="" placeholder="<?php echo htmlspecialchars(__('website_parent_key'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger wb-menu-remove" aria-label="<?php echo htmlspecialchars(__('delete'), ENT_QUOTES, 'UTF-8'); ?>">×</button></div>
                </div>
                <?php } ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="wbMenuAdd"><?php echo htmlspecialchars(__('website_add_item'), ENT_QUOTES, 'UTF-8'); ?></button>
        </div>
    </div>
    <?php } ?>
    <div class="rateb-card">
        <div class="rateb-card-header d-flex justify-content-between"><strong><?php echo htmlspecialchars(__('website_footer_builder'), ENT_QUOTES, 'UTF-8'); ?></strong>
            <button type="button" class="btn btn-sm btn-primary" id="wbFooterSave"><?php echo htmlspecialchars(__('website_save_footer'), ENT_QUOTES, 'UTF-8'); ?></button>
        </div>
        <div class="rateb-card-body" id="wbFooterCols">
            <?php foreach (($footerColumns ?? []) as $i => $col) { ?>
            <div class="row g-2 mb-2 wb-footer-row">
                <div class="col-md-4"><input class="form-control form-control-sm" data-field="title_en" value="<?php echo htmlspecialchars((string) ($col['title_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(__('website_title_en_short'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                <div class="col-md-4"><input class="form-control form-control-sm" data-field="title_ar" value="<?php echo htmlspecialchars((string) ($col['title_ar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(__('website_title_ar_short'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                <div class="col-md-4"><input class="form-control form-control-sm" data-field="links_json" value="<?php echo htmlspecialchars(is_string($col['links_json'] ?? null) ? (string) $col['links_json'] : json_encode($col['links_json'] ?? []), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(__('website_links_json'), ENT_QUOTES, 'UTF-8'); ?>"></div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>
<script src="<?php echo htmlspecialchars(rateb_asset('js/website-menus.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
