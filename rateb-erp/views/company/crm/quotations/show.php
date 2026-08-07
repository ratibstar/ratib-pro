<?php
declare(strict_types=1);
$item = $item ?? [];
$lines = $lines ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?php echo htmlspecialchars((string) ($item['quotation_no'] ?? ($title ?? '')), ENT_QUOTES, 'UTF-8'); ?></h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/quotations')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('back'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="card mb-3">
        <div class="card-body row g-2">
            <div class="col-md-6"><strong><?php echo htmlspecialchars(__('title'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="col-md-3"><strong><?php echo htmlspecialchars(__('status'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="col-md-3"><strong><?php echo htmlspecialchars(__('amount'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['total_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) ($item['currency_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="col-md-3"><strong><?php echo htmlspecialchars(__('crm_lead_id'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo (int) ($item['lead_id'] ?? 0); ?></div>
            <div class="col-md-3"><strong><?php echo htmlspecialchars(__('crm_opportunity_id'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo (int) ($item['opportunity_id'] ?? 0); ?></div>
            <div class="col-md-3"><strong><?php echo htmlspecialchars(__('customer_id'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo (int) ($item['customer_id'] ?? 0); ?></div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped">
            <thead><tr><th>#</th><th><?php echo htmlspecialchars(__('item'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('quantity'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('unit_price'), ENT_QUOTES, 'UTF-8'); ?></th><th><?php echo htmlspecialchars(__('amount'), ENT_QUOTES, 'UTF-8'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($lines as $line): ?>
                <tr>
                    <td><?php echo (int) ($line['line_no'] ?? 0); ?></td>
                    <td><?php echo htmlspecialchars((string) ($line['item_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($line['quantity'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($line['unit_price'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars((string) ($line['line_total'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($lines === []): ?><tr><td colspan="5" class="text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
