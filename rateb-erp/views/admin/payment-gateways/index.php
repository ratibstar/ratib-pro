<?php Rateb\App\Core\View::partial('accounting-nav', ['accountingActive' => $accountingActive ?? 'admin']); ?>
<div class="rateb-card mb-4">
    <div class="rateb-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h2 class="h5 mb-0"><?php echo __('payment_gateways') ?: 'Payment Gateways'; ?> — Moyasar</h2>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo rateb_url('admin/payment-gateways/transactions'); ?>"><?php echo __('payment_transactions') ?: 'Transactions'; ?></a>
            <a class="btn btn-outline-danger btn-sm" href="<?php echo rateb_url('admin/payment-gateways/failed'); ?>"><?php echo __('failed_payments') ?: 'Failed'; ?></a>
        </div>
    </div>
    <div class="rateb-card-body">
        <form method="post" action="<?php echo rateb_url('admin/payment-gateways/save'); ?>" id="rateb-payment-gateways-form" data-health-url="<?php echo rateb_url('admin/payment-gateways/health'); ?>">
            <input type="hidden" name="_csrf" value="<?php echo Rateb\App\Core\View::escape((string) ($csrf ?? '')); ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('enabled') ?: 'Enabled'; ?></label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="enabled" value="1" id="pg-enabled" <?php echo !empty($settings['enabled']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="pg-enabled">Moyasar</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="pg-mode"><?php echo __('mode') ?: 'Mode'; ?></label>
                    <select class="form-select" name="mode" id="pg-mode">
                        <option value="sandbox" <?php echo ($settings['mode'] ?? '') === 'sandbox' ? 'selected' : ''; ?>>Sandbox</option>
                        <option value="production" <?php echo ($settings['mode'] ?? '') === 'production' ? 'selected' : ''; ?>>Production</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?php echo __('health_status') ?: 'Health'; ?></label>
                    <p class="mb-0"><span class="badge bg-secondary" id="pg-health-badge"><?php echo Rateb\App\Core\View::escape((string) ($settings['health_status'] ?? 'unknown')); ?></span></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="pg-pub"><?php echo __('publishable_key') ?: 'Publishable Key'; ?></label>
                    <input type="text" class="form-control" name="publishable_key" id="pg-pub" placeholder="<?php echo Rateb\App\Core\View::escape((string) ($settings['publishable_key_masked'] ?? '')); ?>" autocomplete="off">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="pg-secret"><?php echo __('secret_key') ?: 'Secret Key'; ?></label>
                    <input type="password" class="form-control" name="secret_key" id="pg-secret" placeholder="<?php echo Rateb\App\Core\View::escape((string) ($settings['secret_key_masked'] ?? '')); ?>" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="pg-wh-secret"><?php echo __('webhook_secret') ?: 'Webhook Secret'; ?></label>
                    <input type="password" class="form-control" name="webhook_secret" id="pg-wh-secret" placeholder="<?php echo Rateb\App\Core\View::escape((string) ($settings['webhook_secret_masked'] ?? '')); ?>" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="pg-callback"><?php echo __('callback_url') ?: 'Callback URL'; ?></label>
                    <input type="url" class="form-control" name="callback_url" id="pg-callback" value="<?php echo Rateb\App\Core\View::escape((string) ($settings['callback_url'] ?? '')); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label" for="pg-webhook"><?php echo __('webhook_url') ?: 'Webhook URL'; ?></label>
                    <input type="url" class="form-control" name="webhook_url" id="pg-webhook" value="<?php echo Rateb\App\Core\View::escape((string) ($settings['webhook_url'] ?? '')); ?>" readonly>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary"><?php echo __('save') ?: 'Save'; ?></button>
                <button type="button" class="btn btn-outline-primary" id="pg-test-connection"><?php echo __('test_connection') ?: 'Test Connection'; ?></button>
            </div>
        </form>
    </div>
</div>
<link href="<?php echo rateb_asset('css/payment-gateways.css'); ?>" rel="stylesheet">
<script src="<?php echo rateb_asset('js/payment-gateways.js'); ?>"></script>
