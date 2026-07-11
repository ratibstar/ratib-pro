<?php ?>
<div class="container-fluid py-3">
    <h1 class="h4 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('accounting/tax-codes')), ENT_QUOTES, 'UTF-8'); ?>" class="row g-3 col-lg-6">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="col-md-4"><label class="form-label"><?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" name="code" required></div>
        <div class="col-md-8"><label class="form-label"><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" name="name" required></div>
        <div class="col-md-4"><label class="form-label">%</label><input class="form-control" type="number" step="0.0001" name="rate_percent" value="15"></div>
        <div class="col-md-4"><label class="form-label"><?php echo htmlspecialchars(__('type'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select class="form-select" name="tax_type"><option value="vat">VAT</option><option value="withholding">Withholding</option><option value="other">Other</option></select>
        </div>
        <div class="col-12"><button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
</div>
