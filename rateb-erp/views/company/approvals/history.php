<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('approval_history')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('approval_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group border rounded">
                <?php foreach (($timeline ?? []) as $ev): ?>
                    <li class="list-group-item"><div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div><div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></li>
                <?php endforeach; ?>
                <?php if (($timeline ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('approval_audit'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group border rounded">
                <?php foreach (($audit ?? []) as $ev): ?>
                    <li class="list-group-item"><div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['message'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div><div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></li>
                <?php endforeach; ?>
                <?php if (($audit ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
