<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($canCreate)): ?>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/opportunities/create')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('create'), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-striped"><thead><tr><th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('amount'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
        <tbody>
        <?php foreach (($items ?? []) as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars((string) ($row['opportunity_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($row['amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars((string) ($row['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (($items ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
        </tbody></table>
    </div>
</div>
