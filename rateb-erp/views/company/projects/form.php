<?php
declare(strict_types=1);
/** @var array<string,mixed>|null $item */
$isEdit = is_array($item);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('project_create')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars((string) ($action ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3" style="max-width:720px">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <?php if ($isEdit): ?>
        <input type="hidden" name="expected_version" value="<?php echo (int) ($item['version'] ?? 1); ?>">
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input type="text" name="name" required class="form-control" value="<?php echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('name_ar'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input type="text" name="name_ar" class="form-control" value="<?php echo htmlspecialchars((string) ($item['name_ar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="row g-3 mb-3">
            <div class="col-md-6">
                <label class="form-label"><?php echo htmlspecialchars(__('planned_start'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="date" name="planned_start" class="form-control" value="<?php echo htmlspecialchars((string) ($item['planned_start'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo htmlspecialchars(__('planned_end'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="date" name="planned_end" class="form-control" value="<?php echo htmlspecialchars((string) ($item['planned_end'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('notes'), ENT_QUOTES, 'UTF-8'); ?></label>
            <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($item['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('projects')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </form>
</div>
