<?php
declare(strict_types=1);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('eam_assignments')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="table-responsive border rounded">
        <table class="table align-middle mb-0">
            <thead><tr><th>Asset</th><th>User</th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('date'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
            <tbody>
            <?php foreach (($items ?? []) as $row): ?>
                <tr>
                    <td><a href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eam/assets') . '/' . (int) ($row['asset_id'] ?? 0)), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) ($row['asset_id'] ?? 0); ?></a></td>
                    <td><?php echo (int) ($row['assignee_user_id'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['assigned_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?><tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
