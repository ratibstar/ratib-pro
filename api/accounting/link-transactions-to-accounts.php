<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/link-transactions-to-accounts.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/link-transactions-to-accounts.php`.
 */
/**
 * Link Financial Transactions to Accounts
 * This script helps you link existing financial_transactions to accounts
 * 
 * Usage:
 * 1. Check current status: GET request
 * 2. Link transactions: POST request with transaction_id and account_id
 */

require_once '../../includes/config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    // GET: Check status and show examples
    if ($method === 'GET') {
        $info = [];
        
        // Check if transaction_lines table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'transaction_lines'");
        $hasTransactionLines = $tableCheck->num_rows > 0;
        $info['transaction_lines_table_exists'] = $hasTransactionLines;
        
        // Check if journal_entry_lines table exists
        $jelCheck = $conn->query("SHOW TABLES LIKE 'journal_entry_lines'");
        $hasJournalLines = $jelCheck->num_rows > 0;
        $info['journal_entry_lines_table_exists'] = $hasJournalLines;
        
        // Count transactions
        $ftCheck = $conn->query("SHOW TABLES LIKE 'financial_transactions'");
        if ($ftCheck->num_rows > 0) {
            $ftCount = $conn->query("SELECT COUNT(*) as count FROM financial_transactions");
            $info['financial_transactions_count'] = $ftCount->fetch_assoc()['count'];
            
            // Check how many are linked
            if ($hasTransactionLines) {
                $linkedCount = $conn->query("SELECT COUNT(DISTINCT ft.id) as count 
                    FROM financial_transactions ft 
                    INNER JOIN transaction_lines tl ON ft.id = tl.transaction_id");
                $info['linked_transactions_count'] = $linkedCount->fetch_assoc()['count'];
            }
        }
        
        // Count journal entries
        $jeCheck = $conn->query("SHOW TABLES LIKE 'journal_entries'");
        if ($jeCheck->num_rows > 0) {
            $jeCount = $conn->query("SELECT COUNT(*) as count FROM journal_entries");
            $info['journal_entries_count'] = $jeCount->fetch_assoc()['count'];
            
            // Check how many are linked
            if ($hasJournalLines) {
                $linkedJeCount = $conn->query("SELECT COUNT(DISTINCT je.id) as count 
                    FROM journal_entries je 
                    INNER JOIN journal_entry_lines jel ON je.id = jel.journal_entry_id 
                    WHERE jel.account_id IS NOT NULL");
                $info['linked_journal_entries_count'] = $linkedJeCount->fetch_assoc()['count'];
            }
        }
        
        // Get sample transactions
        $samples = [];
        if ($ftCheck->num_rows > 0) {
            $sampleQuery = "SELECT ft.id, ft.transaction_date, ft.description, ft.transaction_type, ft.total_amount 
                FROM financial_transactions ft 
                ORDER BY ft.transaction_date DESC, ft.id DESC
                LIMIT 50";
            $result = $conn->query($sampleQuery);
            while ($row = $result->fetch_assoc()) {
                $samples[] = [
                    'id' => $row['id'],
                    'date' => $row['transaction_date'],
                    'description' => $row['description'],
                    'type' => $row['transaction_type'],
                    'amount' => $row['total_amount']
                ];
            }
        }
        $info['sample_transactions'] = $samples;
        
        // Get available accounts
        $accounts = [];
        $accountsCheck = $conn->query("SHOW TABLES LIKE 'financial_accounts'");
        if ($accountsCheck->num_rows > 0) {
            $accountsQuery = "SELECT id, account_code, account_name, account_type FROM financial_accounts WHERE is_active = 1 ORDER BY account_code";
            $result = $conn->query($accountsQuery);
            while ($row = $result->fetch_assoc()) {
                $accounts[] = [
                    'id' => $row['id'],
                    'code' => $row['account_code'],
                    'name' => $row['account_name'],
                    'type' => $row['account_type']
                ];
            }
        }
        $info['available_accounts'] = $accounts;
        
        echo json_encode([
            'success' => true,
            'info' => $info,
            'examples' => [
                'link_transaction' => [
                    'method' => 'POST',
                    'url' => (defined('BASE_URL') ? BASE_URL : '') . '/api/accounting/link-transactions-to-accounts.php',
                    'body' => [
                        'transaction_id' => 1,
                        'account_id' => 5,
                        'transaction_type' => 'financial_transactions' // or 'journal_entries'
                    ]
                ],
                'account_mapping' => [
                    'Income transactions' => 'Should link to Income accounts (e.g., 4100 Sales Revenue)',
                    'Expense transactions' => 'Should link to Expense accounts (e.g., 5100 Salaries)',
                    'Agent payments' => 'Could link to Expense accounts',
                    'Worker payments' => 'Could link to Expense accounts'
                ]
            ]
        ]);
        exit;
    }
    
    // POST: Link a transaction to an account
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['transaction_id']) || !isset($data['account_id'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'transaction_id and account_id are required']);
            exit;
        }
        
        $transactionId = intval($data['transaction_id']);
        $accountId = intval($data['account_id']);
        $transactionType = $data['transaction_type'] ?? 'financial_transactions';
        
        // Verify account exists
        $accountCheck = $conn->prepare("SELECT id, account_code, account_name FROM financial_accounts WHERE id = ?");
        $accountCheck->bind_param('i', $accountId);
        $accountCheck->execute();
        $accountResult = $accountCheck->get_result();
        
        if ($accountResult->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Account not found']);
            exit;
        }
        $account = $accountResult->fetch_assoc();
        
        if ($transactionType === 'financial_transactions') {
            // Link via transaction_lines table
            $tableCheck = $conn->query("SHOW TABLES LIKE 'transaction_lines'");
            if ($tableCheck->num_rows === 0) {
                // Create transaction_lines table
                $createTable = "
                    CREATE TABLE IF NOT EXISTS transaction_lines (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        transaction_id INT NOT NULL,
                        account_id INT NOT NULL,
                        description VARCHAR(255),
                        debit_amount DECIMAL(15,2) DEFAULT 0.00,
                        credit_amount DECIMAL(15,2) DEFAULT 0.00,
                        FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id) ON DELETE CASCADE,
                        FOREIGN KEY (account_id) REFERENCES financial_accounts(id) ON DELETE RESTRICT,
                        UNIQUE KEY unique_transaction_account (transaction_id, account_id),
                        INDEX idx_transaction (transaction_id),
                        INDEX idx_account (account_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ";
                $conn->query($createTable);
            }
            
            // Get transaction details
            $transCheck = $conn->prepare("SELECT id, transaction_type, total_amount FROM financial_transactions WHERE id = ?");
            $transCheck->bind_param('i', $transactionId);
            $transCheck->execute();
            $transResult = $transCheck->get_result();
            
            if ($transResult->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Transaction not found']);
                exit;
            }
            $transaction = $transResult->fetch_assoc();
            
            // Determine debit/credit based on transaction type
            $debitAmount = $transaction['transaction_type'] === 'Expense' ? $transaction['total_amount'] : 0;
            $creditAmount = $transaction['transaction_type'] === 'Income' ? $transaction['total_amount'] : 0;
            
            // Check if transaction already linked
            $checkExisting = $conn->prepare("SELECT id FROM transaction_lines WHERE transaction_id = ?");
            $checkExisting->bind_param('i', $transactionId);
            $checkExisting->execute();
            $existingResult = $checkExisting->get_result();
            
            if ($existingResult->num_rows > 0) {
                // Update existing link
                $updateLine = $conn->prepare("
                    UPDATE transaction_lines 
                    SET account_id = ?, debit_amount = ?, credit_amount = ?, description = ?
                    WHERE transaction_id = ?
                ");
                $description = "Linked to {$account['account_code']} - {$account['account_name']}";
                $updateLine->bind_param('iddds', $accountId, $debitAmount, $creditAmount, $description, $transactionId);
                
                if ($updateLine->execute()) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Transaction link updated successfully',
                        'transaction_id' => $transactionId,
                        'account' => $account
                    ]);
                } else {
                    throw new Exception('Failed to update transaction link: ' . $updateLine->error);
                }
            } else {
                // Insert new link
                $insertLine = $conn->prepare("
                    INSERT INTO transaction_lines (transaction_id, account_id, debit_amount, credit_amount, description)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $description = "Linked to {$account['account_code']} - {$account['account_name']}";
                $insertLine->bind_param('iidds', $transactionId, $accountId, $debitAmount, $creditAmount, $description);
                
                if ($insertLine->execute()) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Transaction linked to account successfully',
                        'transaction_id' => $transactionId,
                        'account' => $account
                    ]);
                } else {
                    throw new Exception('Failed to link transaction: ' . $insertLine->error);
                }
            }
            
        } elseif ($transactionType === 'journal_entries') {
            // Link via journal_entry_lines table
            $tableCheck = $conn->query("SHOW TABLES LIKE 'journal_entry_lines'");
            if ($tableCheck->num_rows === 0) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'journal_entry_lines table does not exist']);
                exit;
            }
            
            // Get journal entry details
            $jeCheck = $conn->prepare("SELECT id, total_debit, total_credit FROM journal_entries WHERE id = ?");
            $jeCheck->bind_param('i', $transactionId);
            $jeCheck->execute();
            $jeResult = $jeCheck->get_result();
            
            if ($jeResult->num_rows === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Journal entry not found']);
                exit;
            }
            $journalEntry = $jeResult->fetch_assoc();
            
            // Insert or update journal_entry_line
            $insertLine = $conn->prepare("
                INSERT INTO journal_entry_lines (journal_entry_id, account_id, account_code, account_name, debit_amount, credit_amount, line_order)
                VALUES (?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE account_id = ?, account_code = ?, account_name = ?
            ");
            $insertLine->bind_param('iissddiss',
                $transactionId, $accountId, $account['account_code'], $account['account_name'],
                $journalEntry['total_debit'], $journalEntry['total_credit'],
                $accountId, $account['account_code'], $account['account_name']
            );
            
            if ($insertLine->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Journal entry linked to account successfully',
                    'entry_id' => $transactionId,
                    'account' => $account
                ]);
            } else {
                throw new Exception('Failed to link journal entry: ' . $insertLine->error);
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid transaction_type. Use "financial_transactions" or "journal_entries"']);
            exit;
        }
    }
    
} catch (Exception $e) {
    error_log('Link transactions error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>

