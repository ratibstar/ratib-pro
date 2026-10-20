<?php
declare(strict_types=1);

/**
 * In-memory stand-in for the usage_events, users, plans, and subscriptions tables. It
 * implements only the statements the reservation and usage functions issue, plus reads
 * and writes a test registers explicitly, and rejects anything else, so a changed query
 * fails the test instead of silently passing.
 */
final class FakeLedger extends PDO
{
    /** @var array<int,int> */
    public array $balances = [];
    /** @var array<int,array<string,mixed>> */
    public array $events = [];
    public int $nextId = 1;
    public string $failOn = '';
    /** @var array<string,mixed>|null the active plan row rateb_lock_plan() returns */
    public ?array $plan = null;
    /** @var array<string,array<int,array<string,mixed>>> registered read statements and their rows */
    public array $reads = [];
    /** @var array<int,string> registered write statements */
    public array $writable = [];
    /** @var array<int,array{sql:string,params:array<int,mixed>}> */
    public array $writes = [];
    private ?array $snapshot = null;
    private int $lastId = 0;

    public function __construct()
    {
    }

    public function reserve(int $userId, ?int $campaignId, string $operation, int $ageSeconds = 0): int
    {
        $id = $this->nextId++;
        $this->events[$id] = ['user_id' => $userId, 'campaign_id' => $campaignId, 'operation' => $operation, 'status' => 'reserved', 'credits_deducted' => 0, 'age' => $ageSeconds];
        return $id;
    }

    /** @return array<int,array<string,mixed>> */
    public function rows(string $operation): array
    {
        return array_values(array_filter($this->events, static fn (array $row): bool => $row['operation'] === $operation));
    }

    public static function normalize(string $sql): string
    {
        return preg_replace('/\s+/', ' ', trim($sql)) ?? '';
    }

    public function beginTransaction(): bool
    {
        $this->snapshot = [$this->balances, $this->events, $this->nextId];
        return true;
    }

    public function commit(): bool
    {
        $this->snapshot = null;
        return true;
    }

    public function rollBack(): bool
    {
        if ($this->snapshot !== null) {
            [$this->balances, $this->events, $this->nextId] = $this->snapshot;
        }
        $this->snapshot = null;
        return true;
    }

    public function inTransaction(): bool
    {
        return $this->snapshot !== null;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return (string) $this->lastId;
    }

    public function setLastId(int $id): void
    {
        $this->lastId = $id;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new FakeStatement($this, self::normalize($query));
    }
}

final class FakeStatement extends PDOStatement
{
    private int $affected = 0;
    /** @var array<int,array<string,mixed>> */
    private array $result = [];

    public function __construct(private FakeLedger $db, private string $sql)
    {
    }

