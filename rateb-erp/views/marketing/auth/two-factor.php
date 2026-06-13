<?php /** @var string $csrf */ ?>
<section class="rateb-mkt-page-hero">
    <div class="container">
        <h1><?php echo __('two_factor_verify'); ?></h1>
    </div>
</section>
<section class="rateb-mkt-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-5">
                <div class="rateb-mkt-auth-card">
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
        </div>
    </div>
</section>
