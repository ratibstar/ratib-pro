<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/payment-receipts.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/payment-receipts.php`.
 */
/**
 * Payment Receipts API Endpoint
 * 
 * MODERNIZED: Now uses ReceiptPaymentVoucherManager class
 * Guardian-protected, audit-ready, production-ready
 * 
 * Backward compatible with existing frontend code
 * 
 * @package Accounting
 * @version 2.0.0
 */

// Suppress any output before JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start(); // Start output buffering to catch any unexpected output

// Ensure we always send JSON on fatal/exit (e.g. empty response causes "Unexpected end of JSON input" on client)
$payment_receipts_json_sent = false;
register_shutdown_function(function () use (&$payment_receipts_json_sent) {
    if ($payment_receipts_json_sent) {
        return;
    }
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_level()) {
            @ob_clean();
        }
        @header('Content-Type: application/json');
        http_response_code(500);
        $errMsg = $e['message'] ?? '';
        $errFile = isset($e['file']) ? basename($e['file']) : '';
        $errLine = $e['line'] ?? 0;
        echo json_encode([
            'success' => false,
            'message' => $errMsg ? ('Server error: ' . $errMsg) : 'Server error while processing receipt. Check server logs.',
            'error' => $errMsg,
            'file' => $errFile,
            'line' => $errLine
        ]);
    }
});

require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
if (file_exists(__DIR__ . '/../core/date-helper.php')) { require_once __DIR__ . '/../core/date-helper.php'; }
elseif (file_exists(__DIR__ . '/core/date-helper.php')) { require_once __DIR__ . '/core/date-helper.php'; }
if (!function_exists('formatDateForDatabase')) {
    function formatDateForDatabase($s) { if (empty($s)) return null; $s = trim($s); if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return explode(' ', $s)[0]; if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $s, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]); $t = strtotime($s); return $t ? date('Y-m-d', $t) : null; }
}
require_once __DIR__ . '/core/ReceiptPaymentVoucherManager.php';

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get PDO connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    $payment_receipts_json_sent = true;
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// Check permissions
if ($method === 'GET') {
    enforceApiPermission('receipt-vouchers', 'view');
} elseif ($method === 'POST') {
    enforceApiPermission('receipt-vouchers', 'create');
} elseif ($method === 'PUT') {
    enforceApiPermission('receipt-vouchers', 'update');
} elseif ($method === 'DELETE') {
    enforceApiPermission('receipt-vouchers', 'delete');
}

try {
    // Initialize manager for receipt vouchers
    $manager = new ReceiptPaymentVoucherManager($pdo, 'receipt', $_SESSION['user_id']);
    
    if ($method === 'GET') {
        $receiptId = isset($_GET['id']) ? intval($_GET['id']) : null;
        $dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : null;
        $dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : null;
        $year = isset($_GET['year']) ? intval($_GET['year']) : null;
        
        if ($receiptId) {
            // Get single receipt voucher
            $result = $manager->get($receiptId);
            
            if ($result['success'] && $result['voucher']) {
                // Transform to match old API format for backward compatibility
                $voucher = $result['voucher'];
                // Cast to int or null so frontend always gets number/null (not empty string)
                $bankId = isset($voucher['bank_account_id']) && $voucher['bank_account_id'] !== '' && $voucher['bank_account_id'] !== null
                    ? (int) $voucher['bank_account_id'] : null;
                $acctId = isset($voucher['account_id']) && $voucher['account_id'] !== '' && $voucher['account_id'] !== null
                    ? (int) $voucher['account_id'] : null;
                $custId = isset($voucher['customer_id']) && $voucher['customer_id'] !== '' && $voucher['customer_id'] !== null
                    ? (int) $voucher['customer_id'] : null;
                $collectedId = isset($voucher['collected_from_account_id']) && $voucher['collected_from_account_id'] !== '' && $voucher['collected_from_account_id'] !== null
                    ? (int) $voucher['collected_from_account_id'] : null;
                $entityType = isset($voucher['entity_type']) && $voucher['entity_type'] !== '' ? trim($voucher['entity_type']) : null;
                $entityId = isset($voucher['entity_id']) && $voucher['entity_id'] !== '' && $voucher['entity_id'] !== null ? (int) $voucher['entity_id'] : null;
                $cashOptionVal = $bankId ? 'bank_' . $bankId : ($acctId ? 'gl_' . $acctId : (isset($voucher['bank_account_id']) && ($voucher['bank_account_id'] === 0 || $voucher['bank_account_id'] === '0') ? '0' : ''));
                $collOptionVal = $custId ? 'customer_' . $custId : ($collectedId ? 'gl_' . $collectedId : (($entityType && $entityId && in_array(strtolower($entityType), ['agent', 'subagent', 'worker', 'hr', 'accounting'], true)) ? strtolower($entityType) . '_' . $entityId : ''));
                $receipt = [
                    'id' => $voucher['id'],
                    'receipt_number' => $voucher['voucher_number'],
                    'payment_date' => $voucher['voucher_date'],
                    'payment_method' => $voucher['payment_method'],
                    'amount' => $voucher['amount'],
                    'currency' => $voucher['currency'],
                    'bank_account_id' => $bankId,
                    'cash_account_id' => $bankId,
                    'account_id' => $acctId,
                    'source_account_id' => isset($voucher['source_account_id']) && $voucher['source_account_id'] !== '' && $voucher['source_account_id'] !== null ? (int) $voucher['source_account_id'] : null,
                    'cheque_number' => $voucher['cheque_number'],
                    'reference_number' => $voucher['reference_number'],
                    'customer_id' => $custId,
                    'collected_from_account_id' => $collectedId,
                    'collected_from_id' => $collectedId,
                    'entity_type' => $voucher['entity_type'],
                    'entity_id' => $voucher['entity_id'],
                    'cost_center_id' => $voucher['cost_center_id'],
                    'status' => $voucher['status'],
                    'notes' => $voucher['notes'] ?? $voucher['description'],
                    'description' => $voucher['description'] ?? $voucher['notes'],
                    'vat_report' => $voucher['vat_report'] ?? '0',
                    'created_by' => $voucher['created_by'],
                    'created_at' => $voucher['created_at'],
                    'updated_at' => $voucher['updated_at'],
                    'journal_entry_id' => $voucher['journal_entry_id'] ?? null,
                    'cash_account_option_value' => $cashOptionVal,
                    'collected_from_option_value' => $collOptionVal
                ];
                
                // Add dynamic fields if present
                if (!empty($voucher['dynamic_fields'])) {
                    foreach ($voucher['dynamic_fields'] as $key => $value) {
                        $receipt[$key] = $value;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'receipt' => $receipt
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => $result['message'] ?? 'Receipt not found'
                ]);
            }
        } else {
            // List receipts with filters
            $filters = [];
            if ($dateFrom) $filters['date_from'] = $dateFrom;
            if ($dateTo) $filters['date_to'] = $dateTo;
            if ($year) {
                $filters['date_from'] = "{$year}-01-01";
                $filters['date_to'] = "{$year}-12-31";
            }
            
            $result = $manager->list($filters, 1, 1000); // Get all for backward compatibility
            
            if ($result['success']) {
                // Transform vouchers to match old API format
                $receipts = [];
                foreach ($result['vouchers'] as $voucher) {
                    try {
                        $receipt = [
                            'id' => $voucher['id'],
                            'receipt_number' => $voucher['voucher_number'],
                            'payment_date' => $voucher['voucher_date'],
                            'payment_method' => $voucher['payment_method'],
                            'amount' => $voucher['amount'],
                            'currency' => $voucher['currency'],
                            'bank_account_id' => $voucher['bank_account_id'],
                            'cash_account_id' => $voucher['bank_account_id'],
                            'account_id' => $voucher['account_id'] ?? null,
                            'cheque_number' => $voucher['cheque_number'],
                            'reference_number' => $voucher['reference_number'],
                            'customer_id' => $voucher['customer_id'],
                            'collected_from_account_id' => $voucher['collected_from_account_id'] ?? null,
                            'collected_from_id' => $voucher['collected_from_account_id'] ?? null,
                            'entity_type' => $voucher['entity_type'],
                            'entity_id' => $voucher['entity_id'],
                            'cost_center_id' => $voucher['cost_center_id'],
                            'status' => $voucher['status'],
                            'notes' => $voucher['notes'] ?? $voucher['description'],
                            'description' => $voucher['description'] ?? $voucher['notes'],
                            'created_by' => $voucher['created_by'],
                            'created_at' => $voucher['created_at'],
                            'updated_at' => $voucher['updated_at']
                        ];
                        
                        // Add dynamic fields
                        if (!empty($voucher['dynamic_fields'])) {
                            foreach ($voucher['dynamic_fields'] as $key => $value) {
                                $receipt[$key] = $value;
                            }
                        }
                        
                        // Try to get customer name, bank name, cost center name for backward compatibility
                        if (!empty($voucher['customer_id'])) {
                            $customerStmt = $pdo->prepare("SELECT customer_name FROM accounting_customers WHERE id = ? LIMIT 1");
                            $customerStmt->execute([$voucher['customer_id']]);
                            $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
                            $receipt['customer_name'] = $customer['customer_name'] ?? 'N/A';
                        } else {
                            $receipt['customer_name'] = 'N/A';
                        }
                        
                        if (!empty($voucher['bank_account_id'])) {
                            $bankStmt = $pdo->prepare("SELECT account_name, bank_name FROM accounting_banks WHERE id = ? LIMIT 1");
                            $bankStmt->execute([$voucher['bank_account_id']]);
                            $bank = $bankStmt->fetch(PDO::FETCH_ASSOC);
                            $receipt['bank_account_name'] = $bank['account_name'] ?? $bank['bank_name'] ?? 'Cash';
                        } else {
                            $receipt['bank_account_name'] = 'Cash';
                        }
                        
                        if (!empty($voucher['cost_center_id'])) {
                            $ccStmt = $pdo->prepare("SELECT code, name FROM cost_centers WHERE id = ? LIMIT 1");
                            $ccStmt->execute([$voucher['cost_center_id']]);
                            $cc = $ccStmt->fetch(PDO::FETCH_ASSOC);
                            if ($cc) {
                                $receipt['cost_center_name'] = !empty($cc['code']) ? "{$cc['code']} - {$cc['name']}" : $cc['name'];
                            } else {
                                $receipt['cost_center_name'] = '';
                            }
                        } else {
                            $receipt['cost_center_name'] = '';
                        }
                        
                        $receipts[] = $receipt;
                    } catch (Throwable $e) {
                        error_log('Payment Receipts API list: skip voucher id ' . ($voucher['id'] ?? '?') . ': ' . $e->getMessage());
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'receipts' => $receipts
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'receipts' => [],
                    'message' => $result['message'] ?? 'Failed to retrieve receipts'
                ]);
            }
        }
        
    } elseif ($method === 'POST') {
        // Create new receipt voucher
        $rawInput = file_get_contents('php://input');
        $data = is_string($rawInput) && $rawInput !== '' ? json_decode($rawInput, true) : null;
        if (!is_array($data)) {
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid or empty request body. Send JSON with at least payment_date, amount, bank_account_id or account_id, and customer_id or collected_from_account_id.']);
            exit;
        }
        
        // Transform old API format to new format
        // Handle customer_id - can be customer_X, gl_X, bank_X, agent_X, subagent_X, worker_X, hr_X, or 0
        $customerId = null;
        $collectedFromAccountId = null;
        $entityTypeForVoucher = isset($data['entity_type']) && $data['entity_type'] !== '' ? trim($data['entity_type']) : null;
        $entityIdForVoucher = isset($data['entity_id']) && $data['entity_id'] !== '' && $data['entity_id'] !== null ? intval($data['entity_id']) : null;
        if (isset($data['collected_from_bank_id']) && $data['collected_from_bank_id'] !== '' && $data['collected_from_bank_id'] !== null) {
            $bankId = intval($data['collected_from_bank_id']);
            $bankStmt = $pdo->prepare("SELECT account_id FROM accounting_banks WHERE id = ? LIMIT 1");
            $bankStmt->execute([$bankId]);
            $bankRow = $bankStmt->fetch(PDO::FETCH_ASSOC);
            $collectedFromAccountId = ($bankRow && isset($bankRow['account_id']) && $bankRow['account_id']) ? (int) $bankRow['account_id'] : null;
        } else if (isset($data['customer_id']) && $data['customer_id'] !== '' && $data['customer_id'] !== null) {
            if (is_string($data['customer_id']) && strpos($data['customer_id'], 'customer_') === 0) {
                $customerId = intval(str_replace('customer_', '', $data['customer_id']));
            } else if (is_string($data['customer_id']) && strpos($data['customer_id'], 'gl_') === 0) {
                $collectedFromAccountId = intval(str_replace('gl_', '', $data['customer_id']));
            } else if (is_string($data['customer_id']) && strpos($data['customer_id'], 'bank_') === 0) {
                $bankId = intval(str_replace('bank_', '', $data['customer_id']));
                $bankStmt = $pdo->prepare("SELECT account_id FROM accounting_banks WHERE id = ? LIMIT 1");
                $bankStmt->execute([$bankId]);
                $bankRow = $bankStmt->fetch(PDO::FETCH_ASSOC);
                $collectedFromAccountId = ($bankRow && isset($bankRow['account_id']) && $bankRow['account_id']) ? (int) $bankRow['account_id'] : null;
            } else if (preg_match('/^(agent|subagent|worker|hr|accounting)_(\d+)$/i', (string) $data['customer_id'], $m)) {
                $customerId = null;
                $collectedFromAccountId = null;
                $entityTypeForVoucher = strtolower($m[1]);
                $entityIdForVoucher = (int) $m[2];
            } else if ($data['customer_id'] === '0' || $data['customer_id'] === 0) {
                $customerId = 0;
            } else {
                $customerId = intval($data['customer_id']);
            }
        }
        if (isset($data['entity_type']) && $data['entity_type'] !== '') {
            $entityTypeForVoucher = trim($data['entity_type']);
        }
        if (isset($data['entity_id']) && $data['entity_id'] !== '' && $data['entity_id'] !== null) {
            $entityIdForVoucher = intval($data['entity_id']);
        }
        // Handle bank_account_id - can be bank_X, gl_X, 0, or numeric
        $bankAccountId = null;
        $accountIdForCash = null;
        if (isset($data['bank_account_id']) && $data['bank_account_id'] !== '' && $data['bank_account_id'] !== null) {
            if (is_string($data['bank_account_id']) && strpos($data['bank_account_id'], 'bank_') === 0) {
                $bankAccountId = intval(str_replace('bank_', '', $data['bank_account_id']));
            } else if (is_string($data['bank_account_id']) && strpos($data['bank_account_id'], 'gl_') === 0) {
                $accountIdForCash = intval(str_replace('gl_', '', $data['bank_account_id']));
            } else if ($data['bank_account_id'] === 0 || $data['bank_account_id'] === '0') {
                $bankAccountId = 0;
            } else {
                $bankAccountId = intval($data['bank_account_id']);
            }
        }
        
        $voucherData = [
            'voucher_date' => $data['payment_date'] ?? date('Y-m-d'),
            'amount' => floatval($data['amount'] ?? 0),
            'currency' => strtoupper($data['currency'] ?? 'SAR'),
            'payment_method' => $data['payment_method'] ?? 'Cash',
            'bank_account_id' => $bankAccountId,
            'account_id' => isset($data['account_id']) && $data['account_id'] ? intval($data['account_id']) : $accountIdForCash,
            'cheque_number' => $data['cheque_number'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'customer_id' => $customerId,
            'collected_from_account_id' => $collectedFromAccountId,
            'entity_type' => $entityTypeForVoucher,
            'entity_id' => $entityIdForVoucher,
            'cost_center_id' => isset($data['cost_center_id']) && $data['cost_center_id'] ? intval($data['cost_center_id']) : null,
            'description' => $data['description'] ?? $data['notes'] ?? null,
            'notes' => $data['notes'] ?? $data['description'] ?? null,
            'status' => $data['status'] ?? 'Draft',
            'voucher_number' => $data['receipt_number'] ?? null // Auto-generate if not provided
        ];
        
        // Handle vat_report field (if exists in old data)
        if (isset($data['vat_report'])) {
            $voucherData['vat_report'] = $data['vat_report'];
        }
        
        // Add any other fields from old API as dynamic fields
        $dynamicFields = [];
        $knownFields = ['payment_date', 'amount', 'currency', 'payment_method', 'bank_account_id', 
                       'cheque_number', 'reference_number', 'customer_id', 'entity_type', 'entity_id', 
                       'cost_center_id', 'status', 'notes', 'description', 'receipt_number', 'vat_report',
                       'account_id', 'source_account_id', 'collected_from_account_id', 'branch_id', 'fiscal_period_id'];
        foreach ($data as $key => $value) {
            if (!in_array($key, $knownFields) && !empty($value)) {
                $dynamicFields[$key] = $value;
            }
        }
        if (!empty($dynamicFields)) {
            $voucherData = array_merge($voucherData, $dynamicFields);
        }
        
        // Create voucher
        try {
            $result = $manager->create($voucherData);
            
            if ($result['success']) {
                // Get created voucher for response
                $voucher = $manager->get($result['voucher_id']);
                
                if ($voucher['success']) {
                    $voucherData = $voucher['voucher'];
                    
                    // Transform to old API format
                    $receipt = [
                        'id' => $voucherData['id'],
                        'receipt_number' => $voucherData['voucher_number'],
                        'payment_date' => $voucherData['voucher_date'],
                        'payment_method' => $voucherData['payment_method'],
                        'amount' => $voucherData['amount'],
                        'currency' => $voucherData['currency'],
                        'bank_account_id' => $voucherData['bank_account_id'],
                        'cash_account_id' => $voucherData['bank_account_id'],
                        'account_id' => $voucherData['account_id'] ?? null,
                        'cheque_number' => $voucherData['cheque_number'],
                        'reference_number' => $voucherData['reference_number'],
                        'customer_id' => $voucherData['customer_id'],
                        'collected_from_account_id' => $voucherData['collected_from_account_id'] ?? null,
                        'collected_from_id' => $voucherData['collected_from_account_id'] ?? null,
                        'entity_type' => $voucherData['entity_type'],
                        'entity_id' => $voucherData['entity_id'],
                        'cost_center_id' => $voucherData['cost_center_id'],
                        'status' => $voucherData['status'],
                        'notes' => $voucherData['notes'] ?? $voucherData['description'],
                        'description' => $voucherData['description'] ?? $voucherData['notes'],
                        'created_by' => $voucherData['created_by'],
                        'created_at' => $voucherData['created_at'],
                        'updated_at' => $voucherData['updated_at']
                    ];

                    // Add vat_report if it exists
                    if (isset($voucherData['vat_report'])) {
                        $receipt['vat_report'] = $voucherData['vat_report'];
                    }
                    
                    // Add dynamic fields
                    if (!empty($voucherData['dynamic_fields'])) {
                        foreach ($voucherData['dynamic_fields'] as $key => $value) {
                            $receipt[$key] = $value;
                        }
                    }
                    
                    // Auto-post if status is Posted, Cleared or Deposited (connect to journal/GL)
                    $journalResult = null;
                    if (in_array($voucherData['status'], ['Posted', 'Cleared', 'Deposited'])) {
                        $postResult = $manager->post($result['voucher_id']);
                        if ($postResult['success']) {
                            $journalResult = [
                                'success' => true,
                                'journal_entry_id' => $postResult['journal_entry_id'],
                                'message' => 'Journal entry created and posted to GL'
                            ];
                        }
                    }
                    
                    ob_clean(); // Clear any output before JSON
                    $payment_receipts_json_sent = true;
                    echo json_encode([
                        'success' => true,
                        'message' => 'Receipt created successfully',
                        'receipt_id' => $result['voucher_id'],
                        'receipt_number' => $result['voucher_number'],
                        'receipt' => $receipt,
                        'journal_entry' => $journalResult,
                        'correlation_id' => $result['correlation_id'] ?? null
                    ]);
                    exit;
                } else {
                    $payment_receipts_json_sent = true;
                    ob_clean();
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => $voucher['message'] ?? 'Failed to retrieve created receipt'
                    ]);
                    exit;
                }
            } else {
                $payment_receipts_json_sent = true;
                ob_clean();
                http_response_code(400);
                echo json_encode($result);
                exit;
            }
        } catch (Exception $e) {
            $payment_receipts_json_sent = true;
            ob_clean();
            http_response_code(500);
            error_log('Payment Receipts API Error (POST): ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create receipt: ' . $e->getMessage()
            ]);
            exit;
        }
        
    } elseif ($method === 'PUT') {
        // Update receipt voucher
        $receiptId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$receiptId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Receipt ID required']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Transform old API format to new format (parse bank_X, gl_X, customer_X like POST)
        $updateData = [];
        if (isset($data['payment_date'])) $updateData['voucher_date'] = $data['payment_date'];
        if (isset($data['amount'])) $updateData['amount'] = floatval($data['amount']);
        if (isset($data['currency'])) $updateData['currency'] = strtoupper($data['currency']);
        if (isset($data['payment_method'])) $updateData['payment_method'] = $data['payment_method'];
        if (array_key_exists('bank_account_id', $data) || array_key_exists('account_id', $data)) {
            if (($data['bank_account_id'] ?? null) === '' || ($data['bank_account_id'] ?? null) === null) {
                $updateData['bank_account_id'] = null;
                if (isset($data['account_id']) && $data['account_id'] !== '' && $data['account_id'] !== null) {
                    $updateData['account_id'] = intval($data['account_id']);
                } else {
                    $updateData['account_id'] = null;
                }
            } else if (is_string($data['bank_account_id']) && strpos($data['bank_account_id'], 'bank_') === 0) {
                $updateData['bank_account_id'] = intval(str_replace('bank_', '', $data['bank_account_id']));
                $updateData['account_id'] = null;
            } else if (is_string($data['bank_account_id']) && strpos($data['bank_account_id'], 'gl_') === 0) {
                $updateData['account_id'] = intval(str_replace('gl_', '', $data['bank_account_id']));
                $updateData['bank_account_id'] = null;
            } else if ($data['bank_account_id'] === 0 || $data['bank_account_id'] === '0') {
                $updateData['bank_account_id'] = 0;
                $updateData['account_id'] = null;
            } else {
                $updateData['bank_account_id'] = intval($data['bank_account_id']);
                $updateData['account_id'] = null;
            }
        }
        if (isset($data['cheque_number'])) $updateData['cheque_number'] = $data['cheque_number'];
        if (isset($data['reference_number'])) $updateData['reference_number'] = $data['reference_number'];
        if (array_key_exists('customer_id', $data) || array_key_exists('collected_from_account_id', $data) || array_key_exists('collected_from_bank_id', $data)) {
            if (isset($data['collected_from_bank_id']) && $data['collected_from_bank_id'] !== '' && $data['collected_from_bank_id'] !== null) {
                $bankId = intval($data['collected_from_bank_id']);
                $bankStmt = $pdo->prepare("SELECT account_id FROM accounting_banks WHERE id = ? LIMIT 1");
                $bankStmt->execute([$bankId]);
                $bankRow = $bankStmt->fetch(PDO::FETCH_ASSOC);
                $updateData['customer_id'] = null;
                $updateData['collected_from_account_id'] = ($bankRow && isset($bankRow['account_id']) && $bankRow['account_id']) ? (int) $bankRow['account_id'] : null;
            } else if (($data['customer_id'] ?? null) === '' || ($data['customer_id'] ?? null) === null) {
                $updateData['customer_id'] = null;
                if (isset($data['collected_from_account_id']) && $data['collected_from_account_id'] !== '' && $data['collected_from_account_id'] !== null) {
                    $updateData['collected_from_account_id'] = intval($data['collected_from_account_id']);
                } else {
                    $updateData['collected_from_account_id'] = null;
                }
            } else if (is_string($data['customer_id']) && strpos($data['customer_id'], 'customer_') === 0) {
                $updateData['customer_id'] = intval(str_replace('customer_', '', $data['customer_id']));
                $updateData['collected_from_account_id'] = null;
            } else if (is_string($data['customer_id']) && strpos($data['customer_id'], 'gl_') === 0) {
                $updateData['collected_from_account_id'] = intval(str_replace('gl_', '', $data['customer_id']));
                $updateData['customer_id'] = null;
            } else if (is_string($data['customer_id']) && preg_match('/^(agent|subagent|worker|hr|accounting)_(\d+)$/i', $data['customer_id'], $m)) {
                $updateData['customer_id'] = null;
                $updateData['collected_from_account_id'] = null;
                $updateData['entity_type'] = strtolower($m[1]);
                $updateData['entity_id'] = (int) $m[2];
            } else {
                $updateData['customer_id'] = intval($data['customer_id']);
                $updateData['collected_from_account_id'] = null;
            }
        }
        if (isset($data['entity_type'])) $updateData['entity_type'] = $data['entity_type'];
        if (isset($data['entity_id'])) $updateData['entity_id'] = $data['entity_id'] ? intval($data['entity_id']) : null;
        if (isset($data['cost_center_id'])) $updateData['cost_center_id'] = $data['cost_center_id'] ? intval($data['cost_center_id']) : null;
        if (isset($data['account_id']) && $data['account_id'] !== '' && $data['account_id'] !== null) {
            $updateData['account_id'] = intval($data['account_id']);
        }
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['notes'])) $updateData['notes'] = $data['notes'];
        if (isset($data['status'])) $updateData['status'] = $data['status'];
        
        // Handle vat_report
        if (isset($data['vat_report'])) {
            $updateData['vat_report'] = $data['vat_report'];
        }
        
        // Update voucher
        $result = $manager->update($receiptId, $updateData);
        
        if ($result['success']) {
            // Get updated voucher
            $voucher = $manager->get($receiptId);
            
            if ($voucher['success']) {
                $voucherData = $voucher['voucher'];
                $bankId = isset($voucherData['bank_account_id']) && $voucherData['bank_account_id'] !== '' && $voucherData['bank_account_id'] !== null ? (int) $voucherData['bank_account_id'] : null;
                $acctId = isset($voucherData['account_id']) && $voucherData['account_id'] !== '' && $voucherData['account_id'] !== null ? (int) $voucherData['account_id'] : null;
                $custId = isset($voucherData['customer_id']) && $voucherData['customer_id'] !== '' && $voucherData['customer_id'] !== null ? (int) $voucherData['customer_id'] : null;
                $collectedId = isset($voucherData['collected_from_account_id']) && $voucherData['collected_from_account_id'] !== '' && $voucherData['collected_from_account_id'] !== null ? (int) $voucherData['collected_from_account_id'] : null;
                $cashOptionVal = $bankId ? 'bank_' . $bankId : ($acctId ? 'gl_' . $acctId : (isset($voucherData['bank_account_id']) && ($voucherData['bank_account_id'] === 0 || $voucherData['bank_account_id'] === '0') ? '0' : ''));
                $collOptionVal = $custId ? 'customer_' . $custId : ($collectedId ? 'gl_' . $collectedId : '');
                
                // Check if status changed to Cleared/Deposited (auto-post)
                $oldStatus = $data['old_status'] ?? $voucherData['status'];
                $newStatus = $updateData['status'] ?? $voucherData['status'];
                $journalResult = null;
                
                if (in_array($newStatus, ['Posted', 'Cleared', 'Deposited']) && !in_array($oldStatus, ['Posted', 'Cleared', 'Deposited'])) {
                    // Auto-post if status changed to Posted/Cleared/Deposited (connect to journal/GL)
                    $postResult = $manager->post($receiptId);
                    if ($postResult['success']) {
                        $journalResult = [
                            'success' => true,
                            'journal_entry_id' => $postResult['journal_entry_id'],
                            'message' => 'Journal entry created and posted to GL'
                        ];
                    }
                }
                
                // Transform to old API format
                $receipt = [
                    'id' => $voucherData['id'],
                    'receipt_number' => $voucherData['voucher_number'],
                    'payment_date' => $voucherData['voucher_date'],
                    'payment_method' => $voucherData['payment_method'],
                    'amount' => $voucherData['amount'],
                    'currency' => $voucherData['currency'],
                    'bank_account_id' => $voucherData['bank_account_id'],
                    'cash_account_id' => $voucherData['bank_account_id'],
                    'account_id' => $voucherData['account_id'] ?? null,
                    'cheque_number' => $voucherData['cheque_number'],
                    'reference_number' => $voucherData['reference_number'],
                    'customer_id' => $voucherData['customer_id'],
                    'collected_from_account_id' => $voucherData['collected_from_account_id'] ?? null,
                    'collected_from_id' => $voucherData['collected_from_account_id'] ?? null,
                    'entity_type' => $voucherData['entity_type'],
                    'entity_id' => $voucherData['entity_id'],
                    'cost_center_id' => $voucherData['cost_center_id'],
                    'status' => $voucherData['status'],
                    'notes' => $voucherData['notes'] ?? $voucherData['description'],
                    'description' => $voucherData['description'] ?? $voucherData['notes'],
                    'created_by' => $voucherData['created_by'],
                    'created_at' => $voucherData['created_at'],
                    'updated_at' => $voucherData['updated_at'],
                    'cash_account_option_value' => $cashOptionVal,
                    'collected_from_option_value' => $collOptionVal
                ];

                // Add vat_report if present
                if (isset($voucherData['vat_report'])) {
                    $receipt['vat_report'] = $voucherData['vat_report'];
                }

                // Add dynamic fields
                if (!empty($voucherData['dynamic_fields'])) {
                    foreach ($voucherData['dynamic_fields'] as $key => $value) {
                        $receipt[$key] = $value;
                    }
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Receipt updated successfully',
                    'receipt' => $receipt,
                    'journal_entry' => $journalResult,
                    'correlation_id' => $result['correlation_id'] ?? null
                ]);
            } else {
                echo json_encode($result);
            }
        } else {
            http_response_code(400);
            echo json_encode($result);
        }
        
    } elseif ($method === 'DELETE') {
        // Delete receipt voucher
        $receiptId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$receiptId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Receipt ID required']);
            exit;
        }
        
        $result = $manager->delete($receiptId);
        http_response_code($result['success'] ? 200 : 400);
        echo json_encode($result);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    $payment_receipts_json_sent = true;
    ob_clean();
    http_response_code(500);
    error_log('Payment Receipts API Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getTraceAsString() : null
    ]);
    exit;
} catch (Error $e) {
    $payment_receipts_json_sent = true;
    ob_clean();
    http_response_code(500);
    error_log('Payment Receipts API Fatal Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getTraceAsString() : null
    ]);
    exit;
} catch (Throwable $e) {
    ob_clean();
    $payment_receipts_json_sent = true;
    http_response_code(500);
    error_log('Payment Receipts API Throwable Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred: ' . $e->getMessage()
    ]);
    exit;
}
