<?php
/** @var string $pageSlug */
/** @var array<int, array<string, mixed>> $pages */
/** @var array<string, array<string, mixed>> $content */
?>
<div class="rateb-card mb-3">
    <div class="rateb-card-body">
        <form method="get" action="<?php echo rateb_url('admin/cms/page-builder'); ?>" class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label"><?php echo __('cms_page'); ?></label>
                <select name="page" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($pages as $p) {
                        $slug = (string) ($p['slug'] ?? '');
                        ?>
                    <option value="<?php echo Rateb\App\Core\View::escape($slug); ?>"<?php echo $pageSlug === $slug ? ' selected' : ''; ?>>
                        <?php echo Rateb\App\Core\View::escape($slug); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
        </form>
    </div>
</div>

<form method="post" action="<?php echo rateb_url('admin/cms/page-builder/reorder'); ?>" id="cmsPageBuilderForm">
    <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
    <input type="hidden" name="page_slug" value="<?php echo Rateb\App\Core\View::escape($pageSlug); ?>">
    <div id="cmsSectionsSort" class="d-flex flex-column gap-3">
        <?php foreach ($content as $key => $block) {
            $section = $block['section'] ?? [];
            $sid = (int) ($section['id'] ?? 0);
            ?>
        <div class="rateb-card cms-pb-section" data-section-id="<?php echo $sid; ?>">
            <div class="rateb-card-header d-flex align-items-center gap-2">
                <span class="cms-pb-handle text-muted"><i class="fas fa-grip-vertical"></i></span>
                <strong><?php echo Rateb\App\Core\View::escape($key); ?></strong>
                <span class="text-muted small"><?php echo Rateb\App\Core\View::escape((string) ($section['title_en'] ?? '')); ?></span>
                <input type="hidden" name="sections[]" value="<?php echo $sid; ?>">
            </div>
            <div class="rateb-card-body">
                <ul class="list-group cms-pb-blocks" data-section="<?php echo $sid; ?>">
                    <?php foreach ($block['blocks'] ?? [] as $b) {
                        $bid = (int) ($b['id'] ?? 0);
                        ?>
                    <li class="list-group-item d-flex align-items-center gap-2 cms-pb-block" data-block-id="<?php echo $bid; ?>">
                        <span class="cms-pb-handle text-muted"><i class="fas fa-grip-lines"></i></span>
                        <span><?php echo Rateb\App\Core\View::escape((string) ($b['title_en'] ?? $b['block_type'] ?? '')); ?></span>
                        <input type="hidden" name="blocks[]" value="<?php echo $bid; ?>">
                    </li>
                    <?php } ?>
                </ul>
            </div>
        </div>
        <?php } ?>
    </div>
    <div class="mt-3">
        <button type="submit" class="btn btn-primary"><?php echo __('cms_save_order'); ?></button>
        <a href="<?php echo rateb_url('admin/cms/sections'); ?>" class="btn btn-outline-secondary"><?php echo __('cms_sections'); ?></a>
        <a href="<?php echo rateb_url('admin/cms/blocks'); ?>" class="btn btn-outline-secondary"><?php echo __('cms_blocks'); ?></a>
    </div>
</form>
