<?php
declare(strict_types=1);
$leadId = (int) ($lead_id ?? 0);
$opportunityId = (int) ($opportunity_id ?? 0);
$customerId = (int) ($customer_id ?? 0);
?>
<div class="container-fluid py-3">
    <h1 class="h3 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars((string) ($action ?? ''), ENT_QUOTES, 'UTF-8'); ?>" class="card">
        <?php echo rateb_csrf_field(); ?>
        <div class="card-body row g-3">
            <div class="col-md-6">
                <label class="form-label"><?php echo htmlspecialchars(__('title'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input class="form-control" name="title" required>
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo htmlspecialchars(__('crm_lead_id'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input class="form-control" name="lead_id" type="number" min="0" value="<?php echo $leadId > 0 ? $leadId : ''; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo htmlspecialchars(__('crm_opportunity_id'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input class="form-control" name="opportunity_id" type="number" min="0" value="<?php echo $opportunityId > 0 ? $opportunityId : ''; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo htmlspecialchars(__('customer_id'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input class="form-control" name="customer_id" type="number" min="0" value="<?php echo $customerId > 0 ? $customerId : ''; ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo htmlspecialchars(__('item'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input class="form-control" name="item_name">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo htmlspecialchars(__('quantity'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input class="form-control" name="quantity" type="number" step="0.001" value="1">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo htmlspecialchars(__('unit_price'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input class="form-control" name="unit_price" type="number" step="0.01" value="0">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo htmlspecialchars(__('tax_rate'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input class="form-control" name="tax_rate" type="number" step="0.01" value="15">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?php echo htmlspecialchars(__('currency'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input class="form-control" name="currency_code" value="SAR" maxlength="3">
            </div>
            <div class="col-md-3">
                <label class="form-label"><?php echo htmlspecialchars(__('valid_until'), ENT_QUOTES, 'UTF-8'); ?></label>
                <input class="form-control" name="valid_until" type="date">
            </div>
            <div class="col-12">
                <label class="form-label"><?php echo htmlspecialchars(__('notes'), ENT_QUOTES, 'UTF-8'); ?></label>
                <textarea class="form-control" name="notes" rows="3"></textarea>
            </div>
            <div class="col-12">
                <button class="btn btn-primary"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('crm/quotations')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        </div>
    </form>
</div>
