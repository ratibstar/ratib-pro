<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('eam_reports')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted small"><?php echo htmlspecialchars(__('eam_assets'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4 fw-semibold"><?php echo (int) ($assetTotal ?? 0); ?></div></div></div>
        <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted small"><?php echo htmlspecialchars(__('eam_requests'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4 fw-semibold"><?php echo (int) ($requestTotal ?? 0); ?></div></div></div>
        <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted small"><?php echo htmlspecialchars(__('eam_work_orders'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fs-4 fw-semibold"><?php echo (int) ($workOrderTotal ?? 0); ?></div></div></div>
    </div>
    <h2 class="h5"><?php echo htmlspecialchars(__('eam_assets'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <div class="row g-2 mb-4">
        <?php foreach (($assetBoard ?? []) as $st => $cnt): ?>
        <div class="col-6 col-md-3"><div class="border rounded p-2"><span class="text-muted small"><?php echo htmlspecialchars((string) $st, ENT_QUOTES, 'UTF-8'); ?></span><div class="fw-semibold"><?php echo (int) $cnt; ?></div></div></div>
        <?php endforeach; ?>
    </div>
    <h2 class="h5"><?php echo htmlspecialchars(__('eam_requests'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <div class="row g-2">
        <?php foreach (($requestBoard ?? []) as $st => $cnt): ?>
        <div class="col-6 col-md-3"><div class="border rounded p-2"><span class="text-muted small"><?php echo htmlspecialchars((string) $st, ENT_QUOTES, 'UTF-8'); ?></span><div class="fw-semibold"><?php echo (int) $cnt; ?></div></div></div>
        <?php endforeach; ?>
    </div>
</div>
