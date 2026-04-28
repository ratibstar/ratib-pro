<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/transactions/run-gaap-migration-safe.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/transactions/run-gaap-migration-safe.php`.
 */
/**
 * GAAP Chart of Accounts Migration Runner (Safe Version)
 * 
 * This script checks for existing columns before adding them,
 * avoiding all "duplicate column" errors.
 * No information_schema access required - uses SHOW COLUMNS.
 */

require_once '../../../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

header('Content-Type: application/json');

try {
    // Step 1: Ensure table exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS `financial_accounts` (
            `id` INT PRIMARY KEY AUTO_INCREMENT,
            `account_code` VARCHAR(50) NOT NULL UNIQUE,
            `account_name` VARCHAR(255) NOT NULL,
            `account_type` ENUM('Asset', 'Liability', 'Equity', 'Income', 'Expense') NOT NULL DEFAULT 'Asset',
            `normal_balance` ENUM('Debit', 'Credit') NOT NULL DEFAULT 'Debit',
            `parent_id` INT NULL,
            `opening_balance` DECIMAL(15,2) DEFAULT 0.00,
            `current_balance` DECIMAL(15,2) DEFAULT 0.00,
            `description` TEXT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            `is_system_account` TINYINT(1) DEFAULT 0,
            `currency` VARCHAR(3) DEFAULT 'SAR',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_account_code` (`account_code`),
            INDEX `idx_account_type` (`account_type`),
            INDEX `idx_parent_id` (`parent_id`),
            INDEX `idx_is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Step 2: Get existing columns
    $existingColumns = [];
    $columnsResult = $conn->query("SHOW COLUMNS FROM `financial_accounts`");
    if ($columnsResult) {
        while ($row = $columnsResult->fetch_assoc()) {
            $existingColumns[] = $row['Field'];
        }
    }
    
    // Step 3: Get existing indexes
    $existingIndexes = [];
    $indexesResult = $conn->query("SHOW INDEXES FROM `financial_accounts`");
    if ($indexesResult) {
        while ($row = $indexesResult->fetch_assoc()) {
            if (!in_array($row['Key_name'], $existingIndexes)) {
                $existingIndexes[] = $row['Key_name'];
            }
        }
    }
    
    $added = [];
    $skipped = [];
    $errors = [];
    
    // Step 4: Add missing columns
    $columnsToAdd = [
        'account_type' => "ENUM('Asset', 'Liability', 'Equity', 'Income', 'Expense') NOT NULL DEFAULT 'Asset' AFTER `account_name`",
        'normal_balance' => "ENUM('Debit', 'Credit') NOT NULL DEFAULT 'Debit' AFTER `account_type`",
        'parent_id' => "INT NULL AFTER `normal_balance`",
        'opening_balance' => "DECIMAL(15,2) DEFAULT 0.00 AFTER `parent_id`",
        'current_balance' => "DECIMAL(15,2) DEFAULT 0.00 AFTER `opening_balance`",
        'description' => "TEXT NULL AFTER `current_balance`",
        'is_active' => "TINYINT(1) DEFAULT 1 AFTER `description`",
        'is_system_account' => "TINYINT(1) DEFAULT 0 AFTER `is_active`",
        'currency' => "VARCHAR(3) DEFAULT 'SAR' AFTER `is_system_account`",
        'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER `currency`",
        'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`",
        'entity_type' => "VARCHAR(50) NULL DEFAULT NULL AFTER `description`",
        'entity_id' => "INT NULL DEFAULT NULL AFTER `entity_type`"
    ];
    
    foreach ($columnsToAdd as $columnName => $definition) {
        if (!in_array($columnName, $existingColumns)) {
            try {
                $sql = "ALTER TABLE `financial_accounts` ADD COLUMN `$columnName` $definition";
                if ($conn->query($sql)) {
                    $added[] = "Column: $columnName";
                } else {
                    $errors[] = "Failed to add column $columnName: " . $conn->error;
                }
            } catch (Exception $e) {
                $errors[] = "Error adding column $columnName: " . $e->getMessage();
            }
        } else {
            $skipped[] = "Column: $columnName (already exists)";
        }
    }
    
    // Step 5: Add missing indexes
    $indexesToAdd = [
        'idx_account_code' => '(`account_code`)',
        'idx_account_type' => '(`account_type`)',
        'idx_parent_id' => '(`parent_id`)',
        'idx_is_active' => '(`is_active`)',
        'idx_entity_type_id' => '(`entity_type`, `entity_id`)'
    ];
    
    foreach ($indexesToAdd as $indexName => $definition) {
        if (!in_array($indexName, $existingIndexes)) {
            try {
                $sql = "ALTER TABLE `financial_accounts` ADD INDEX `$indexName` $definition";
                if ($conn->query($sql)) {
                    $added[] = "Index: $indexName";
                } else {
                    $errors[] = "Failed to add index $indexName: " . $conn->error;
                }
            } catch (Exception $e) {
                $errors[] = "Error adding index $indexName: " . $e->getMessage();
            }
        } else {
            $skipped[] = "Index: $indexName (already exists)";
        }
    }
    
    // Step 6: Fix existing data
    $dataFixed = [];
    
    // Fix normal_balance based on account_type
    $result = $conn->query("
        UPDATE `financial_accounts` 
        SET `normal_balance` = 'Debit' 
        WHERE `account_type` IN ('Asset', 'Expense') 
        AND (`normal_balance` IS NULL OR `normal_balance` != 'Debit')
    ");
    if ($result) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            $dataFixed[] = "Fixed $affected accounts: Set normal_balance to 'Debit' for Assets/Expenses";
        }
    }
    
    $result = $conn->query("
        UPDATE `financial_accounts` 
        SET `normal_balance` = 'Credit' 
        WHERE `account_type` IN ('Liability', 'Equity', 'Income') 
        AND (`normal_balance` IS NULL OR `normal_balance` != 'Credit')
    ");
    if ($result) {
        $affected = $conn->affected_rows;
        if ($affected > 0) {
            $dataFixed[] = "Fixed $affected accounts: Set normal_balance to 'Credit' for Liabilities/Equity/Income";
        }
    }
    
    // Set defaults for NULL values
    $result = $conn->query("UPDATE `financial_accounts` SET `account_type` = 'Asset' WHERE `account_type` IS NULL");
    if ($result && $conn->affected_rows > 0) {
        $dataFixed[] = "Set default account_type for " . $conn->affected_rows . " accounts";
    }
    
    $result = $conn->query("UPDATE `financial_accounts` SET `normal_balance` = 'Debit' WHERE `normal_balance` IS NULL");
    if ($result && $conn->affected_rows > 0) {
        $dataFixed[] = "Set default normal_balance for " . $conn->affected_rows . " accounts";
    }
    
    // Step 7: Ensure NOT NULL constraints
    $constraintsFixed = [];
    try {
        $conn->query("ALTER TABLE `financial_accounts` MODIFY COLUMN `account_code` VARCHAR(50) NOT NULL");
        $constraintsFixed[] = "account_code";
    } catch (Exception $e) {
        // Ignore if already set
    }
    
    try {
        $conn->query("ALTER TABLE `financial_accounts` MODIFY COLUMN `account_name` VARCHAR(255) NOT NULL");
        $constraintsFixed[] = "account_name";
    } catch (Exception $e) {
        // Ignore if already set
    }
    
    // Step 8: Verify final structure
    $verification = [];
    $columnsResult = $conn->query("SHOW COLUMNS FROM `financial_accounts`");
    if ($columnsResult) {
        while ($row = $columnsResult->fetch_assoc()) {
            $verification[] = [
                'column' => $row['Field'],
                'type' => $row['Type'],
                'nullable' => $row['Null'],
                'default' => $row['Default']
            ];
        }
    }
    
    // Check for required columns
    $requiredColumns = ['account_type', 'normal_balance'];
    $missingColumns = [];
    foreach ($requiredColumns as $reqCol) {
        $found = false;
        foreach ($verification as $col) {
            if ($col['column'] === $reqCol) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $missingColumns[] = $reqCol;
        }
    }
    
    echo json_encode([
        'success' => empty($missingColumns) && empty($errors),
        'message' => empty($missingColumns) ? 'Migration completed successfully!' : 'Migration completed with warnings',
        'added' => $added,
        'skipped' => $skipped,
        'data_fixed' => $dataFixed,
        'constraints_fixed' => $constraintsFixed,
        'errors' => $errors,
        'missing_columns' => $missingColumns,
        'table_structure' => $verification
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Migration failed: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
?>
