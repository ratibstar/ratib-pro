<?php
/**
 * One-off / maintenance: sync financial_accounts.account_name from live entity tables
 * (agents, subagents, workers, HR employees, partner agencies, accounting users).
 *
 * Usage (browser or curl, same session as app):
 *   GET  .../sync-entity-account-names.php              — dry-run preview (needs view_chart_accounts)
 *   POST .../sync-entity-account-names.php
 *        Content-Type: application/json
 *        Body: {"execute":true}                        — apply updates (needs edit_account)
 */

require_once __DIR__ . '/../core/api-permission-helper.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * @param mysqli $conn
 * @return bool
 */
function sync_entity_accounts_table_ok($conn)
{
    $r = $conn->query("SHOW TABLES LIKE 'financial_accounts'");
    return $r && $r->num_rows > 0;
}

/**
 * @param mysqli $conn
 * @param string $table
 * @return bool
 */
function sync_entity_accounts_table_exists($conn, $table)
{
    $t = $conn->real_escape_string($table);
    $r = $conn->query("SHOW TABLES LIKE '$t'");
    return $r && $r->num_rows > 0;
}

/**
 * @param mysqli $conn
 * @param string $table
 * @param string $col
 * @return bool
 */
function sync_entity_accounts_column_exists($conn, $table, $col)
{
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($col);
    $r = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $r && $r->num_rows > 0;
}

/**
 * Resolve display name for an entity linked row.
 *
 * @param mysqli $conn
 * @param string $entityType
 * @param int $entityId
 * @return string|null trimmed name or null if not found
 */
function sync_entity_accounts_resolve_name($conn, $entityType, $entityId)
{
    $entityId = (int) $entityId;
    if ($entityId <= 0) {
        return null;
    }
    $et = strtolower(trim((string) $entityType));

    $candidates = [];
    if ($et === 'agent') {
        $candidates[] = ['table' => 'agents', 'pk' => 'id', 'nameCols' => ['agent_name', 'full_name', 'name']];
    } elseif ($et === 'subagent') {
        $candidates[] = ['table' => 'subagents', 'pk' => 'id', 'nameCols' => ['subagent_name', 'full_name', 'name']];
    } elseif ($et === 'worker') {
        $candidates[] = ['table' => 'workers', 'pk' => 'id', 'nameCols' => ['worker_name', 'full_name', 'name']];
    } elseif ($et === 'partner_agency') {
        $candidates[] = ['table' => 'partner_agencies', 'pk' => 'id', 'nameCols' => ['name']];
    } elseif ($et === 'hr' || $et === 'employee') {
        $candidates[] = ['table' => 'hr_employees', 'pk' => 'id', 'nameCols' => ['employee_name', 'full_name', 'name']];
        $candidates[] = ['table' => 'employees', 'pk' => 'id', 'nameCols' => ['name', 'employee_name', 'full_name']];
    } elseif ($et === 'accounting') {
        $candidates[] = ['table' => 'users', 'pk' => 'user_id', 'nameCols' => ['username']];
    } else {
        return null;
    }

    foreach ($candidates as $cfg) {
        $table = $cfg['table'];
        if (!sync_entity_accounts_table_exists($conn, $table)) {
            continue;
        }
        $pk = $cfg['pk'];
        if (!sync_entity_accounts_column_exists($conn, $table, $pk)) {
            continue;
        }
        foreach ($cfg['nameCols'] as $nameCol) {
            if (!sync_entity_accounts_column_exists($conn, $table, $nameCol)) {
                continue;
            }
            $sql = 'SELECT `' . $conn->real_escape_string($nameCol) . '` AS n FROM `' . $conn->real_escape_string($table)
                . '` WHERE `' . $conn->real_escape_string($pk) . '` = ? LIMIT 1';
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                continue;
            }
            $stmt->bind_param('i', $entityId);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            if ($row && isset($row['n'])) {
                $n = trim((string) $row['n']);
                if ($n !== '') {
                    return $n;
                }
            }
            break;
        }
    }

    return null;
}

