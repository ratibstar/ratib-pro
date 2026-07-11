<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('project_reports')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="row g-3 mb-4">
        <?php foreach (($board ?? []) as $st => $cnt): ?>
        <div class="col-6 col-md-3">
            <div class="border rounded p-3">
                <div class="text-muted small"><?php echo htmlspecialchars((string) $st, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fs-4 fw-semibold"><?php echo (int) $cnt; ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <h2 class="h5"><?php echo htmlspecialchars(__('project_tasks'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <div class="row g-3">
        <?php foreach (($taskStatuses ?? []) as $st): ?>
        <div class="col-md-4 col-lg-2">
            <div class="border rounded p-3">
                <div class="text-muted small"><?php echo htmlspecialchars((string) $st, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fs-5"><?php echo count($taskBoard[$st] ?? []); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
