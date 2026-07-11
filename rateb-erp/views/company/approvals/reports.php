<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('approval_reports')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="mb-3"><div class="border rounded p-3 col-md-4"><div class="text-muted small"><?php echo htmlspecialchars(__('approval_requests'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4 fw-semibold"><?php echo (int) ($total ?? 0); ?></div></div></div>
    <div class="row g-2">
        <?php foreach (($board ?? []) as $st => $cnt): ?>
        <div class="col-6 col-md-3"><div class="border rounded p-2"><span class="text-muted small"><?php echo htmlspecialchars((string) $st, ENT_QUOTES, 'UTF-8'); ?></span><div class="fw-semibold"><?php echo (int) $cnt; ?></div></div></div>
        <?php endforeach; ?>
    </div>
</div>
