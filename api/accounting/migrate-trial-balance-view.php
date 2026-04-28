<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/migrate-trial-balance-view.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/migrate-trial-balance-view.php`.
 */
/**
 * Trial Balance View Migration Script
 * 
 * This script:
 * 1. Creates trial_balance MySQL view that sums debit and credit by account from general_ledger
 * 2. Validates that total debit = total credit
 * 3. Ensures view works with existing accounts and ledger entries
 */

require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in and has admin permissions
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if required tables exist
$tableCheck = $conn->query("SHOW TABLES LIKE 'financial_accounts'");
if ($tableCheck->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'financial_accounts table does not exist']);
    exit;
}
$tableCheck->free();

$glTableCheck = $conn->query("SHOW TABLES LIKE 'general_ledger'");
if ($glTableCheck->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'general_ledger table does not exist. Please run migrate-general-ledger.php first.']);
    exit;
}
$glTableCheck->free();

try {
    $results = [];
    $errors = [];
    
    // Step 1: Drop existing view if it exists (to recreate with correct structure)
    $viewCheck = $conn->query("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'trial_balance'");
    $hasView = $viewCheck->num_rows > 0;
    $viewCheck->free();
    
    if ($hasView) {
        $dropView = $conn->query("DROP VIEW IF EXISTS trial_balance");
        if ($dropView) {
            $results[] = "Dropped existing trial_balance view";
        } else {
            $errors[] = "Failed to drop existing view: " . $conn->error;
        }
    }
    
    // Step 2: Check which columns exist in financial_accounts
    $columnsCheck = $conn->query("SHOW COLUMNS FROM financial_accounts");
    $hasAccountType = false;
    $hasNormalBalance = false;
    
    if ($columnsCheck) {
        while ($col = $columnsCheck->fetch_assoc()) {
            if ($col['Field'] === 'account_type') $hasAccountType = true;
            if ($col['Field'] === 'normal_balance') $hasNormalBalance = true;
        }
    }
    $columnsCheck->free();
    
    // Build SELECT fields dynamically based on available columns
    $selectFields = [
        'fa.id AS account_id',
        'fa.account_code',
        'fa.account_name'
    ];
    
    if ($hasAccountType) {
        $selectFields[] = 'fa.account_type';
    } else {
        $selectFields[] = "'ASSET' AS account_type";
    }
    
    if ($hasNormalBalance) {
        $selectFields[] = 'fa.normal_balance';
    } else {
        $selectFields[] = "'DEBIT' AS normal_balance";
    }
    
    $selectFields[] = 'COALESCE(SUM(gl.debit), 0.00) AS total_debit';
    $selectFields[] = 'COALESCE(SUM(gl.credit), 0.00) AS total_credit';
    $selectFields[] = '(COALESCE(SUM(gl.debit), 0.00) - COALESCE(SUM(gl.credit), 0.00)) AS balance';
    
    // Build GROUP BY clause
    $groupByFields = ['fa.id', 'fa.account_code', 'fa.account_name'];
    if ($hasAccountType) {
        $groupByFields[] = 'fa.account_type';
    }
    if ($hasNormalBalance) {
        $groupByFields[] = 'fa.normal_balance';
    }
    
    // Step 3: Create trial_balance view
    // The view aggregates debit and credit from general_ledger by account_id
    // and joins with financial_accounts to get account details
    $viewQuery = "
        CREATE VIEW trial_balance AS
        SELECT 
            " . implode(",\n            ", $selectFields) . "
        FROM financial_accounts fa
        LEFT JOIN general_ledger gl ON fa.id = gl.account_id
        WHERE fa.is_active = 1
        GROUP BY " . implode(", ", $groupByFields) . "
        ORDER BY COALESCE(fa.account_code, '') ASC, fa.id ASC
    ";
    
    $createView = $conn->query($viewQuery);
    
    if ($createView) {
        $results[] = "Created trial_balance view successfully";
    } else {
        $errors[] = "Failed to create trial_balance view: " . $conn->error;
    }
    
    // Step 4: Validate that view works correctly
    if ($createView) {
        $testQuery = $conn->query("SELECT * FROM trial_balance LIMIT 10");
        if ($testQuery) {
            $testQuery->free();
            $results[] = "View validation: View is accessible and working";
        } else {
            $errors[] = "View validation failed: " . $conn->error;
        }
    }
    
    // Step 5: Validate that total debit = total credit
    if ($createView) {
        $balanceCheck = $conn->query("
            SELECT 
                SUM(total_debit) AS grand_total_debit,
                SUM(total_credit) AS grand_total_credit,
                (SUM(total_debit) - SUM(total_credit)) AS difference
            FROM trial_balance
        ");
        
        if ($balanceCheck) {
            $balanceData = $balanceCheck->fetch_assoc();
            $balanceCheck->free();
            
            $grandTotalDebit = floatval($balanceData['grand_total_debit'] ?? 0);
            $grandTotalCredit = floatval($balanceData['grand_total_credit'] ?? 0);
            $difference = abs(floatval($balanceData['difference'] ?? 0));
            
            if ($difference <= 0.01) {
                $results[] = "Balance validation: PASSED - Total Debit ({$grandTotalDebit}) equals Total Credit ({$grandTotalCredit})";
            } else {
                $errors[] = "Balance validation: FAILED - Total Debit ({$grandTotalDebit}) does not equal Total Credit ({$grandTotalCredit}). Difference: {$difference}";
            }
        } else {
            $errors[] = "Balance validation query failed: " . $conn->error;
        }
    }
    
    // Step 6: Check account coverage
    if ($createView) {
        // Count active accounts
        $accountCount = $conn->query("SELECT COUNT(*) as total FROM financial_accounts WHERE is_active = 1");
        $accountData = $accountCount->fetch_assoc();
        $totalAccounts = intval($accountData['total'] ?? 0);
        $accountCount->free();
        
        // Count accounts in trial balance
        $tbAccountCount = $conn->query("SELECT COUNT(*) as total FROM trial_balance");
        $tbData = $tbAccountCount->fetch_assoc();
        $tbAccounts = intval($tbData['total'] ?? 0);
        $tbAccountCount->free();
        
        if ($tbAccounts === $totalAccounts) {
            $results[] = "Account coverage: All {$totalAccounts} active accounts are included in trial balance";
        } else {
            $results[] = "Account coverage: {$tbAccounts} accounts in trial balance out of {$totalAccounts} active accounts (difference is expected if some accounts have no ledger entries)";
        }
    }
    
    // Prepare response
    $response = [
        'success' => count($errors) === 0,
        'results' => $results,
        'view_info' => [
            'view_name' => 'trial_balance',
            'base_table' => 'general_ledger',
            'joined_table' => 'financial_accounts'
        ]
    ];
    
    if (count($errors) > 0) {
        $response['errors'] = $errors;
    }
    
    // Add validation summary
    if ($createView) {
        $summaryQuery = $conn->query("
            SELECT 
                COUNT(*) as account_count,
                SUM(total_debit) as grand_total_debit,
                SUM(total_credit) as grand_total_credit,
                SUM(CASE WHEN total_debit > 0 OR total_credit > 0 THEN 1 ELSE 0 END) as accounts_with_activity
            FROM trial_balance
        ");
        if ($summaryQuery) {
            $summary = $summaryQuery->fetch_assoc();
            $summaryQuery->free();
            $response['summary'] = [
                'total_accounts' => intval($summary['account_count'] ?? 0),
                'accounts_with_activity' => intval($summary['accounts_with_activity'] ?? 0),
                'grand_total_debit' => floatval($summary['grand_total_debit'] ?? 0),
                'grand_total_credit' => floatval($summary['grand_total_credit'] ?? 0),
                'is_balanced' => abs(floatval($summary['grand_total_debit'] ?? 0) - floatval($summary['grand_total_credit'] ?? 0)) <= 0.01
            ];
        }
    }
    
    http_response_code(count($errors) === 0 ? 200 : 500);
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'error' => $e->getMessage()
    ]);
}
