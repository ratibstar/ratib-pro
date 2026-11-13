<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/plans.php';
require dirname(__DIR__) . '/app/maintenance/credits.php';

$failures = 0;
$check = static function (bool $ok, string $name) use (&$failures): void {
    if ($ok) {
        echo "PASS {$name}\n";
        return;
    }
    $failures++;
    echo "FAIL {$name}\n";
};

$check(rateb_paid_package('starter')['amount'] === 9900 && rateb_paid_package('pro')['credits'] === 2500 && rateb_paid_package('usage') === null, 'starter and pro prices stay published amounts');
$check(rateb_payment_accept(['status' => 'paid', 'currency' => 'SAR', 'amount' => 9900, 'metadata' => ['user_id' => '4', 'package' => 'starter', 'order_id' => '8']]) === '', 'accepted starter payment matches amount and order');
$check(rateb_payment_accept(['status' => 'paid', 'currency' => 'SAR', 'amount' => 100, 'metadata' => ['user_id' => '4', 'package' => 'usage', 'credits' => '10', 'order_id' => '8']]) === 'amount', 'usage bill rejects an amount that does not match the credit price');
$check(rateb_moyasar_webhook_authorized('webhook-secret', 'webhook-secret') && !rateb_moyasar_webhook_authorized('webhook-secret', 'api-secret'), 'webhook authorization compares the webhook secret, not the API secret');

$record = (string) file_get_contents(dirname(__DIR__) . '/app/plans.php');
$recordStart = strpos($record, 'function rateb_payment_record');
$recordBody = $recordStart === false ? '' : substr($record, $recordStart, 2800);
$check(str_contains($recordBody, "status'] === 'paid'") && str_contains($recordBody, "return 'duplicate'") && str_contains($recordBody, 'payment_receipts WHERE moyasar_id'), 'a paid order or known Moyasar id is a duplicate before credits change');

$schema = (string) file_get_contents(dirname(__DIR__) . '/app/schema.php');
$check(!str_contains($schema, 'test_credit_grant') && !str_contains($schema, 'text_campaign_repair') && !str_contains($schema, 'credits_balance = credits_balance +'), 'web schema bootstrap does not grant credits');
$cli = (string) file_get_contents(dirname(__DIR__) . '/cli/maintenance-credits.php');
$check(str_contains($cli, "PHP_SAPI !== 'cli'"), 'campaign 72 grant is not an HTTP route');

$grant = new class {
    public int $balance = 40;
    public array $keys = [];
    public int $writes = 0;
    public bool $tx = false;
    private int $snapBalance = 40;
    private array $snapKeys = [];
    public function beginTransaction(): void { $this->snapBalance = $this->balance; $this->snapKeys = $this->keys; $this->tx = true; }
    public function commit(): void { $this->tx = false; }
    public function rollBack(): void { $this->balance = $this->snapBalance; $this->keys = $this->snapKeys; $this->tx = false; }
    public function inTransaction(): bool { return $this->tx; }
    public function prepare(string $sql): object
    {
        $db = $this;
        return new class($db, $sql) {
            public function __construct(private object $db, private string $sql) {}
            private array $args = [];
            public function execute(array $args = []): bool
            {
                $this->args = $args;
                if (str_starts_with($this->sql, 'INSERT INTO maintenance_grants')) {
                    if (isset($this->db->keys[$args[0]])) {
                        throw new PDOException('duplicate', 23000);
                    }
                    $this->db->keys[$args[0]] = true;
                    $this->db->writes++;
                }
                if (str_starts_with($this->sql, 'UPDATE users')) {
                    $this->db->balance += (int) $args[0];
                    $this->db->writes++;
                }
                return true;
            }
            public function fetch(): mixed { return false; }
        };
    }
};
$check(rateb_maintenance_grant($grant, 'test_credit_grant', 9, null, 200, 'operator campaign 72 grant', true) === 'dry-run' && $grant->balance === 40 && $grant->writes === 0, 'dry-run does not change balances');
$check(rateb_maintenance_grant($grant, 'test_credit_grant', 9, null, 200, 'operator campaign 72 grant', false) === 'applied' && $grant->balance === 240, 'first maintenance grant applies once');
$again = rateb_maintenance_grant($grant, 'test_credit_grant', 9, null, 200, 'operator campaign 72 grant', false);
$check($again === 'duplicate' && $grant->balance === 240, 'repeated or concurrent grant does not apply twice');
$check(rateb_text_repair_credit(3, 1) === 5 && rateb_text_repair_credit(1, 0) === 0, 'text repair keeps the original extra-charge rule');

$migration = (string) file_get_contents(dirname(__DIR__) . '/migrations/2026-11-11-admin-audit.sql');
$check(str_contains($migration, 'users.updated_at is missing') && str_contains($migration, 'IF (SELECT COUNT(*)'), 'migration checks updated_at and existing objects before adding them');
$check(!str_contains($migration, 'DROP TABLE') && !str_contains($migration, 'DROP COLUMN'), 'migration does not drop customer tables or columns');
$grants = (string) file_get_contents(dirname(__DIR__) . '/migrations/2026-11-11-maintenance-grants.sql');
$check(str_contains($grants, 'CREATE TABLE IF NOT EXISTS maintenance_grants') && str_contains($grants, 'UNIQUE KEY operation_key'), 'maintenance grant table is additive and unique');

echo $failures === 0 ? "ALL PASS 0\n" : "FAILURES {$failures}\n";
echo "SIMULATED ONLY. No MariaDB connection was opened.\n";
exit($failures === 0 ? 0 : 1);
