<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('approval_request_create')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars((string) ($action ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 col-lg-8">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input type="text" name="title" required class="form-control">
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('approval_templates'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select name="template_id" class="form-select">
                <option value="">—</option>
                <?php foreach (($templates ?? []) as $t): ?>
                <option value="<?php echo (int) $t['id']; ?>"><?php echo htmlspecialchars((string) ($t['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('notes'), ENT_QUOTES, 'UTF-8'); ?></label>
            <textarea name="notes" class="form-control" rows="3"></textarea>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('approvals/requests')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </form>
</div>
