<?php
declare(strict_types=1);
/** @var array<string, mixed>|null $item */
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('eproc_contract_create')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars((string) ($action ?? rateb_url(rateb_app_route('eproc/contracts'))), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 col-lg-8">
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
            <input type="text" name="title" required class="form-control" value="<?php echo htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('profile_id'), ENT_QUOTES, 'UTF-8'); ?></label>
            <input type="number" name="profile_id" class="form-control" min="1" value="<?php echo htmlspecialchars((string) ($item['profile_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <label class="form-label"><?php echo htmlspecialchars(__('starts_at'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="date" name="starts_at" class="form-control" value="<?php echo htmlspecialchars((string) ($item['starts_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo htmlspecialchars(__('ends_at'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="date" name="ends_at" class="form-control" value="<?php echo htmlspecialchars((string) ($item['ends_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <div class="row g-2 mb-3">
            <div class="col-md-6">
                <label class="form-label"><?php echo htmlspecialchars(__('amount'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="number" step="0.01" name="value_amount" class="form-control" value="<?php echo htmlspecialchars((string) ($item['value_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label"><?php echo htmlspecialchars(__('currency'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="text" name="currency_code" class="form-control" maxlength="3" value="<?php echo htmlspecialchars((string) ($item['currency_code'] ?? 'SAR'), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label"><?php echo htmlspecialchars(__('notes'), ENT_QUOTES, 'UTF-8'); ?></label>
            <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars((string) ($item['notes'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/contracts')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
    </form>
</div>
