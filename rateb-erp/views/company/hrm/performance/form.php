<?php
declare(strict_types=1);
/** @var array<string, mixed>|null $item */
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('hrm_performance_create')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars((string) ($action ?? rateb_url(rateb_app_route('hrm/performance'))), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 col-lg-8">
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
                <label class="form-label"><?php echo htmlspecialchars(__('hrm_employees'), ENT_QUOTES, 'UTF-8'); ?></label>
                <select name="employee_profile_id" class="form-select" required>
                    <option value=""></option>
                    <?php foreach (($employees ?? []) as $emp): ?>
                    <option value="<?php echo (int) $emp['id']; ?>"><?php echo htmlspecialchars(trim((string) ($emp['code'] ?? '') . ' — ' . (string) ($emp['first_name'] ?? '') . ' ' . (string) ($emp['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label"><?php echo htmlspecialchars(__('period_start'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="date" name="period_start" class="form-control" value="<?php echo htmlspecialchars((string) ($item['period_start'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label"><?php echo htmlspecialchars(__('period_end'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="date" name="period_end" class="form-control" value="<?php echo htmlspecialchars((string) ($item['period_end'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-12 mb-3">
                <label class="form-label"><?php echo htmlspecialchars(__('summary'), ENT_QUOTES, 'UTF-8'); ?></label>
                <textarea name="summary" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($item['summary'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <div class="col-12 mb-3">
                <label class="form-label"><?php echo htmlspecialchars(__('notes'), ENT_QUOTES, 'UTF-8'); ?></label>
                <textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars((string) ($item['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('hrm/performance')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </form>
</div>
