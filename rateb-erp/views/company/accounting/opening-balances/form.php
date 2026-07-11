<?php ?>
<div class="container-fluid py-3">
    <h1 class="h4 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('accounting/opening-balances')), ENT_QUOTES, 'UTF-8'); ?>" class="row g-3">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="col-md-3"><label class="form-label"><?php echo htmlspecialchars(__('date'), ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" type="date" name="entry_date" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" required></div>
        <div class="col-md-9"><label class="form-label"><?php echo htmlspecialchars(__('description'), ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" name="description" value="Opening balances" required></div>
        <div class="col-12"><p class="text-muted small"><?php echo htmlspecialchars(__('accounting_opening_hint'), ENT_QUOTES, 'UTF-8'); ?></p></div>
        <?php for ($i = 0; $i < 6; $i++): ?>
        <div class="col-md-4"><input class="form-control" name="line_account_id[]" type="number" placeholder="<?php echo htmlspecialchars(__('account_id'), ENT_QUOTES, 'UTF-8'); ?>" <?php echo $i < 2 ? 'required' : ''; ?>></div>
        <div class="col-md-4"><input class="form-control" name="line_debit[]" type="number" step="0.01" value="0"></div>
        <div class="col-md-4"><input class="form-control" name="line_credit[]" type="number" step="0.01" value="0"></div>
        <?php endfor; ?>
        <div class="col-12"><button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
</div>
