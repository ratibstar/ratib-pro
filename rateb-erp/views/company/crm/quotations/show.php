<?php
declare(strict_types=1);
$item = $item ?? [];
$lines = $lines ?? [];
$transitions = $transitions ?? [];
$timeline = $timeline ?? [];
$history = $history ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1"><?php echo htmlspecialchars((string) ($item['quotation_no'] ?? ($title ?? '')), ENT_QUOTES, 'UTF-8'); ?></h1>
            <span class="badge text-bg-secondary"><?php echo htmlspecialchars((string) ($item['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/quotations')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('back'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="card mb-3">
        <div class="card-body row g-2">
            <div class="col-md-6"><strong><?php echo htmlspecialchars(__('title'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="col-md-3"><strong><?php echo htmlspecialchars(__('amount'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['total_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) ($item['currency_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="col-md-3"><strong><?php echo htmlspecialchars(__('crm_lead_id'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo (int) ($item['lead_id'] ?? 0); ?></div>
            <div class="col-md-3"><strong><?php echo htmlspecialchars(__('crm_opportunity_id'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo (int) ($item['opportunity_id'] ?? 0); ?></div>
            <div class="col-md-3"><strong><?php echo htmlspecialchars(__('customer_id'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo (int) ($item['customer_id'] ?? 0); ?></div>
        </div>
    </div>

    <?php if (!empty($canWorkflow) && $transitions !== []): ?>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/quotations') . '/' . (int) $item['id'] . '/transition'), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-3">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <label class="form-label"><?php echo htmlspecialchars(__('crm_quotation_transition'), ENT_QUOTES, 'UTF-8'); ?></label>
        <div class="input-group">
            <select class="form-select" name="to_status" required>
                <?php foreach ($transitions as $t): ?><option value="<?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($t, ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?>
            </select>
            <input class="form-control" name="reason" placeholder="<?php echo htmlspecialchars(__('notes'), ENT_QUOTES, 'UTF-8'); ?>">
            <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('apply'), ENT_QUOTES, 'UTF-8'); ?></button>
        </div>
    </form>
    <?php endif; ?>

    <?php if (!empty($canConvertCustomer)): ?>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/quotations') . '/' . (int) $item['id'] . '/convert-customer'), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-3">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="d-flex flex-wrap gap-2 align-items-end">
            <div>
                <label class="form-label"><?php echo htmlspecialchars(__('crm_convert_to_customer'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input class="form-control" name="name" value="<?php echo htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <button class="btn btn-success" type="submit"><?php echo htmlspecialchars(__('crm_convert_to_customer'), ENT_QUOTES, 'UTF-8'); ?></button>
        </div>
        <div class="form-text"><?php echo htmlspecialchars(__('crm_no_invoice_phase2'), ENT_QUOTES, 'UTF-8'); ?></div>
    </form>
    <?php endif; ?>

    <div class="table-responsive mb-4">
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

    <div class="row g-3">
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_timeline'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach ($timeline as $ev): ?>
                <li class="list-group-item">
                    <div class="fw-semibold"><?php echo htmlspecialchars((string) ($ev['title'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars((string) ($ev['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if ($timeline === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
        <div class="col-lg-6">
            <h2 class="h5"><?php echo htmlspecialchars(__('crm_status_history'), ENT_QUOTES, 'UTF-8'); ?></h2>
            <ul class="list-group">
                <?php foreach ($history as $h): ?>
                <li class="list-group-item">
                    <div><?php echo htmlspecialchars((string) (($h['from_status'] ?? '') . ' → ' . ($h['to_status'] ?? '')), ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="small text-muted"><?php echo htmlspecialchars((string) ($h['created_at'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                </li>
                <?php endforeach; ?>
                <?php if ($history === []): ?><li class="list-group-item text-muted"><?php echo htmlspecialchars(__('no_records'), ENT_QUOTES, 'UTF-8'); ?></li><?php endif; ?>
            </ul>
        </div>
    </div>
</div>
