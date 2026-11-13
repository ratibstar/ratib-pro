<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/session.php';
require dirname(__DIR__) . '/config/guard.php';
require dirname(__DIR__) . '/admin/lib.php';
require dirname(__DIR__) . '/admin/auth.php';
require dirname(__DIR__) . '/app/auth/account-state.php';

$ownerActor = admin_actor_from_owner(['id' => 1, 'email' => 'admin', 'name' => 'Admin', 'role' => 'owner']);

$failures = 0;
$check = static function (bool $ok, string $name) use (&$failures): void {
    if ($ok) {
        echo "PASS {$name}\n";
        return;
    }
    $failures++;
    echo "FAIL {$name}\n";
};

$check(admin_role_allowed('owner') === true, 'owner role is allowed');
$check(admin_role_allowed('user') === false, 'ordinary role is refused');
$check(admin_role_allowed(null) === false, 'anonymous role is refused');
$check(admin_role_allowed('admin') === false, 'unknown role is refused');
$check(csrf_matches('abc', 'abc') === true, 'csrf match');
$check(csrf_matches('abc', 'abd') === false, 'csrf mismatch');
$check(csrf_matches('', 'abc') === false, 'empty csrf rejected');
$check(admin_offset(1) === 0 && admin_offset(2) === 25 && admin_page() === 1, 'pagination offset');
$check(admin_like('100%_off') === '%100\\%\\_off%', 'search wildcards are escaped');
$check(admin_halalas_to_sar(9900) === '99.00' && admin_halalas_to_sar(24900) === '249.00', 'receipt halalas convert to SAR');
$check(admin_known_payment_credits('starter') === 500 && admin_known_payment_credits('pro') === 2500 && admin_known_payment_credits('growth') === null, 'payment credit map matches webhook');

$secret = 'sk-live-should-not-appear';
$rows = admin_provider_rows([
    'deepseek_present' => '1',
    'groq_present' => '',
    'horde_present' => $secret,
    'sawtak_present' => '',
    'runway_present' => '',
    'wan_url_present' => '',
    'wan_token_present' => '',
    'video_provider' => '',
]);
$encoded = json_encode($rows);
$check(is_string($encoded) && !str_contains($encoded, $secret), 'provider rows omit secret values');
$horde = null;
foreach ($rows as $row) {
    if ($row['id'] === 'horde') {
        $horde = $row;
    }
}
$check($horde !== null && $horde['configured'] === true && $horde['selected'] === false, 'horde key does not mark the provider selected or healthy');
$check($rows[0]['selected'] === true && str_contains($rows[0]['note'], 'not a live health check'), 'deepseek selection is configuration only');

$pdo = new class {
    public array $users = [1 => ['role' => 'owner', 'credits_balance' => 50, 'disabled_at' => null], 2 => ['role' => 'user', 'credits_balance' => 40, 'disabled_at' => null], 3 => ['role' => 'owner', 'credits_balance' => 10, 'disabled_at' => null]];
    public array $adjustments = [];
    public array $audit = [];
    public array $usage = [];
    private bool $tx = false;
    private array $snap = [];
    public function getAttribute(int $attr): string { return 'sqlite'; }
    public function beginTransaction(): void { $this->tx = true; $this->snap = [$this->users, $this->adjustments, $this->audit, $this->usage]; }
    public function commit(): void { $this->tx = false; $this->snap = []; }
    public function rollBack(): void { [$this->users, $this->adjustments, $this->audit, $this->usage] = $this->snap; $this->tx = false; }
    public function inTransaction(): bool { return $this->tx; }
    public function prepare(string $sql): object
    {
        $db = $this;
        return new class($db, $sql) {
            public function __construct(private object $db, private string $sql) {}
            private array $args = [];
            private int $count = 0;
            public function execute(array $args = []): bool
            {
                $this->args = $args;
                $this->count = $this->apply();
                return true;
            }
            public function fetch(): mixed
            {
                $db = $this->db;
                if (str_contains($this->sql, 'FROM admin_credit_adjustments')) {
                    foreach ($db->adjustments as $row) {
                        if ($row['idempotency_key'] === $this->args[0]) {
                            return $row;
                        }
                    }
                    return false;
                }
                if (str_contains($this->sql, 'FROM users')) {
                    $id = (int) $this->args[0];
                    if (!isset($db->users[$id])) {
                        return false;
                    }
                    return ['id' => $id] + $db->users[$id];
                }
                return false;
            }
            public function rowCount(): int
            {
                return $this->count;
            }
            public function fetchColumn(): mixed
            {
                if (str_contains($this->sql, 'information_schema')) {
                    $table = (string) ($this->args[0] ?? '');
                    $column = (string) ($this->args[1] ?? '');
                    if ($table === 'admin_audit_log' && $column === 'id') {
                        return 1;
                    }
                    if ($table === 'admin_credit_adjustments' && $column === 'id') {
                        return 1;
                    }
                    return 0;
                }
                return 0;
            }
            private function apply(): int
            {
                $db = $this->db;
                if (str_starts_with($this->sql, 'UPDATE users SET credits_balance')) {
                    $id = (int) $this->args[1];
                    if (($db->users[$id]['credits_balance'] ?? null) !== (int) $this->args[2]) {
                        return 0;
                    }
                    $db->users[$id]['credits_balance'] = (int) $this->args[0];
                    return 1;
                }
                if (str_starts_with($this->sql, 'UPDATE users SET disabled_at = CURRENT_TIMESTAMP')) {
                    $id = (int) $this->args[0];
                    if (($db->users[$id]['role'] ?? '') === 'owner') {
                        return 0;
                    }
                    $db->users[$id]['disabled_at'] = '2026-11-11 00:00:00';
                    return 1;
                }
                if (str_starts_with($this->sql, 'UPDATE users SET disabled_at = NULL')) {
                    $db->users[(int) $this->args[0]]['disabled_at'] = null;
                    return 1;
                }
                if (str_starts_with($this->sql, 'INSERT INTO admin_credit_adjustments')) {
                    foreach ($db->adjustments as $row) {
                        if ($row['idempotency_key'] === $this->args[0]) {
                            throw new RuntimeException('duplicate');
                        }
                    }
                    $db->adjustments[] = ['idempotency_key' => $this->args[0], 'balance_after' => $this->args[4], 'delta' => $this->args[3]];
                    return 1;
                }
                if (str_starts_with($this->sql, 'INSERT INTO admin_audit_log')) {
                    $db->audit[] = ['action' => $this->args[1]];
                    return 1;
                }
                if (str_starts_with($this->sql, 'INSERT INTO usage_events')) {
                    $db->usage[] = $this->args;
                    return 1;
                }
                return 1;
            }
        };
    }
    public function query(string $sql): object
    {
        $db = $this;
        return new class($db, $sql) {
            public function __construct(private object $db, private string $sql) {}
            public function fetchColumn(): mixed
            {
                if (str_contains($this->sql, 'credits_balance FROM users WHERE id = 2')) {
                    return $this->db->users[2]['credits_balance'];
                }
                if (str_contains($this->sql, 'disabled_at FROM users WHERE id = 2')) {
                    return $this->db->users[2]['disabled_at'];
                }
                if (str_contains($this->sql, 'disabled_at FROM users WHERE id = 3')) {
                    return $this->db->users[3]['disabled_at'];
                }
                if (str_contains($this->sql, 'COUNT(*) FROM usage_events')) {
                    return count($this->db->usage);
                }
                if (str_contains($this->sql, 'COUNT(*) FROM admin_audit_log')) {
                    return count($this->db->audit);
                }
                return null;
            }
            public function fetchAll(int $mode = 0): array
            {
                return array_column($this->db->audit, 'action');
            }
        };
    }
};

