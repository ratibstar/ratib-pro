<?php
declare(strict_types=1);
/** @var array<string, mixed>|null $item */
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('hrm_training_create')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars((string) ($action ?? rateb_url(rateb_app_route('hrm/training'))), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 col-lg-8">
        <?php if (function_exists('csrf_field')): ?>
            <?php echo csrf_field(); ?>
        <?php elseif (isset($csrf)): ?>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <?php else: ?>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <div class="row g-2">
            <div class="col-md-4 mb-3">
                <label class="form-label"><?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars((string) ($item['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-8 mb-3">
                <label class="form-label"><?php echo htmlspecialchars(__('title'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="text" name="title" required class="form-control" value="<?php echo htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label"><?php echo htmlspecialchars(__('provider'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="text" name="provider" class="form-control" value="<?php echo htmlspecialchars((string) ($item['provider'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label"><?php echo htmlspecialchars(__('planned_start'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="date" name="planned_start" class="form-control" value="<?php echo htmlspecialchars((string) ($item['planned_start'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label"><?php echo htmlspecialchars(__('planned_end'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="date" name="planned_end" class="form-control" value="<?php echo htmlspecialchars((string) ($item['planned_end'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label"><?php echo htmlspecialchars(__('hours'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="number" step="0.01" name="hours" class="form-control" value="<?php echo htmlspecialchars((string) ($item['hours'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label"><?php echo htmlspecialchars(__('capacity'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="number" name="capacity" class="form-control" value="<?php echo htmlspecialchars((string) ($item['capacity'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-12 mb-3">
                <label class="form-label"><?php echo htmlspecialchars(__('notes'), ENT_QUOTES, 'UTF-8'); ?></label>
                <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($item['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/training')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </form>
</div>
