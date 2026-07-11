<?php ?>
<div class="container-fluid py-3">
    <h1 class="h4 mb-3"><?php echo htmlspecialchars((string) ($title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h1>
    <form method="post" action="<?php echo htmlspecialchars(rateb_url(rateb_app_route('accounting/currencies')), ENT_QUOTES, 'UTF-8'); ?>" class="row g-3 col-lg-6">
        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars((string) ($csrf ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="col-md-4"><label class="form-label"><?php echo htmlspecialchars(__('code'), ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" name="code" maxlength="3" required></div>
        <div class="col-md-8"><label class="form-label"><?php echo htmlspecialchars(__('name'), ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" name="name" required></div>
        <div class="col-md-8"><label class="form-label"><?php echo htmlspecialchars(__('name_ar'), ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" name="name_ar"></div>
        <div class="col-md-4"><label class="form-label"><?php echo htmlspecialchars(__('symbol'), ENT_QUOTES, 'UTF-8'); ?></label><input class="form-control" name="symbol"></div>
        <div class="col-12 form-check"><input class="form-check-input" type="checkbox" name="is_base" value="1" id="is_base"><label class="form-check-label" for="is_base"><?php echo htmlspecialchars(__('accounting_base_currency'), ENT_QUOTES, 'UTF-8'); ?></label></div>
        <div class="col-12"><button class="btn btn-primary" type="submit"><?php echo htmlspecialchars(__('save'), ENT_QUOTES, 'UTF-8'); ?></button>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(rateb_url(rateb_app_route('accounting/currencies')), ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(__('cancel'), ENT_QUOTES, 'UTF-8'); ?></a></div>
    </form>
</div>
