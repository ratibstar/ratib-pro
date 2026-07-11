<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('crm_customer_profile')), ENT_QUOTES, 'UTF-8'); ?> #<?php echo (int) ($customer_id ?? 0); ?></h1>
    <h2 class="h5"><?php echo htmlspecialchars(__('crm_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <ul class="list-group col-lg-8">
        <?php foreach (($timeline ?? []) as $ev): ?>
            <li class="list-group-item">
                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            </li>
        <?php endforeach; ?>
        <?php if (($timeline ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
    </ul>
</div>
