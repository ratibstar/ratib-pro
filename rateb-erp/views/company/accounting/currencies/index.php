<?php
$rows = is_array($rows ?? null) ? $rows : [];
$canCreate = !empty($canCreate);
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($canCreate): ?>
        <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('accounting/currencies/create')), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars(__('accounting_currency_create'), ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
            <thead><tr><th><?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th><th></th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="4" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($row['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($row['is_base'])): ?> <span class="badge bg-secondary">base</span><?php endif; ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($canCreate): ?>
    <hr>
    <h2 class="h6"><?php echo htmlspecialchars(__('accounting_exchange_rate_add'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('accounting/exchange-rates')), ENT_QUOTES, 'UTF-8'); ?>" class="row g-2">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="col-md-2"><input class="form-control" name="from_currency" placeholder="USD" maxlength="3" required></div>
        <div class="col-md-2"><input class="form-control" name="to_currency" placeholder="SAR" maxlength="3" required></div>
        <div class="col-md-2"><input class="form-control" name="rate" type="number" step="0.00000001" min="0" required></div>
        <div class="col-md-2"><input class="form-control" name="rate_date" type="date" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required></div>
        <div class="col-md-2"><button class="btn btn-outline-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
    <?php endif; ?>
</div>
