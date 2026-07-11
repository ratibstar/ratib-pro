<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $recent */
/** @var int $total */
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? __('recruitment')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('recruitment/candidates/create')), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas fa-user-plus"></i> <?php echo htmlspecialchars(__('recruitment_candidate_create'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="border rounded p-3">
                <div class="text-muted small"><?php echo htmlspecialchars(__('recruitment_candidates'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fs-3 fw-semibold"><?php echo (int) $total; ?></div>
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $row): ?>
                <tr>
                    <td><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('recruitment/candidates') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars((string) ($row['candidate_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                    <td><?php echo htmlspecialchars((string) ($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($recent === []): ?>
                <tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