try {
    global $conn;
    if (!$conn instanceof mysqli) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection not available']);
        exit;
    }

    if (!sync_entity_accounts_table_ok($conn)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'financial_accounts table not found']);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'GET' && $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed. Use GET (preview) or POST with {"execute":true}.']);
        exit;
    }

    $execute = false;
    if ($method === 'POST') {
        $raw = file_get_contents('php://input');
        $body = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($body) || empty($body['execute'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'POST requires JSON body: {"execute":true}. Use GET for a dry-run preview (no writes).',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $execute = true;
    }

    if ($execute) {
        enforceApiPermission('accounts', 'update');
    } else {
        enforceApiPermission('accounts', 'view');
    }

    $hasEntityType = false;
    $hasEntityId = false;
    $colRes = $conn->query('SHOW COLUMNS FROM financial_accounts');
    if ($colRes) {
        while ($c = $colRes->fetch_assoc()) {
            $f = strtolower($c['Field'] ?? '');
            if ($f === 'entity_type') {
                $hasEntityType = true;
            }
            if ($f === 'entity_id') {
                $hasEntityId = true;
            }
        }
    }
    if (!$hasEntityType || !$hasEntityId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'financial_accounts missing entity_type/entity_id columns']);
        exit;
    }

    $q = "SELECT id, entity_type, entity_id, account_name FROM financial_accounts
          WHERE entity_type IS NOT NULL AND entity_type != '' AND entity_id IS NOT NULL AND entity_id > 0
          ORDER BY id";
    $res = $conn->query($q);
    if (!$res) {
        throw new RuntimeException('Query failed: ' . $conn->error);
    }

    $scanned = 0;
    $changed = 0;
    $skipped = 0;
    $samples = [];

    $upd = null;
    if ($execute) {
        $upd = $conn->prepare('UPDATE financial_accounts SET account_name = ? WHERE id = ?');
        if (!$upd) {
            throw new RuntimeException('Prepare failed: ' . $conn->error);
        }
    }

    while ($row = $res->fetch_assoc()) {
        $scanned++;
        $id = (int) ($row['id'] ?? 0);
        $et = (string) ($row['entity_type'] ?? '');
        $eid = (int) ($row['entity_id'] ?? 0);
        $current = trim((string) ($row['account_name'] ?? ''));

        $resolved = sync_entity_accounts_resolve_name($conn, $et, $eid);
        if ($resolved === null || $resolved === '') {
            $skipped++;
            continue;
        }
        if ($resolved === $current) {
            continue;
        }

        if (count($samples) < 25) {
            $samples[] = [
                'id' => $id,
                'entity_type' => $et,
                'entity_id' => $eid,
                'old_account_name' => $current,
                'new_account_name' => $resolved,
            ];
        }

        if ($execute && $upd) {
            $upd->bind_param('si', $resolved, $id);
            if ($upd->execute()) {
                $changed++;
            }
        } elseif (!$execute) {
            $changed++;
        }
    }
    $res->free();
    if ($upd instanceof mysqli_stmt) {
        $upd->close();
    }

    echo json_encode([
        'success' => true,
        'mode' => $execute ? 'execute' : 'dry_run',
        'message' => $execute
            ? "Updated {$changed} account name(s); scanned {$scanned}; skipped {$skipped} (entity missing or empty name)."
            : "Dry run: {$changed} account(s) would be renamed; scanned {$scanned}; skipped {$skipped}. POST {\"execute\":true} to apply.",
        'scanned' => $scanned,
        'updated_or_would_update' => $changed,
        'skipped' => $skipped,
        'samples' => $samples,
    ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    $msg = $e->getMessage();
    $code = (strpos($msg, 'Authentication required') !== false) ? 401 : ((strpos($msg, 'Access denied') !== false || strpos($msg, 'denied') !== false) ? 403 : 500);
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
}
