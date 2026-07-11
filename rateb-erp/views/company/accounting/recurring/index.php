<?php $canCreate = !empty($canCreate); ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($canCreate): ?>
        <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('accounting/recurring/create')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('accounting_recurring_create'), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endif; ?>
    </div>
    <p class="text-muted"><?php echo htmlspecialchars(__('accounting_recurring_hint'), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
