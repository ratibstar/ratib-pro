<?php
declare(strict_types=1);

use Rateb\App\Core\View;

$esc = static fn ($v): string => View::escape($v);
$result = is_array($result ?? null) ? $result : null;
$password = (string) ($password ?? '');
$email = (string) ($email ?? '');
?>
<div class="rateb-card">
    <div class="rateb-card-header"><?php echo $esc($title ?? 'Module add-on demo user'); ?></div>
    <div class="rateb-card-body">
        <p class="text-muted">admin.rateb.sa preview only. Does not create an invoice, start payment, or activate CRM.</p>
        <p>This prepares a normal company user and removes <code>crm</code> from this demo tenant module pack so locked checkout can be shown.</p>
        <?php if (is_array($result)) { ?>
            <?php if (!empty($result['ok'])) { ?>
        <div class="alert alert-success">Demo user ready.</div>
        <dl class="row">
            <dt class="col-sm-4">Login</dt>
            <dd class="col-sm-8"><code id="demo-preview-email"><?php echo $esc((string) ($result['email'] ?? $email)); ?></code></dd>
            <dt class="col-sm-4">Password</dt>
            <dd class="col-sm-8"><code id="demo-preview-password"><?php echo $esc($password); ?></code></dd>
            <dt class="col-sm-4">Company</dt>
            <dd class="col-sm-8"><?php echo $esc((string) ($result['company_name'] ?? '')); ?> (#<?php echo $esc((string) (int) ($result['company_id'] ?? 0)); ?>)</dd>
            <dt class="col-sm-4">Checkout</dt>
            <dd class="col-sm-8"><a href="<?php echo $esc(rateb_url('admin/billing/modules/crm')); ?>">/admin/billing/modules/crm</a></dd>
        </dl>
            <?php } else { ?>
        <div class="alert alert-danger">Failed: <code><?php echo $esc((string) ($result['code'] ?? 'error')); ?></code></div>
            <?php } ?>
        <?php } ?>
        <form method="post" action="<?php echo $esc($action ?? ''); ?>">
            <input type="hidden" name="_csrf" value="<?php echo $esc($csrf ?? ''); ?>">
            <input type="hidden" name="confirm" value="1">
            <button type="submit" class="btn btn-primary" id="demo-preview-bootstrap">Create / reset demo user</button>
        </form>
    </div>
</div>
