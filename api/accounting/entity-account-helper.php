<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/entity-account-helper.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/entity-account-helper.php`.
 */
/**
 * Helper function to automatically create GL account for an entity (agent, subagent, worker, accounting, hr)
 * Call this after creating/updating an entity to ensure it has a GL account in system accounts.
 * Supports both PDO and mysqli connections.
 * 
 * Note: Uses require_once so config.php won't be loaded twice if already included
 */
require_once __DIR__ . '/../../includes/config.php';

function ensureEntityAccount($conn, $entityType, $entityId, $entityName) {
    if (!$conn || !$entityType || !$entityName) {
        return ['success' => false, 'message' => 'Invalid parameters'];
    }
    $entityId = (int) $entityId;
    if ($entityId <= 0) {
        return ['success' => false, 'message' => 'Invalid entity ID'];
    }
    $entityName = trim((string) $entityName);
    if ($entityName === '') {
        return ['success' => false, 'message' => 'Entity name is required'];
    }

    $isPdo = $conn instanceof PDO;

    // Check if columns exist
    if ($isPdo) {
        $faCols = $conn->query("SHOW COLUMNS FROM financial_accounts");
        $faCols = $faCols ? $faCols->fetchAll(PDO::FETCH_ASSOC) : [];
    } else {
        $faCols = $conn->query("SHOW COLUMNS FROM financial_accounts");
        $rows = [];
        if ($faCols) {
            while ($c = $faCols->fetch_assoc()) {
                $rows[] = $c;
            }
        }
        $faCols = $rows;
    }
    $hasEntityType = false;
    $hasEntityId = false;
    foreach ($faCols as $c) {
        if (($c['Field'] ?? $c['field'] ?? '') === 'entity_type') $hasEntityType = true;
        if (($c['Field'] ?? $c['field'] ?? '') === 'entity_id') $hasEntityId = true;
    }
    if (!$hasEntityType) {
        $conn->query("ALTER TABLE financial_accounts ADD COLUMN entity_type VARCHAR(50) NULL DEFAULT NULL");
        $hasEntityType = true;
    }
    if (!$hasEntityId) {
        $conn->query("ALTER TABLE financial_accounts ADD COLUMN entity_id INT NULL DEFAULT NULL");
        $hasEntityId = true;
    }
    if (!$hasEntityType || !$hasEntityId) {
        return ['success' => false, 'message' => 'Failed to add entity columns'];
    }

    // Check if account already exists
    $chk = $conn->prepare("SELECT id FROM financial_accounts WHERE entity_type = ? AND entity_id = ? LIMIT 1");
    if (!$chk) {
        $err = $isPdo ? ($conn->errorInfo()[2] ?? $conn->errorInfo()[1] ?? '') : $conn->error;
        return ['success' => false, 'message' => 'Failed to prepare check query: ' . $err];
    }
    if ($isPdo) {
        $chk->execute([$entityType, $entityId]);
        if ($chk->rowCount() > 0) {
            return ['success' => true, 'message' => 'Account already exists'];
        }
    } else {
        $chk->bind_param('si', $entityType, $entityId);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $chk->close();
            return ['success' => true, 'message' => 'Account already exists'];
        }
        $chk->close();
    }

    // Determine prefix and account type
    $prefixMap = [
        'agent' => '43',
        'subagent' => '44',
        'worker' => '45',
        'hr' => '46',
        'accounting' => '47'
    ];
    $prefix = $prefixMap[$entityType] ?? '49';
    $accountType = in_array($entityType, ['agent', 'subagent']) ? 'Income' : 'Expense';
    $normalBalance = ($accountType === 'Income') ? 'Credit' : 'Debit';

    // Get next account number
    $likePattern = $prefix . '%';
    if ($isPdo) {
        $maxStmt = $conn->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(account_code, 3) AS UNSIGNED)), 0) AS mx FROM financial_accounts WHERE account_code LIKE ? AND LENGTH(account_code) >= 4");
        $maxStmt->execute([$likePattern]);
        $mr = $maxStmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $maxStmt = $conn->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING(account_code, 3) AS UNSIGNED)), 0) AS mx FROM financial_accounts WHERE account_code LIKE ? AND LENGTH(account_code) >= 4");
        $maxStmt->bind_param('s', $likePattern);
        $maxStmt->execute();
        $mr = $maxStmt->get_result()->fetch_assoc();
    }
    $nextNum = 1;
    if ($mr && isset($mr['mx'])) {
        $nextNum = (int)$mr['mx'] + 1;
    }
    $accountCode = $prefix . str_pad((string)$nextNum, 2, '0', STR_PAD_LEFT);

    // Insert account
    $ins = $conn->prepare("INSERT INTO financial_accounts (account_code, account_name, account_type, normal_balance, opening_balance, current_balance, is_active, entity_type, entity_id) VALUES (?, ?, ?, ?, 0, 0, 1, ?, ?)");
    if (!$ins) {
        $err = $isPdo ? ($conn->errorInfo()[2] ?? '') : $conn->error;
        return ['success' => false, 'message' => 'Failed to prepare INSERT: ' . $err];
    }
    if ($isPdo) {
        try {
            $ins->execute([$accountCode, $entityName, $accountType, $normalBalance, $entityType, $entityId]);
            error_log("entity-account-helper: Created account $accountCode for $entityType entity_id=$entityId name=$entityName");
            return ['success' => true, 'account_code' => $accountCode, 'message' => 'Account created successfully'];
        } catch (PDOException $e) {
            error_log("entity-account-helper: INSERT failed for $entityType entity_id=$entityId: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create account: ' . $e->getMessage()];
        }
    } else {
        $ins->bind_param('sssssi', $accountCode, $entityName, $accountType, $normalBalance, $entityType, $entityId);
        if ($ins->execute()) {
            $ins->close();
            error_log("entity-account-helper: Created account $accountCode for $entityType entity_id=$entityId name=$entityName");
            return ['success' => true, 'account_code' => $accountCode, 'message' => 'Account created successfully'];
        } else {
            $error = $conn->error;
            $ins->close();
            error_log("entity-account-helper: INSERT failed for $entityType entity_id=$entityId: $error");
            return ['success' => false, 'message' => 'Failed to create account: ' . $error];
        }
    }
}
