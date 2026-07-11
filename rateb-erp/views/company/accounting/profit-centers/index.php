<?php
$rows = is_array($rows ?? null) ? $rows : [];
$canCreate = !empty($canCreate);
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($canCreate): ?>
        <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('accounting/profit-centers/create')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('accounting_profit_center_create'), ENT_QUOTES, 'UTF-8'); ?></a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-striped">
            <thead><tr><th><?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?><tr><td colspan="3" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($row['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
