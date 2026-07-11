<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('approval_pending')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="table-responsive border rounded">
        <table class="table align-middle mb-0">
            <thead><tr><th><?php echo htmlspecialchars(__('record_id'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('priority'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
            <tbody>
            <?php foreach (($items ?? []) as $row): ?>
                <tr>
                    <td><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('approvals/requests') . '/' . (int) $row['id']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars((string) ($row['request_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></a></td>
                    <td><?php echo htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['priority'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
