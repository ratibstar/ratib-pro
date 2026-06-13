<?php /** @var string $csrf */ ?>
<div class="rateb-auth-page-wrap">
    <div class="rateb-mkt-auth-card">
        <h1 class="h4 text-center mb-4"><?php echo __('two_factor_verify'); ?></h1>
        <form method="post" action="<?php echo rateb_url('site/login/2fa'); ?>" class="rateb-mkt-form">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
            <div class="mb-3">
                <label class="form-label" for="code"><?php echo __('two_factor_code'); ?></label>
                <input type="text" class="form-control" id="code" name="code" required autocomplete="one-time-code" inputmode="numeric">
            </div>
            <button type="submit" class="btn btn-primary w-100"><?php echo __('two_factor_verify'); ?></button>
        </form>
    </div>
</div>
