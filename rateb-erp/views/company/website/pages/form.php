<?php
declare(strict_types=1);
/** @var array<string,mixed>|null $page */
/** @var string $action */
/** @var array<string,mixed>|null $seo */
$seo = $seo ?? [];
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rateb_asset('css/website-builder.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="container-fluid py-3 wb-admin">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? 'Page'), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" class="row g-3">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="col-md-4">
            <label class="form-label"><?php echo htmlspecialchars(__('slug'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" name="slug" required value="<?php echo htmlspecialchars((string) ($page['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label"><?php echo htmlspecialchars(__('website_title_en'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" name="title_en" value="<?php echo htmlspecialchars((string) ($page['title_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label"><?php echo htmlspecialchars(__('website_title_ar'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" name="title_ar" value="<?php echo htmlspecialchars((string) ($page['title_ar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label"><?php echo htmlspecialchars(__('website_template'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select class="form-select" name="template">
                <option value="builder"<?php echo (($page['template'] ?? 'builder') === 'builder') ? ' selected' : ''; ?>><?php echo htmlspecialchars(__('website_template_builder'), ENT_QUOTES, 'UTF-8'); ?></option>
                <option value="default"<?php echo (($page['template'] ?? '') === 'default') ? ' selected' : ''; ?>><?php echo htmlspecialchars(__('website_template_default'), ENT_QUOTES, 'UTF-8'); ?></option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label"><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select class="form-select" name="status">
                <?php foreach (['draft', 'published', 'scheduled'] as $st) { ?>
                <option value="<?php echo $st; ?>"<?php echo (($page['status'] ?? 'draft') === $st) ? ' selected' : ''; ?>><?php echo htmlspecialchars(rateb_website_status_label($st), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="col-12"><hr><h2 class="h5"><?php echo htmlspecialchars(__('website_seo'), ENT_QUOTES, 'UTF-8'); ?></h2></div>
        <div class="col-md-6">
            <label class="form-label"><?php echo htmlspecialchars(__('website_meta_title_en'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" name="seo[meta_title_en]" value="<?php echo htmlspecialchars((string) ($seo['meta_title_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label"><?php echo htmlspecialchars(__('website_meta_title_ar'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" name="seo[meta_title_ar]" value="<?php echo htmlspecialchars((string) ($seo['meta_title_ar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label"><?php echo htmlspecialchars(__('website_meta_desc_en'), ENT_QUOTES, 'UTF-8'); ?></label>
            <textarea class="form-control" name="seo[meta_description_en]" rows="2"><?php echo htmlspecialchars((string) ($seo['meta_description_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label"><?php echo htmlspecialchars(__('website_meta_desc_ar'), ENT_QUOTES, 'UTF-8'); ?></label>
            <textarea class="form-control" name="seo[meta_description_ar]" rows="2"><?php echo htmlspecialchars((string) ($seo['meta_description_ar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label"><?php echo htmlspecialchars(__('website_canonical'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" name="seo[canonical_url]" value="<?php echo htmlspecialchars((string) ($seo['canonical_url'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label"><?php echo htmlspecialchars(__('website_og_image'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" name="seo[og_image]" value="<?php echo htmlspecialchars((string) ($seo['og_image'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label"><?php echo htmlspecialchars(__('website_twitter_card'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" name="seo[twitter_card]" value="<?php echo htmlspecialchars((string) ($seo['twitter_card'] ?? 'summary_large_image'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label"><?php echo htmlspecialchars(__('website_robots'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input class="form-control" name="seo[robots]" value="<?php echo htmlspecialchars((string) ($seo['robots'] ?? 'index,follow'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-12">
            <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('save') ?: 'Save', ENT_QUOTES, 'UTF-8'); ?></button>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/pages')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('cancel') ?: 'Cancel', ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </form>
</div>
