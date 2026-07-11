<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('eam_calendar')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-3"><input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars((string) ($from ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-3"><input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars((string) ($to ?? ''), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-2"><button class="btn btn-outline-secondary w-100" type="submit"><?php echo htmlspecialchars(__('filter'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
    <div class="table-responsive border rounded">
        <table class="table align-middle mb-0">
            <thead><tr><th><?php echo htmlspecialchars(__('date'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
            <tbody>
            <?php foreach (($items ?? []) as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($row['scheduled_start'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eam/work-orders') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['work_order_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                    <td><?php echo htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