    public function execute(?array $params = null): bool
    {
        $p = array_values($params ?? []);
        $db = $this->db;
        if ($db->failOn !== '' && str_contains($this->sql, $db->failOn)) {
            throw new PDOException('injected failure');
        }
        $this->affected = 0;
        $this->result = [];
        switch ($this->sql) {
            case "UPDATE usage_events SET credits_deducted = ? WHERE id = ? AND user_id = ? AND status = 'reserved' AND credits_deducted = 0":
                $row = $db->events[(int) $p[1]] ?? null;
                if ($row && $row['user_id'] === (int) $p[2] && $row['status'] === 'reserved' && $row['credits_deducted'] === 0) {
                    $db->events[(int) $p[1]]['credits_deducted'] = (int) $p[0];
                    $this->affected = 1;
                }
                return true;
            case 'UPDATE users SET credits_balance = credits_balance - ? WHERE id = ? AND credits_balance >= ?':
                if (($db->balances[(int) $p[1]] ?? -1) >= (int) $p[2]) {
                    $db->balances[(int) $p[1]] -= (int) $p[0];
                    $this->affected = 1;
                }
                return true;
            case 'UPDATE users SET credits_balance = credits_balance + ? WHERE id = ?':
                if (isset($db->balances[(int) $p[1]])) {
                    $db->balances[(int) $p[1]] += (int) $p[0];
                    $this->affected = 1;
                }
                return true;
            case "INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted) VALUES (?, ?, ?, 1, 'completed', ?, ?)":
            case "INSERT INTO usage_events (user_id, campaign_id, operation, units, status, event_type, credits_deducted) VALUES (?, ?, ?, 1, 'completed', ?, 0)":
                $db->events[$db->nextId++] = ['user_id' => (int) $p[0], 'campaign_id' => $p[1], 'operation' => (string) $p[2], 'status' => 'completed', 'credits_deducted' => (int) ($p[4] ?? 0), 'age' => 0];
                $this->affected = 1;
                return true;
            case "INSERT INTO usage_events (user_id, campaign_id, operation, units, seconds, status) VALUES (?, ?, ?, 1, ?, 'reserved')":
                $id = $db->reserve((int) $p[0], $p[1] === null ? null : (int) $p[1], (string) $p[2]);
                $db->setLastId($id);
                $this->affected = 1;
                return true;
            case "UPDATE usage_events SET status = ? WHERE id = ? AND user_id = ? AND status = 'reserved'":
                $row = $db->events[(int) $p[1]] ?? null;
                if ($row && $row['user_id'] === (int) $p[2] && $row['status'] === 'reserved') {
                    $db->events[(int) $p[1]]['status'] = (string) $p[0];
                    $this->affected = 1;
                }
                return true;
            case "UPDATE usage_events SET status = ? WHERE id = ? AND status = 'reserved'":
                $row = $db->events[(int) $p[1]] ?? null;
                if ($row && $row['status'] === 'reserved') {
                    $db->events[(int) $p[1]]['status'] = (string) $p[0];
                    $this->affected = 1;
                }
                return true;
            case 'SELECT credits_deducted, campaign_id FROM usage_events WHERE id = ? AND user_id = ?':
                $row = $db->events[(int) $p[0]] ?? null;
                if ($row && $row['user_id'] === (int) $p[1]) {
                    $this->result = [['credits_deducted' => $row['credits_deducted'], 'campaign_id' => $row['campaign_id']]];
                }
                return true;
            case "SELECT id FROM usage_events WHERE user_id = ? AND operation = ? AND status = 'reserved' AND credits_deducted > 0 AND created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)":
                foreach ($db->events as $id => $row) {
                    if ($row['user_id'] === (int) $p[0] && $row['operation'] === (string) $p[1] && $row['status'] === 'reserved' && $row['credits_deducted'] > 0 && $row['age'] > (int) $p[2]) {
                        $this->result[] = ['id' => $id];
                    }
                }
                return true;
            case "SELECT s.id AS subscription_id, s.plan_id, p.code, p.name_ar, p.name_en, p.price_sar, p.video_limit, p.video_max_seconds, p.image_limit, p.watermark, p.is_active FROM subscriptions s INNER JOIN plans p ON p.id = s.plan_id WHERE s.user_id = ? AND s.status = 'active' ORDER BY s.id DESC LIMIT 1 FOR UPDATE":
                if ($db->plan !== null) {
                    $this->result = [$db->plan];
                }
                return true;
            case "SELECT COALESCE(SUM(units), 0) FROM usage_events WHERE user_id = ? AND operation = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND (status = 'completed' OR (status = 'reserved' AND created_at >= DATE_SUB(NOW(), INTERVAL 20 MINUTE)))":
                $count = 0;
                foreach ($db->events as $row) {
                    if ($row['user_id'] === (int) $p[0] && $row['operation'] === (string) $p[1] && ($row['status'] === 'completed' || ($row['status'] === 'reserved' && $row['age'] < 1200))) {
                        $count++;
                    }
                }
                $this->result = [[0 => $count]];
                return true;
        }
        if (array_key_exists($this->sql, $db->reads)) {
            $this->result = $db->reads[$this->sql];
            return true;
        }
        if (in_array($this->sql, $db->writable, true)) {
            $db->writes[] = ['sql' => $this->sql, 'params' => $p];
            $this->affected = 1;
            return true;
        }
        throw new LogicException('Unexpected SQL in test: ' . $this->sql);
    }

    public function rowCount(): int
    {
        return $this->affected;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return array_shift($this->result) ?? false;
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $row = array_shift($this->result);
        return is_array($row) ? (array_values($row)[$column] ?? false) : false;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $rows = $this->result;
        $this->result = [];
        return $rows;
    }

    public function getIterator(): Iterator
    {
        $rows = $this->result;
        $this->result = [];
        return new ArrayIterator($rows);
    }
}
