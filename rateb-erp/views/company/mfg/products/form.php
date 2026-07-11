<?php
declare(strict_types=1);
/** @var array<string, mixed>|null $item */
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('mfg_product_create')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars((string) ($action ?? rateb_url(rateb_app_route('mfg/products'))), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 col-lg-8">
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
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('mfg/products')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </form>
</div>
