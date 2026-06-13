<?php
/** @var array<string,mixed> $user */
/** @var array<string,mixed>|null $pending */
/** @var string $csrf */
?>
<div class="card">
    <div class="card-body">
        <h2><?php echo __('two_factor_setup'); ?></h2>
        <?php if ((int) ($user['two_factor_enabled'] ?? 0) === 1) { ?>
            <p class="text-success"><?php echo __('two_factor_enabled'); ?></p>
            <form method="post" action="<?php echo rateb_app_url('profile/2fa/disable'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <button type="submit" class="btn btn-outline-danger"><?php echo __('two_factor_disable'); ?></button>
            </form>
        <?php } elseif ($pending) { ?>
            <p><?php echo __('two_factor_scan'); ?></p>
            <p><code><?php echo Rateb\App\Core\View::escape((string) $pending['secret']); ?></code></p>
            <p class="small text-muted"><code><?php echo Rateb\App\Core\View::escape((string) $pending['uri']); ?></code></p>
            <form method="post" action="<?php echo rateb_app_url('profile/2fa/enable'); ?>">
                <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape($csrf); ?>">
                <div class="mb-3">
                    <label class="form-label"><?php echo __('two_factor_code'); ?></label>
                    <input type="text" name="code" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary"><?php echo __('enable'); ?></button>
            </form>
        <?php } else { ?>
            <p><a href="<?php echo rateb_app_url('profile/2fa'); ?>" class="btn btn-primary"><?php echo __('two_factor_begin'); ?></a></p>
        <?php } ?>
    </div>
</div>
