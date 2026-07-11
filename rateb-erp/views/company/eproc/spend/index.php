<?php
declare(strict_types=1);
/** @var array<string, mixed> $summary */
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? __('eproc_spend')), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small"><?php echo htmlspecialchars(__('total'), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fs-4 fw-semibold"><?php echo htmlspecialchars(number_format((float) ($summary['snapshots_total'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
        <?php foreach (($summary['snapshots_by_period'] ?? []) as $periodRow): ?>
        <div class="col-6 col-md-3 col-xl">
            <div class="border rounded p-3 h-100">
                <div class="text-muted small"><?php echo htmlspecialchars((string) ($periodRow['period_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="fs-5 fw-semibold"><?php echo htmlspecialchars(number_format((float) ($periodRow['total'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if (!empty($canCreate)): ?>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('eproc/spend')), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 col-lg-8 mb-4">
        <?php if (function_exists('csrf_field')): ?>
            <?php echo csrf_field(); ?>
        <?php elseif (isset($csrf)): ?>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string) $csrf, ENT_QUOTES, 'UTF-8'); ?>">
        <?php else: ?>
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <?php endif; ?>
        <div class="row g-2">
            <div class="col-md-3">
                <label class="form-label"><?php echo htmlspecialchars(__('period'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="text" name="period_label" required class="form-control" placeholder="2026-Q1">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo htmlspecialchars(__('category'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="text" name="category_key" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo htmlspecialchars(__('amount'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="number" step="0.01" name="amount" required class="form-control" value="0">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo htmlspecialchars(__('currency'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input type="text" name="currency_code" class="form-control" maxlength="3" value="SAR">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            </div>
        </div>
    </form>
    <?php endif; ?>
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-4"><input type="text" name="period" value="<?php echo htmlspecialchars((string) ($period ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="<?php echo htmlspecialchars(__('period'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-md-2"><button class="btn btn-outline-secondary w-100" type="submit"><?php echo htmlspecialchars(__('filter'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
    <div class="table-responsive border rounded">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?php echo htmlspecialchars(__('period'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('category'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('amount'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('currency'), ENT_QUOTES, 'UTF-8'); ?></th>
                    <th><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (($items ?? []) as $row): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string) ($row['period_label'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['category_key'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars(number_format((float) ($row['amount'] ?? 0), 2), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['currency_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (($items ?? []) === []): ?><tr><td colspan="5" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
