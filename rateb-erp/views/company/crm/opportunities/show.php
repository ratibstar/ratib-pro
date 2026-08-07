<?php
declare(strict_types=1);
$item = $item ?? [];
$timeline = $timeline ?? [];
?>
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1"><?php echo htmlspecialchars((string) ($item['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="text-muted"><?php echo htmlspecialchars((string) ($item['opportunity_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> · <span class="badge text-bg-secondary"><?php echo htmlspecialchars((string) ($item['workflow_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span></div>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/opportunities')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('back'), ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
    <div class="border rounded p-3 mb-3">
        <div><strong><?php echo htmlspecialchars(__('amount'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo htmlspecialchars((string) ($item['amount'] ?? '0'), ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars((string) ($item['currency_code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
        <div><strong><?php echo htmlspecialchars(__('crm_lead_id'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo (int) ($item['lead_id'] ?? 0); ?></div>
        <div><strong><?php echo htmlspecialchars(__('customer_id'), ENT_QUOTES, 'UTF-8'); ?>:</strong> <?php echo (int) ($item['customer_id'] ?? 0); ?></div>
    </div>
    <?php if (!empty($canConvert)): ?>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/opportunities') . '/' . (int) $item['id'] . '/convert-quotation'), ENT_QUOTES, 'UTF-8'); ?>" class="border rounded p-3 mb-3">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars(\Rateb\App\Core\Csrf::token(), ENT_QUOTES, 'UTF-8'); ?>">
        <button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('crm_convert_to_quotation'), ENT_QUOTES, 'UTF-8'); ?></button>
    </form>
    <?php endif; ?>
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
