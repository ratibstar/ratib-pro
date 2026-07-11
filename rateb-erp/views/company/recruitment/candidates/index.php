<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $items */
/** @var int $total */
/** @var int $page */
/** @var int $limit */
/** @var string $q */
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($canCreate)): ?>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('recruitment/candidates/create')), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars(__('create'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <?php endif; ?>
    </div>
    <form class="row g-2 mb-3" method="get" action="">
        <div class="col-md-6">
            <input type="search" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="<?php echo htmlspecialchars(__('search'), ENT_QUOTES, 'UTF-8'); ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-secondary"><?php echo htmlspecialchars(__('search'), ENT_QUOTES, 'UTF-8'); ?></button>
        </div>
    </form>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('phone'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($row['candidate_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['full_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="badge text-bg-secondary"><?php echo htmlspecialchars((string) ($row['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('recruitment/candidates') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars(__('view'), ENT_QUOTES, 'UTF-8'); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
                <tr><td colspan="5" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="text-muted small"><?php echo (int) $total; ?> <?php echo htmlspecialchars(__('records'), ENT_QUOTES, 'UTF-8'); ?></p>
</div>