$first = admin_adjust_credits($pdo, $ownerActor, 2, 10, 'goodwill credit', str_repeat('a', 32));
$check($first['ok'] === true && $first['code'] === 'applied' && $first['balance'] === 50, 'credit add applies once');
$again = admin_adjust_credits($pdo, $ownerActor, 2, 10, 'goodwill credit', str_repeat('a', 32));
$balance = (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 2')->fetchColumn();
$events = (int) $pdo->query('SELECT COUNT(*) FROM usage_events')->fetchColumn();
$audits = (int) $pdo->query('SELECT COUNT(*) FROM admin_audit_log')->fetchColumn();
$check($again['code'] === 'duplicate' && $balance === 50 && $events === 1 && $audits === 1, 'duplicate credit key does not move the balance');
$poor = admin_adjust_credits($pdo, $ownerActor, 2, -1000, 'too much', str_repeat('b', 32));
$check($poor['ok'] === false && $poor['code'] === 'balance' && (int) $pdo->query('SELECT credits_balance FROM users WHERE id = 2')->fetchColumn() === 50, 'deduction cannot pass zero');
$short = admin_adjust_credits($pdo, $ownerActor, 2, 1, 'no', str_repeat('c', 32));
$check($short['code'] === 'reason', 'short reason is rejected');
$self = admin_set_account_state($pdo, $ownerActor, 1, true, 'lock myself');
$check($self['code'] === 'self', 'owner cannot disable self');
$owner = admin_set_account_state($pdo, $ownerActor, 3, true, 'lock other owner');
$check($owner['code'] === 'owner' && $pdo->query('SELECT disabled_at FROM users WHERE id = 3')->fetchColumn() === null, 'another owner account cannot be disabled');
$off = admin_set_account_state($pdo, $ownerActor, 2, true, 'abuse report');
$flag = $pdo->query('SELECT disabled_at FROM users WHERE id = 2')->fetchColumn();
$check($off['ok'] === true && $flag !== null && $flag !== '', 'ordinary account can be disabled');
$on = admin_set_account_state($pdo, $ownerActor, 2, false, 'appeal accepted');
$flag = $pdo->query('SELECT disabled_at FROM users WHERE id = 2')->fetchColumn();
$check($on['ok'] === true && ($flag === null || $flag === ''), 'ordinary account can be enabled');
$auditActions = $pdo->query('SELECT action FROM admin_audit_log ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
$check($auditActions === ['credit_add', 'account_disable', 'account_enable'], 'audit log stores admin, action, and keeps order');

echo $failures === 0 ? "ALL PASS {$failures}\n" : "FAILURES {$failures}\n";
exit($failures === 0 ? 0 : 1);
