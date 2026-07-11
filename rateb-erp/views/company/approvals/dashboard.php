<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('approval_platform')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('approvals/requests/create')), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-plus"></i> <?php echo htmlspecialchars(__('approval_request_create'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="row g-3 mb-4">
        <?php foreach (($board ?? []) as $st => $cnt): ?>
        <div class="col-6 col-md-3 col-xl">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small"><?php echo htmlspecialchars((string) $st, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fs-4 fw-semibold"><?php echo (int) $cnt; ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="row g-3">
        <div class="col-lg-7">
            <h2 class="h5"><?php echo htmlspecialchars(__('approval_pending'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <div class="table-responsive border rounded">
                <table class="table mb-0 align-middle">
                    <thead><tr><th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach (($pending ?? []) as $row): ?>
                        <tr>
                            <td><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('approvals/requests') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['request_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                            <td><?php echo htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars((string) ($row['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (($pending ?? []) === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-5">
            <h2 class="h5"><?php echo htmlspecialchars(__('approval_history'), ENT_QUOTES, 'UTF-8'); ?></h2>
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
