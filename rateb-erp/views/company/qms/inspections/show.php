<?php
declare(strict_types=1);
/** @var array<string,mixed> $item */
/** @var list<array<string,mixed>> $timeline */
/** @var list<string> $transitions */
/** @var bool $canTransition */
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small"><?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fw-semibold"><?php echo htmlspecialchars((string) ($item['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small"><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></div><div class="fw-semibold"><?php echo htmlspecialchars((string) ($item['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">UUID</div><div class="fw-semibold small"><?php echo htmlspecialchars((string) ($item['public_uuid'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></div></div>
        <div class="col-md-3"><div class="border rounded p-3"><div class="text-muted small">version</div><div class="fw-semibold"><?php echo (int) ($item['version'] ?? 1); ?></div></div></div>
    </div>
    <?php if (!empty($canTransition) && ($transitions ?? []) !== []): ?>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('qms/inspections') . '/' . (int) ($item['id'] ?? 0) . '/transition'), ENT_QUOTES, 'UTF-8'); ?>" class="card mb-4">
        <?php echo rateb_csrf_field(); ?>
        <input type="hidden" name="expected_version" value="<?php echo (int) ($item['version'] ?? 1); ?>">
        <div class="card-body row g-3">
            <div class="col-md-4"><label class="form-label"><?php echo htmlspecialchars(__('workflow_transition'), ENT_QUOTES, 'UTF-8'); ?></label>
                <select class="form-select" name="to_status">
                    <?php foreach (($transitions ?? []) as $st): ?><option value="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><label class="form-label"><?php echo htmlspecialchars(__('reason'), ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" name="reason"></div>
            <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button></div>
        </div>
    </form>
    <?php endif; ?>
    <h2 class="h5"><?php echo htmlspecialchars(__('quality_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <ul class="list-group list-group-flush border rounded">
        <?php foreach (($timeline ?? []) as $ev): ?>
            <li class="list-group-item"><div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div><div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div></li>
        <?php endforeach; ?>
        <?php if (($timeline ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
    </ul>
</div>
