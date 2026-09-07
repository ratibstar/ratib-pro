<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $menus */
/** @var array<string,mixed>|null $current */
/** @var list<array<string,mixed>> $items */
/** @var list<array<string,mixed>> $footerColumns */
$phEn = htmlspecialchars(__('website_label_en'), ENT_QUOTES, 'UTF-8');
$phAr = htmlspecialchars(__('website_label_ar'), ENT_QUOTES, 'UTF-8');
$phUrl = htmlspecialchars(__('website_url'), ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rateb_asset('css/website-builder.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="container-fluid py-3 wb-admin" id="websiteMenusRoot"
     data-csrf="<?php echo htmlspecialchars($csrf ?? '', ENT_QUOTES, 'UTF-8'); ?>"
     data-save-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/menus/items')), ENT_QUOTES, 'UTF-8'); ?>"
     data-footer-url="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/menus/footer')), ENT_QUOTES, 'UTF-8'); ?>"
     data-menu-id="<?php echo (int) ($current['id'] ?? 0); ?>"
     data-ph-en="<?php echo $phEn; ?>"
     data-ph-ar="<?php echo $phAr; ?>"
     data-ph-url="<?php echo $phUrl; ?>">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('website_menus')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="mb-3 d-flex gap-2 flex-wrap">
        <?php foreach (($menus ?? []) as $m) { ?>
        <a class="btn btn-sm <?php echo ((int) ($current['id'] ?? 0) === (int) $m['id']) ? 'btn-primary' : 'btn-outline-primary'; ?>"
           href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/menus') . '?menu_id=' . (int) $m['id']), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars(rateb_website_menu_name($m), ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <?php } ?>
    </div>
    <?php if ($current) { ?>
    <div class="rateb-card mb-3">
        <div class="rateb-card-header d-flex justify-content-between"><strong><?php echo htmlspecialchars(rateb_website_menu_name($current), ENT_QUOTES, 'UTF-8'); ?></strong>
            <button type="button" class="btn btn-sm btn-primary" id="wbMenuSave"><?php echo htmlspecialchars(__('website_save_menu'), ENT_QUOTES, 'UTF-8'); ?></button>
        </div>
        <div class="rateb-card-body">
            <div id="wbMenuItems">
                <?php foreach (($items ?? []) as $i => $item) {
                    $ar = (string) ($item['label_ar'] ?? '');
                    $en = (string) ($item['label_en'] ?? '');
                    if (rateb_website_is_placeholder($ar)) {
                        $ar = rateb_website_nav_label($item);
                    }
                    if (rateb_website_is_placeholder($en)) {
                        $en = rateb_website_nav_label($item);
                    }
                    ?>
                <div class="row g-2 mb-2 wb-menu-row" data-key="k<?php echo $i; ?>">
                    <div class="col-md-3"><input class="form-control form-control-sm" data-field="label_en" value="<?php echo htmlspecialchars($en, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo $phEn; ?>"></div>
                    <div class="col-md-3"><input class="form-control form-control-sm" data-field="label_ar" value="<?php echo htmlspecialchars($ar, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo $phAr; ?>"></div>
                    <div class="col-md-3"><input class="form-control form-control-sm" data-field="url" value="<?php echo htmlspecialchars((string) ($item['url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo $phUrl; ?>"></div>
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
            <?php foreach (($footerColumns ?? []) as $col) {
                $links = $col['links_json'] ?? [];
                if (is_string($links)) {
                    $decoded = json_decode($links, true);
                    $links = is_array($decoded) ? $decoded : [];
                }
                if (!is_array($links)) {
                    $links = [];
                }
                $titleAr = (string) ($col['title_ar'] ?? '');
                $titleEn = (string) ($col['title_en'] ?? '');
                if (rateb_website_is_placeholder($titleAr)) {
                    $titleAr = rateb_website_pick($col, 'title_en', 'title_ar');
                    if (rateb_website_is_placeholder($titleAr)) {
                        $titleAr = __('cms_quick_links');
                    }
                }
                ?>
            <div class="wb-footer-row mb-4">
                <div class="row g-2 mb-2">
                    <div class="col-md-6"><input class="form-control form-control-sm" data-field="title_en" value="<?php echo htmlspecialchars($titleEn, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(__('website_title_en_short'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                    <div class="col-md-6"><input class="form-control form-control-sm" data-field="title_ar" value="<?php echo htmlspecialchars($titleAr, ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo htmlspecialchars(__('website_title_ar_short'), ENT_QUOTES, 'UTF-8'); ?>"></div>
                </div>
                <div class="wb-footer-links">
                    <?php foreach ($links as $link) {
                        if (!is_array($link)) {
                            continue;
                        }
                        ?>
                    <div class="row g-2 mb-2 wb-footer-link">
                        <div class="col-md-4"><input class="form-control form-control-sm" data-field="label_ar" value="<?php echo htmlspecialchars(rateb_website_nav_label($link), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo $phAr; ?>"></div>
                        <div class="col-md-4"><input class="form-control form-control-sm" data-field="label_en" value="<?php echo htmlspecialchars(rateb_website_is_placeholder((string) ($link['label_en'] ?? $link['label'] ?? '')) ? rateb_website_nav_label($link) : (string) ($link['label_en'] ?? $link['label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo $phEn; ?>"></div>
                        <div class="col-md-3"><input class="form-control form-control-sm" data-field="url" value="<?php echo htmlspecialchars((string) ($link['url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo $phUrl; ?>"></div>
                        <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger wb-footer-link-remove">×</button></div>
                    </div>
                    <?php } ?>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary wb-footer-add-link"><?php echo htmlspecialchars(__('website_add_link'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
            <?php } ?>
        </div>
    </div>
</div>
<script src="<?php echo htmlspecialchars(rateb_asset('js/website-menus.js'), ENT_QUOTES, 'UTF-8'); ?>" defer></script>
