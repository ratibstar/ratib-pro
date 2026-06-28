<?php
/** @var array<string, mixed>|null $item */
/** @var array<int, array<string, mixed>> $segments */
$id = (int) ($item['id'] ?? 0);
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo Rateb\App\Core\View::escape($title ?? ''); ?></div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_url('admin/cms/newsletter/campaign/save'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <?php if ($id > 0) { ?><input type="hidden" name="id" value="<?php echo $id; ?>"><?php } ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('subject_en'); ?></label>
                    <input class="form-control" name="subject_en" value="<?php echo Rateb\App\Core\View::escape((string) ($item['subject_en'] ?? '')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('subject_ar'); ?></label>
                    <input class="form-control" name="subject_ar" value="<?php echo Rateb\App\Core\View::escape((string) ($item['subject_ar'] ?? '')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('cms_segment'); ?></label>
                    <select class="form-select" name="segment_slug">
                        <option value="all">all</option>
                        <?php foreach ($segments as $seg) {
                            $slug = (string) ($seg['slug'] ?? '');
                            ?>
                        <option value="<?php echo Rateb\App\Core\View::escape($slug); ?>"<?php echo ($item['segment_slug'] ?? 'general') === $slug ? ' selected' : ''; ?>>
                            <?php echo Rateb\App\Core\View::escape($slug); ?>
                        </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label"><?php echo __('status'); ?></label>
                    <select class="form-select" name="status">
                        <?php foreach (['draft', 'scheduled', 'sent'] as $st) { ?>
                        <option value="<?php echo $st; ?>"<?php echo ($item['status'] ?? 'draft') === $st ? ' selected' : ''; ?>><?php echo __($st); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label class="form-label"><?php echo __('content_en'); ?></label>
                    <textarea class="form-control rateb-cms-wysiwyg" name="body_html_en" rows="8"><?php echo Rateb\App\Core\View::escape((string) ($item['body_html_en'] ?? '')); ?></textarea>
                </div>
                <div class="col-md-12">
                    <label class="form-label"><?php echo __('content_ar'); ?></label>
                    <textarea class="form-control rateb-cms-wysiwyg" name="body_html_ar" rows="8"><?php echo Rateb\App\Core\View::escape((string) ($item['body_html_ar'] ?? '')); ?></textarea>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
                <a href="<?php echo rateb_url('admin/cms/newsletter'); ?>" class="btn btn-outline-secondary"><?php echo __('cancel'); ?></a>
            </div>
        </form>
    </div>
</div>
