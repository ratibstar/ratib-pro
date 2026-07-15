<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $pages */
/** @var int $companyId */
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars(rateb_asset('css/website-builder.css'), ENT_QUOTES, 'UTF-8'); ?>">
<div class="container-fluid py-3 wb-admin">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? 'Website'), ENT_QUOTES, 'UTF-8'); ?></h1>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/builder')), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-table-columns"></i> <?php echo htmlspecialchars(__('website_builder') ?: 'Builder', ENT_QUOTES, 'UTF-8'); ?></a>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/pages')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('website_pages') ?: 'Pages', ENT_QUOTES, 'UTF-8'); ?></a>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/theme')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('website_theme') ?: 'Theme', ENT_QUOTES, 'UTF-8'); ?></a>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/media')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('website_media') ?: 'Media', ENT_QUOTES, 'UTF-8'); ?></a>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/menus')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('website_menus') ?: 'Menus', ENT_QUOTES, 'UTF-8'); ?></a>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/forms')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('website_forms') ?: 'Forms', ENT_QUOTES, 'UTF-8'); ?></a>
            <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars(rateb_url('site'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars(__('cms_preview') ?: 'Live site', ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </div>
    <p class="text-muted"><?php echo htmlspecialchars(__('website_dashboard_hint') ?: 'Build your agency website. Tenant company #' . (int) $companyId, ENT_QUOTES, 'UTF-8'); ?></p>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead><tr><th><?php echo htmlspecialchars(__('slug') ?: 'Slug', ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('title') ?: 'Title', ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status') ?: 'Status', ENT_QUOTES, 'UTF-8'); ?></th><th></th></tr></thead>
            <tbody>
            <?php foreach (($pages ?? []) as $p) { ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($p['slug'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($p['title_en'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($p['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('website/builder') . '?page_id=' . (int) ($p['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('website_builder') ?: 'Builder', ENT_QUOTES, 'UTF-8'); ?></a>
                    </td>
                </tr>
            <?php } ?>
            <?php if (($pages ?? []) === []) { ?>
                <tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records') ?: 'No pages yet', ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
