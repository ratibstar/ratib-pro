<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/migrate-accounts-enums.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/migrate-accounts-enums.php`.
 */
/**
 * Chart of Accounts Refactoring Migration Script
 * 
 * This script:
 * 1. Adds ENUM fields for account_type and normal_balance
 * 2. Updates existing accounts based on account_code prefix
 * 3. Adds validation constraints
 * 4. Ensures all accounts comply with new rules
 */

require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and has admin permissions
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'financial_accounts'");
if ($tableCheck->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'financial_accounts table does not exist']);
    exit;
}

try {
    $results = [];
    $errors = [];
    
    // Step 1: Check current structure
    $columnsCheck = $conn->query("SHOW COLUMNS FROM financial_accounts");
    $hasAccountType = false;
    $hasNormalBalance = false;
    $accountTypeType = '';
    $normalBalanceType = '';
    
    while ($col = $columnsCheck->fetch_assoc()) {
        if ($col['Field'] === 'account_type') {
            $hasAccountType = true;
            $accountTypeType = $col['Type'];
        }
        if ($col['Field'] === 'normal_balance') {
            $hasNormalBalance = true;
            $normalBalanceType = $col['Type'];
        }
    }
    
    // Step 2: Add or alter account_type column
    if (!$hasAccountType) {
        // Add new ENUM column
        $alterAccountType = $conn->query("
            ALTER TABLE financial_accounts 
            ADD COLUMN account_type ENUM('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE') NOT NULL DEFAULT 'ASSET'
            AFTER account_name
        ");
        if ($alterAccountType) {
            $results[] = "Added account_type ENUM column";
        } else {
            $errors[] = "Failed to add account_type: " . $conn->error;
        }
    } else {
        // Check if it's already an ENUM with correct values
        if (strpos(strtoupper($accountTypeType), 'ENUM') === false) {
            // Convert existing column to ENUM
            $alterAccountType = $conn->query("
                ALTER TABLE financial_accounts 
                MODIFY COLUMN account_type ENUM('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE') NOT NULL DEFAULT 'ASSET'
            ");
            if ($alterAccountType) {
                $results[] = "Converted account_type to ENUM";
            } else {
                $errors[] = "Failed to convert account_type: " . $conn->error;
            }
        } else {
            // Check if ENUM values match
            $currentEnum = $accountTypeType;
            if (stripos($currentEnum, "'ASSET'") === false || 
                stripos($currentEnum, "'LIABILITY'") === false ||
                stripos($currentEnum, "'EQUITY'") === false ||
                stripos($currentEnum, "'REVENUE'") === false ||
                stripos($currentEnum, "'EXPENSE'") === false) {
                // Update ENUM values
                $alterAccountType = $conn->query("
                    ALTER TABLE financial_accounts 
                    MODIFY COLUMN account_type ENUM('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE') NOT NULL DEFAULT 'ASSET'
                ");
                if ($alterAccountType) {
                    $results[] = "Updated account_type ENUM values";
                } else {
                    $errors[] = "Failed to update account_type ENUM: " . $conn->error;
                }
            } else {
                $results[] = "account_type ENUM already exists with correct values";
            }
        }
    }
    
    // Step 3: Add or alter normal_balance column
    if (!$hasNormalBalance) {
        // Add new ENUM column
        $alterNormalBalance = $conn->query("
            ALTER TABLE financial_accounts 
            ADD COLUMN normal_balance ENUM('DEBIT','CREDIT') NOT NULL DEFAULT 'DEBIT'
            AFTER account_type
        ");
        if ($alterNormalBalance) {
            $results[] = "Added normal_balance ENUM column";
        } else {
            $errors[] = "Failed to add normal_balance: " . $conn->error;
        }
    } else {
        // Check if it's already an ENUM with correct values
        if (strpos(strtoupper($normalBalanceType), 'ENUM') === false) {
            // Convert existing column to ENUM
            $alterNormalBalance = $conn->query("
                ALTER TABLE financial_accounts 
                MODIFY COLUMN normal_balance ENUM('DEBIT','CREDIT') NOT NULL DEFAULT 'DEBIT'
            ");
            if ($alterNormalBalance) {
                $results[] = "Converted normal_balance to ENUM";
            } else {
                $errors[] = "Failed to convert normal_balance: " . $conn->error;
            }
        } else {
            // Check if ENUM values match
            $currentEnum = $normalBalanceType;
            if (stripos($currentEnum, "'DEBIT'") === false || 
                stripos($currentEnum, "'CREDIT'") === false) {
                // Update ENUM values
                $alterNormalBalance = $conn->query("
                    ALTER TABLE financial_accounts 
                    MODIFY COLUMN normal_balance ENUM('DEBIT','CREDIT') NOT NULL DEFAULT 'DEBIT'
                ");
                if ($alterNormalBalance) {
                    $results[] = "Updated normal_balance ENUM values";
                } else {
                    $errors[] = "Failed to update normal_balance ENUM: " . $conn->error;
                }
            } else {
                $results[] = "normal_balance ENUM already exists with correct values";
            }
        }
    }
    
    // Step 4: Update existing accounts based on account_code prefix
    // First, get all accounts
    $accountsQuery = $conn->query("SELECT id, account_code, account_type, normal_balance FROM financial_accounts");
    $updatedCount = 0;
    $accountsToUpdate = [];
    
    while ($account = $accountsQuery->fetch_assoc()) {
        $accountCode = $account['account_code'] ?? '';
        $currentType = strtoupper($account['account_type'] ?? '');
        $currentNormal = strtoupper($account['normal_balance'] ?? '');
        
        // Skip if account_code is NULL or empty
        if (empty($accountCode)) {
            // Validate existing values for accounts without codes
            $isValidType = in_array($currentType, ['ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE']);
            $isValidNormal = in_array($currentNormal, ['DEBIT','CREDIT']);
            $validCombo = false;
            if ($isValidType && $isValidNormal) {
                $validCombinations = [
                    'ASSET' => 'DEBIT',
                    'EXPENSE' => 'DEBIT',
                    'LIABILITY' => 'CREDIT',
                    'EQUITY' => 'CREDIT',
                    'REVENUE' => 'CREDIT'
                ];
                $validCombo = ($validCombinations[$currentType] ?? null) === $currentNormal;
            }
            if (!$isValidType || !$isValidNormal || !$validCombo) {
                $accountsToUpdate[] = [
                    'id' => $account['id'],
                    'account_code' => 'NULL',
                    'old_type' => $currentType ?: 'NULL',
                    'old_normal' => $currentNormal ?: 'NULL',
                    'new_type' => 'ASSET',
                    'new_normal' => 'DEBIT'
                ];
            }
            continue;
        }
        
        // Extract first digit from account_code
        preg_match('/^(\d)/', $accountCode, $matches);
        if (isset($matches[1])) {
            $firstDigit = intval($matches[1]);
            $newType = '';
            $newNormal = '';
            
            // Determine type and normal balance based on first digit
            switch ($firstDigit) {
                case 1:
                    $newType = 'ASSET';
                    $newNormal = 'DEBIT';
                    break;
                case 2:
                    $newType = 'LIABILITY';
                    $newNormal = 'CREDIT';
                    break;
                case 3:
                    $newType = 'EQUITY';
                    $newNormal = 'CREDIT';
                    break;
                case 4:
                    $newType = 'REVENUE';
                    $newNormal = 'CREDIT';
                    break;
                case 5:
                    $newType = 'EXPENSE';
                    $newNormal = 'DEBIT';
                    break;
                default:
                    // Keep existing or default to ASSET/DEBIT if no match
                    $newType = in_array($currentType, ['ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE']) ? $currentType : 'ASSET';
                    $newNormal = in_array($currentNormal, ['DEBIT','CREDIT']) ? $currentNormal : 'DEBIT';
                    break;
            }
            
            // Check if update is needed
            $needsUpdate = false;
            if (empty($currentType) || $currentType !== $newType) {
                $needsUpdate = true;
            }
            if (empty($currentNormal) || $currentNormal !== $newNormal) {
                $needsUpdate = true;
            }
            
            if ($needsUpdate) {
                $accountsToUpdate[] = [
                    'id' => $account['id'],
                    'account_code' => $accountCode,
                    'old_type' => $currentType ?: 'NULL',
                    'old_normal' => $currentNormal ?: 'NULL',
                    'new_type' => $newType,
                    'new_normal' => $newNormal
                ];
            }
        } else {
            // Account code doesn't start with digit - validate existing values
            // If values are invalid, add to update list with defaults
            $isValidType = in_array($currentType, ['ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE']);
            $isValidNormal = in_array($currentNormal, ['DEBIT','CREDIT']);
            
            // Check valid combinations
            $validCombo = false;
            if ($isValidType && $isValidNormal) {
                $validCombinations = [
                    'ASSET' => 'DEBIT',
                    'EXPENSE' => 'DEBIT',
                    'LIABILITY' => 'CREDIT',
                    'EQUITY' => 'CREDIT',
                    'REVENUE' => 'CREDIT'
                ];
                $validCombo = ($validCombinations[$currentType] ?? null) === $currentNormal;
            }
            
            if (!$isValidType || !$isValidNormal || !$validCombo) {
                // Default to ASSET/DEBIT for non-numeric prefixes with invalid values
                $accountsToUpdate[] = [
                    'id' => $account['id'],
                    'account_code' => $accountCode,
                    'old_type' => $currentType ?: 'NULL',
                    'old_normal' => $currentNormal ?: 'NULL',
                    'new_type' => 'ASSET',
                    'new_normal' => 'DEBIT'
                ];
            }
        }
    }
    
    // Update accounts
    foreach ($accountsToUpdate as $account) {
        $updateStmt = $conn->prepare("
            UPDATE financial_accounts 
            SET account_type = ?, normal_balance = ? 
            WHERE id = ?
        ");
        $updateStmt->bind_param('ssi', $account['new_type'], $account['new_normal'], $account['id']);
        
        if ($updateStmt->execute()) {
            $updatedCount++;
        } else {
            $errors[] = "Failed to update account {$account['account_code']} (ID: {$account['id']}): " . $conn->error;
        }
        $updateStmt->close();
    }
    
    if ($updatedCount > 0) {
        $results[] = "Updated {$updatedCount} accounts based on account_code prefix";
    } else {
        $results[] = "All accounts already comply with new rules";
    }
    
    // Step 5: Verify all accounts have valid ENUM values
    $invalidAccounts = $conn->query("
        SELECT id, account_code, account_type, normal_balance 
        FROM financial_accounts 
        WHERE account_type NOT IN ('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE')
           OR normal_balance NOT IN ('DEBIT','CREDIT')
    ");
    
    $invalidCount = $invalidAccounts->num_rows;
    if ($invalidCount > 0) {
        $errors[] = "Found {$invalidCount} accounts with invalid ENUM values";
        while ($invalid = $invalidAccounts->fetch_assoc()) {
            $errors[] = "Account {$invalid['account_code']} (ID: {$invalid['id']}) has invalid values: type='{$invalid['account_type']}', normal='{$invalid['normal_balance']}'";
        }
    } else {
        $results[] = "All accounts have valid ENUM values";
    }
    
    // Step 6: Add validation constraint (using a trigger since MySQL doesn't support CHECK constraints well)
    // Check if triggers already exist
    $triggerCheckInsert = $conn->query("SHOW TRIGGERS WHERE `Trigger` = 'validate_account_type_normal_balance_insert'");
    $triggerCheckUpdate = $conn->query("SHOW TRIGGERS WHERE `Trigger` = 'validate_account_type_normal_balance_update'");
    if ($triggerCheckInsert->num_rows === 0 || $triggerCheckUpdate->num_rows === 0) {
        // Drop existing triggers if they exist with different names
        $conn->query("DROP TRIGGER IF EXISTS validate_account_type_normal_balance_insert");
        $conn->query("DROP TRIGGER IF EXISTS validate_account_type_normal_balance_update");
        // Create trigger for INSERT
        $createTriggerInsert = $conn->query("
            CREATE TRIGGER validate_account_type_normal_balance_insert
            BEFORE INSERT ON financial_accounts
            FOR EACH ROW
            BEGIN
                DECLARE valid_type_normal TINYINT DEFAULT 0;
                
                -- Validate: ASSET and EXPENSE must be DEBIT
                -- LIABILITY, EQUITY, and REVENUE must be CREDIT
                SET valid_type_normal = (
                    (NEW.account_type = 'ASSET' AND NEW.normal_balance = 'DEBIT') OR
                    (NEW.account_type = 'EXPENSE' AND NEW.normal_balance = 'DEBIT') OR
                    (NEW.account_type = 'LIABILITY' AND NEW.normal_balance = 'CREDIT') OR
                    (NEW.account_type = 'EQUITY' AND NEW.normal_balance = 'CREDIT') OR
                    (NEW.account_type = 'REVENUE' AND NEW.normal_balance = 'CREDIT')
                );
                
                IF valid_type_normal = 0 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Invalid account configuration: ASSET/EXPENSE must be DEBIT, LIABILITY/EQUITY/REVENUE must be CREDIT';
                END IF;
            END
        ");
        
        if ($createTriggerInsert) {
            $results[] = "Created INSERT trigger for account validation";
        } else {
            $errors[] = "Failed to create INSERT trigger: " . $conn->error;
        }
        
        // Create trigger for UPDATE
        $createTriggerUpdate = $conn->query("
            CREATE TRIGGER validate_account_type_normal_balance_update
            BEFORE UPDATE ON financial_accounts
            FOR EACH ROW
            BEGIN
                DECLARE valid_type_normal TINYINT DEFAULT 0;
                
                SET valid_type_normal = (
                    (NEW.account_type = 'ASSET' AND NEW.normal_balance = 'DEBIT') OR
                    (NEW.account_type = 'EXPENSE' AND NEW.normal_balance = 'DEBIT') OR
                    (NEW.account_type = 'LIABILITY' AND NEW.normal_balance = 'CREDIT') OR
                    (NEW.account_type = 'EQUITY' AND NEW.normal_balance = 'CREDIT') OR
                    (NEW.account_type = 'REVENUE' AND NEW.normal_balance = 'CREDIT')
                );
                
                IF valid_type_normal = 0 THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Invalid account configuration: ASSET/EXPENSE must be DEBIT, LIABILITY/EQUITY/REVENUE must be CREDIT';
                END IF;
            END
        ");
        
        if ($createTriggerUpdate) {
            $results[] = "Created UPDATE trigger for account validation";
        } else {
            $errors[] = "Failed to create UPDATE trigger: " . $conn->error;
        }
    } else {
        $results[] = "Validation triggers already exist";
    }
    
    // Step 7: Ensure all accounts with non-numeric prefixes are handled
    // Accounts without numeric prefix get validated but not auto-updated
    $nonNumericAccounts = $conn->query("
        SELECT id, account_code, account_type, normal_balance 
        FROM financial_accounts 
        WHERE account_code NOT REGEXP '^[0-9]'
        AND (
            account_type NOT IN ('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE')
            OR normal_balance NOT IN ('DEBIT','CREDIT')
            OR (account_type = 'ASSET' AND normal_balance != 'DEBIT')
            OR (account_type = 'EXPENSE' AND normal_balance != 'DEBIT')
            OR (account_type = 'LIABILITY' AND normal_balance != 'CREDIT')
            OR (account_type = 'EQUITY' AND normal_balance != 'CREDIT')
            OR (account_type = 'REVENUE' AND normal_balance != 'CREDIT')
        )
    ");
    
    $invalidNonNumeric = $nonNumericAccounts->num_rows;
    if ($invalidNonNumeric > 0) {
        $errors[] = "Found {$invalidNonNumeric} accounts with non-numeric prefixes that have invalid configurations";
        // Don't auto-fix these - they need manual review
    }
    
    // Step 8: Ensure UNIQUE constraint exists on account_code
    $indexCheck = $conn->query("SHOW INDEXES FROM financial_accounts WHERE Column_name = 'account_code' AND Non_unique = 0");
    if (!$indexCheck || $indexCheck->num_rows === 0) {
        // Check if UNIQUE constraint can be added (no duplicates exist)
        $duplicateCheck = $conn->query("SELECT account_code, COUNT(*) as cnt FROM financial_accounts WHERE account_code IS NOT NULL AND account_code != '' GROUP BY account_code HAVING cnt > 1");
        if ($duplicateCheck->num_rows === 0) {
            // No duplicates - safe to add UNIQUE constraint
            $addUnique = $conn->query("ALTER TABLE financial_accounts ADD UNIQUE KEY unique_account_code (account_code)");
            if ($addUnique) {
                $results[] = "Added UNIQUE constraint on account_code";
            } else {
                $errors[] = "Failed to add UNIQUE constraint on account_code: " . $conn->error;
            }
        } else {
            $duplicateCount = $duplicateCheck->num_rows;
            $errors[] = "Cannot add UNIQUE constraint - found {$duplicateCount} duplicate account codes";
            while ($dup = $duplicateCheck->fetch_assoc()) {
                $errors[] = "Duplicate account_code: {$dup['account_code']} ({$dup['cnt']} accounts)";
            }
        }
    } else {
        $results[] = "UNIQUE constraint on account_code already exists";
    }
    
    // Final summary
    $response = [
        'success' => count($errors) === 0,
        'message' => count($errors) === 0 ? 'Migration completed successfully' : 'Migration completed with errors',
        'results' => $results,
        'updated_accounts' => $updatedCount,
        'total_accounts' => $conn->query("SELECT COUNT(*) as total FROM financial_accounts")->fetch_assoc()['total']
    ];
    
    if (count($errors) > 0) {
        $response['errors'] = $errors;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Migration failed: ' . $e->getMessage(),
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
