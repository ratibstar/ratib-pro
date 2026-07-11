<?php
declare(strict_types=1);
/** @var list<array<string,mixed>> $items */
/** @var int $total */
/** @var int $page */
/** @var int $limit */
/** @var bool $canCreate */
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <?php if (!empty($canCreate)): ?>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('payroll/batches')), ENT_QUOTES, 'UTF-8'); ?>" class="card mb-4">
        <?php echo rateb_csrf_field(); ?>
        <div class="card-body row g-3">
            <div class="col-md-4"><label class="form-label"><?php echo htmlspecialchars(__('title'), ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" name="title" required></div>
            <div class="col-md-3"><label class="form-label"><?php echo htmlspecialchars(__('period_start'), ENT_QUOTES, 'UTF-8'); ?></label><input type="date" class="form-control" name="period_start"></div>
            <div class="col-md-3"><label class="form-label"><?php echo htmlspecialchars(__('period_end'), ENT_QUOTES, 'UTF-8'); ?></label><input type="date" class="form-control" name="period_end"></div>
            <div class="col-md-2 d-flex align-items-end"><button type="submit" class="btn btn-primary w-100"><?php echo htmlspecialchars(__('create'), ENT_QUOTES, 'UTF-8'); ?></button></div>
        </div>
    </form>
    <?php endif; ?>
    <div class="table-responsive border rounded">
        <table class="table table-striped mb-0">
            <thead><tr><th><?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('title'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('net'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
            <tbody>
            <?php foreach (($items ?? []) as $row): ?>
                <tr>
                    <td><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('payroll/batches') . '/' . (int) ($row['id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                    <td><?php echo htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(number_format((float) ($row['total_net'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
