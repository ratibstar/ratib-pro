<?php
declare(strict_types=1);
/** @var array<string, mixed>|null $item */
/** @var list<array<string, mixed>> $categories */
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('eproc_supplier_create')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars((string) ($action ?? rateb_url(rateb_app_route('eproc/suppliers'))), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 col-lg-8">
        <?php if (function_exists('csrf_field')): ?>
            <?php echo csrf_field(); ?>
        <?php elseif (isset($csrf)): ?>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <?php else: ?>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input type="text" name="code" class="form-control" value="<?php echo htmlspecialchars((string) ($item['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input type="text" name="name" required class="form-control" value="<?php echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('name_ar'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input type="text" name="name_ar" class="form-control" value="<?php echo htmlspecialchars((string) ($item['name_ar'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('email'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars((string) ($item['email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('phone'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars((string) ($item['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('risk_level'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select name="risk_level" class="form-select">
                <?php foreach (['low', 'medium', 'high', 'critical'] as $lvl): ?>
                <option value="<?php echo htmlspecialchars($lvl, ENT_QUOTES, 'UTF-8'); ?>" <?php echo (($item['risk_level'] ?? 'medium') === $lvl) ? 'selected' : ''; ?>><?php echo htmlspecialchars($lvl, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('eproc_categories'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select name="category_id" class="form-select">
                <option value="">—</option>
                <?php foreach (($categories ?? []) as $cat): ?>
                <option value="<?php echo (int) $cat['id']; ?>" <?php echo ((int) ($item['category_id'] ?? 0) === (int) $cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($cat['name'] ?? $cat['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('notes'), ENT_QUOTES, 'UTF-8'); ?></label>
            <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($item['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/suppliers')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </form>
</div>
