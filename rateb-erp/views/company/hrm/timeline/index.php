<?php
declare(strict_types=1);
/** @var list<array<string, mixed>> $timeline */
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('hrm_timeline')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <ul class="list-group list-group-flush border rounded">
        <?php foreach (($timeline ?? []) as $ev): ?>
            <li class="list-group-item">
                <div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <?php if (!empty($ev['body'])): ?><div class="small"><?php echo htmlspecialchars((string) $ev['body'], ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
                <div class="small text-muted">
                    <?php echo htmlspecialchars((string) ($ev['entity_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    #<?php echo (int) ($ev['entity_id'] ?? 0); ?>
                    · <?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </li>
        <?php endforeach; ?>
        <?php if (($timeline ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
    </ul>
</div>
