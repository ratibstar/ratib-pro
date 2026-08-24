<?php
declare(strict_types=1);

/** @var array<string,mixed>|null $article */
/** @var list<array<string,mixed>> $modules */
/** @var string $action */
/** @var string $csrf */
/** @var string $helpHomeUrl */

use Rateb\App\Core\View;

$a = is_array($article) ? $article : [];
$bodyAr = json_decode((string) ($a['body_json_ar'] ?? '{}'), true);
$bodyEn = json_decode((string) ($a['body_json_en'] ?? '{}'), true);
if (!is_array($bodyAr)) {
    $bodyAr = [];
}
if (!is_array($bodyEn)) {
    $bodyEn = [];
}
$kw = json_decode((string) ($a['keywords_json'] ?? '[]'), true);
$rel = json_decode((string) ($a['related_json'] ?? '[]'), true);
$stepsAr = implode("\n", array_map('strval', is_array($bodyAr['steps'] ?? null) ? $bodyAr['steps'] : []));
$stepsEn = implode("\n", array_map('strval', is_array($bodyEn['steps'] ?? null) ? $bodyEn['steps'] : []));
?>
<link rel="stylesheet" href="<?php echo rateb_asset('css/help-center.css'); ?>">
<div class="hc-page">
    <?php View::partial('help/breadcrumb', [
        'crumbs' => [
            ['label' => __('help_center'), 'url' => $helpHomeUrl],
            ['label' => __('help_admin_title'), 'url' => rateb_url('admin/help/manage')],
            ['label' => (string) ($title ?? ''), 'url' => null],
        ],
    ]); ?>

    <form method="post" action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" class="hc-panel">
        <input type="hidden" name="_csrf" value="<?php echo View::escape($csrf); ?>">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label"><?php echo View::escape(__('help_admin_slug')); ?></label>
                <input class="form-control" name="slug" required value="<?php echo View::escape((string) ($a['slug'] ?? '')); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo View::escape(__('help_admin_module')); ?></label>
                <select class="form-select" name="module_slug">
                    <?php foreach ($modules as $m) { ?>
                    <option value="<?php echo View::escape((string) ($m['slug'] ?? '')); ?>"<?php echo ((string) ($a['module_slug'] ?? '')) === (string) ($m['slug'] ?? '') ? ' selected' : ''; ?>>
                        <?php echo View::escape((string) ($m['title_ar'] ?? $m['slug'] ?? '')); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo View::escape(__('status')); ?></label>
                <select class="form-select" name="status">
                    <?php foreach (['draft', 'published', 'archived'] as $st) { ?>
                    <option value="<?php echo $st; ?>"<?php echo ((string) ($a['status'] ?? 'draft')) === $st ? ' selected' : ''; ?>><?php echo $st; ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo View::escape(__('help_admin_title_ar')); ?></label>
                <input class="form-control" name="title_ar" required value="<?php echo View::escape((string) ($a['title_ar'] ?? '')); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo View::escape(__('help_admin_title_en')); ?></label>
                <input class="form-control" name="title_en" value="<?php echo View::escape((string) ($a['title_en'] ?? '')); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo View::escape(__('help_admin_summary_ar')); ?></label>
                <textarea class="form-control" name="summary_ar" rows="2"><?php echo View::escape((string) ($a['summary_ar'] ?? '')); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo View::escape(__('help_admin_summary_en')); ?></label>
                <textarea class="form-control" name="summary_en" rows="2"><?php echo View::escape((string) ($a['summary_en'] ?? '')); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo View::escape(__('help_what')); ?> (AR)</label>
                <textarea class="form-control" name="what_ar" rows="2"><?php echo View::escape((string) ($bodyAr['what'] ?? '')); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo View::escape(__('help_what')); ?> (EN)</label>
                <textarea class="form-control" name="what_en" rows="2"><?php echo View::escape((string) ($bodyEn['what'] ?? '')); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo View::escape(__('help_when')); ?> (AR)</label>
                <textarea class="form-control" name="when_ar" rows="2"><?php echo View::escape((string) ($bodyAr['when'] ?? '')); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo View::escape(__('help_when')); ?> (EN)</label>
                <textarea class="form-control" name="when_en" rows="2"><?php echo View::escape((string) ($bodyEn['when'] ?? '')); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo View::escape(__('help_steps')); ?> (AR)</label>
                <textarea class="form-control" name="steps_ar" rows="4" placeholder="سطر لكل خطوة"><?php echo View::escape($stepsAr); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo View::escape(__('help_steps')); ?> (EN)</label>
                <textarea class="form-control" name="steps_en" rows="4" placeholder="One step per line"><?php echo View::escape($stepsEn); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo View::escape(__('help_example')); ?> (AR)</label>
                <textarea class="form-control" name="example_ar" rows="2"><?php echo View::escape((string) ($bodyAr['example'] ?? '')); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo View::escape(__('help_example')); ?> (EN)</label>
                <textarea class="form-control" name="example_en" rows="2"><?php echo View::escape((string) ($bodyEn['example'] ?? '')); ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo View::escape(__('help_admin_keywords')); ?></label>
                <input class="form-control" name="keywords" value="<?php echo View::escape(is_array($kw) ? implode(', ', $kw) : ''); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo View::escape(__('help_related')); ?></label>
                <input class="form-control" name="related" value="<?php echo View::escape(is_array($rel) ? implode(', ', $rel) : ''); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label"><?php echo View::escape(__('help_admin_route')); ?></label>
                <input class="form-control" name="route_hint" value="<?php echo View::escape((string) ($a['route_hint'] ?? '')); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo View::escape(__('help_difficulty_beginner')); ?></label>
                <select class="form-select" name="difficulty">
                    <?php foreach (['beginner', 'intermediate', 'advanced'] as $d) { ?>
                    <option value="<?php echo $d; ?>"<?php echo ((string) ($a['difficulty'] ?? 'beginner')) === $d ? ' selected' : ''; ?>><?php echo $d; ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo View::escape(__('help_minutes')); ?></label>
                <input class="form-control" type="number" min="1" max="60" name="minutes" value="<?php echo (int) ($a['minutes'] ?? 3); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Icon</label>
                <input class="form-control" name="icon" value="<?php echo View::escape((string) ($a['icon'] ?? 'fa-circle-question')); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo View::escape(__('permissions')); ?></label>
                <select class="form-select" name="audience">
                    <?php foreach (['all', 'user', 'manager', 'admin'] as $aud) { ?>
                    <option value="<?php echo $aud; ?>"<?php echo ((string) ($a['audience'] ?? 'all')) === $aud ? ' selected' : ''; ?>><?php echo $aud; ?></option>
                    <?php } ?>
                </select>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?php echo View::escape(__('save')); ?></button>
            <a class="btn btn-outline-secondary" href="<?php echo rateb_url('admin/help/manage'); ?>"><?php echo View::escape(__('cancel')); ?></a>
        </div>
    </form>
</div>
