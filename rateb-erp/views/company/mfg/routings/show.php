<?php
declare(strict_types=1);
/** @var array<string, mixed> $item */
/** @var list<array<string, mixed>> $operations */
/** @var list<array<string, mixed>> $timeline */
/** @var list<string> $transitions */
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?php echo htmlspecialchars((string) ($item['name'] ?? $item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="text-muted"><?php echo htmlspecialchars((string) ($item['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <span class="badge text-bg-secondary"><?php echo htmlspecialchars((string) ($item['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></div>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('mfg/routings')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('back'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="border rounded p-3 mb-3">
                <div><strong><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><strong><?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div><strong>product_id:</strong> <?php echo (int) ($item['product_id'] ?? 0) ?: '—'; ?></div>
            </div>
            <h2 class="h5">operations</h2>
            <div class="table-responsive border rounded mb-3">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>seq</th>
                            <th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th>
                            <th>work_center_id</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach (($operations ?? []) as $op): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string) ($op['sequence_no'] ?? $op['seq'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($op['name'] ?? $op['operation_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) ($op['work_center_id'] ?? 0); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($operations ?? []) === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-4">
            <h2 class="h6"><?php echo htmlspecialchars(__('mfg_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group list-group-flush border rounded">
                <?php foreach (($timeline ?? []) as $ev): ?>
                    <li class="list-group-item">
                        <div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    </li>
                <?php endforeach; ?>
                <?php if (($timeline ?? []) === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
