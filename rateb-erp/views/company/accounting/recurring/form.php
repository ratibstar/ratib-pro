<?php ?>
<div class="container-fluid py-3">
    <h1 class="h4 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('accounting/recurring')), ENT_QUOTES, 'UTF-8'); ?>" class="row g-3">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="col-md-3"><label class="form-label"><?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" name="code" required></div>
        <div class="col-md-5"><label class="form-label"><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" name="name" required></div>
        <div class="col-md-2"><label class="form-label"><?php echo htmlspecialchars(__('frequency'), ENT_QUOTES, 'UTF-8'); ?></label>
            <select class="form-select" name="frequency"><option value="monthly">monthly</option><option value="weekly">weekly</option><option value="quarterly">quarterly</option><option value="yearly">yearly</option></select>
        </div>
        <div class="col-md-2"><label class="form-label"><?php echo htmlspecialchars(__('next_run'), ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" type="date" name="next_run_date" value="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"></div>
        <div class="col-12"><h2 class="h6"><?php echo htmlspecialchars(__('journal_lines'), ENT_QUOTES, 'UTF-8'); ?></h2></div>
        <?php for ($i = 0; $i < 4; $i++): ?>
        <div class="col-md-4"><input class="form-control" name="line_account_id[]" placeholder="<?php echo htmlspecialchars(__('account_id'), ENT_QUOTES, 'UTF-8'); ?>" type="number" <?php echo $i < 2 ? 'required' : ''; ?>></div>
        <div class="col-md-4"><input class="form-control" name="line_debit[]" placeholder="debit" type="number" step="0.01" value="0"></div>
        <div class="col-md-4"><input class="form-control" name="line_credit[]" placeholder="credit" type="number" step="0.01" value="0"></div>
        <?php endfor; ?>
        <div class="col-12"><button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button></div>
    </form>
</div>
