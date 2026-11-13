<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/auth/account-state.php';
require dirname(__DIR__) . '/admin/lib.php';
require dirname(__DIR__) . '/app/plans.php';

$failures = 0;
$check = static function (bool $ok, string $name) use (&$failures): void {
    if ($ok) {
        echo "PASS {$name}\n";
        return;
    }
    $failures++;
    echo "FAIL {$name}\n";
};

$disabledPdo = new class {
    public array $user = ['role' => 'user', 'disabled_at' => '2026-11-11 00:00:00'];
    public function query(string $sql): ArrayIterator
    {
        return new ArrayIterator([['Field' => 'disabled_at'], ['Field' => 'last_login_at']]);
    }
    public function prepare(string $sql): object
    {
        $db = $this;
        return new class($db) {
            public function __construct(private object $db) {}
            public function execute(array $args = []): bool { return true; }
            public function fetch(): array { return $this->db->user; }
        };
    }
};
$check(rateb_user_is_disabled($disabledPdo, 4) === true, 'disabled customer is detected');
$disabledPdo->user['role'] = 'owner';
$check(rateb_user_is_disabled($disabledPdo, 4) === false, 'owner is not treated as disabled');
$disabledPdo->user = ['role' => 'user', 'disabled_at' => null];
$check(rateb_user_is_disabled($disabledPdo, 4) === false, 'active customer is not disabled');

$root = dirname(__DIR__);
$read = static fn (string $path): string => (string) file_get_contents($root . '/' . $path);
$check(str_contains($read('config/bootstrap.php'), 'rateb_block_disabled_session($pdo)'), 'bootstrap blocks a disabled session');
$check(str_contains($read('config/bootstrap.php'), 'function current_user') && str_contains($read('config/bootstrap.php'), 'rateb_block_disabled_session($pdo)'), 'current_user path is covered from bootstrap');
$check(str_contains($read('config/guard.php'), 'rateb_block_disabled_session($authPdo)'), 'require_login blocks a disabled session');
$check(str_contains($read('campaign-new.php'), 'rateb_block_disabled_session(db())'), 'campaign drafts are behind the disabled check');
$check(str_contains($read('app/brand/library.php'), 'rateb_block_disabled_session(db())'), 'brand assets are behind the disabled check');
$login = $read('app/auth/login.php');
$check(strpos($login, 'rateb_user_is_disabled') < strpos($login, "header('Location: /dashboard')"), 'existing login session is checked before the dashboard redirect');
$check(str_contains($read('app/auth/google.php'), 'rateb_user_is_disabled'), 'google sign-in checks disabled');
$check(str_contains($read('app/auth/github.php'), 'rateb_user_is_disabled'), 'github sign-in checks disabled');

$buckets = admin_credit_buckets([
    ['operation' => 'image', 'credits' => 10, 'events' => 1],
    ['operation' => 'ai_image', 'credits' => 10, 'events' => 1],
    ['operation' => 'voice', 'credits' => 15, 'events' => 1],
    ['operation' => 'saudi_voice', 'credits' => 15, 'events' => 1],
    ['operation' => 'ai_image_refund', 'credits' => 0, 'events' => 1],
    ['operation' => 'admin_credit', 'credits' => -25, 'events' => 1],
    ['operation' => 'payment_starter', 'credits' => 0, 'events' => 1],
]);
$check($buckets['consumed'] === 25, 'image and voice ledger charges count once');
$check($buckets['refunded'] === 10, 'one image refund counts the feature price once');
$check($buckets['adjusted_add'] === 25 && $buckets['adjusted_deduct'] === 0, 'admin adjustment is not customer usage');
$check($buckets['granted'] === 500, 'payment grant is separate from consumption');

$adminLang = $read('admin/lang.php');
$adminBoot = $read('admin/bootstrap.php');
$check(str_contains($adminLang, 'rateb_admin_lang') && !str_contains($adminLang, 'rateb_ui_lang'), 'admin language cookie is separate');
$check(str_contains($adminBoot, 'rateb_admin_lang') && !str_contains($adminBoot, 'rateb_ui_lang'), 'admin layout does not read the public language cookie');
$check(str_contains($read('config/guard.php'), "rateb_ui_lang"), 'public language cookie is unchanged');

$truth = admin_billing_truth();
$check(str_contains($truth['limits'], 'subscriptions') && str_contains($truth['wallet'], 'credits_balance'), 'limits and wallet have different sources');
$check(str_contains($truth['reconcile'], 'do not') && str_contains($truth['reconcile'], 'grant credits'), 'reconciliation does not auto-grant credits');

$secret = 'moyasar-webhook-secret';
$checkout = $read('app/payment/checkout.php');
$webhook = $read('app/payment/webhook.php');
$check(str_contains($checkout, 'payment_orders') && str_contains($checkout, 'verify_csrf()') && str_contains($checkout, "packageCode === 'usage'"), 'production checkout keeps orders, CSRF, and usage billing');
$check(str_contains($checkout, 'callback_url') && str_contains($checkout, '/app/payment/return.php') && !str_contains($checkout, 'rateb_moyasar_callback_url'), 'checkout callback stays the return URL');
$check(str_contains($webhook, 'HTTP_X_MOYASAR_TOKEN') && str_contains($webhook, 'secret_token') && str_contains($webhook, 'moyasar_webhook_secret') && str_contains($webhook, 'rateb_payment_record'), 'webhook keeps production authentication and recording');
$check(rateb_moyasar_webhook_authorized($secret, $secret) === true, 'matching Moyasar webhook token is accepted');
$check(rateb_moyasar_webhook_authorized($secret, 'other') === false && rateb_moyasar_webhook_authorized('', $secret) === false, 'missing or wrong Moyasar webhook token is rejected');

echo $failures === 0 ? "ALL PASS 0\n" : "FAILURES {$failures}\n";
echo "SIMULATED ONLY. No MySQL or MariaDB connection was opened.\n";
exit($failures === 0 ? 0 : 1);
