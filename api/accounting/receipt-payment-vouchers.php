<?php
/**
 * EN: Handles API endpoint/business logic in `api/accounting/receipt-payment-vouchers.php`.
 * AR: يدير منطق واجهات API والعمليات الخلفية في `api/accounting/receipt-payment-vouchers.php`.
 */
/**
 * Receipt and Payment Vouchers API Endpoint
 * 
 * Modern API endpoint using ReceiptPaymentVoucherManager class
 * Supports both receipt vouchers and payment vouchers
 * 
 * @package Accounting
 * @version 2.0.0
 */

require_once '../../includes/config.php';
require_once __DIR__ . '/../core/api-permission-helper.php';
if (file_exists(__DIR__ . '/../core/date-helper.php')) { require_once __DIR__ . '/../core/date-helper.php'; }
elseif (file_exists(__DIR__ . '/core/date-helper.php')) { require_once __DIR__ . '/core/date-helper.php'; }
if (!function_exists('formatDateForDatabase')) {
    function formatDateForDatabase($s) { if (empty($s)) return null; $s = trim($s); if (preg_match('/^\d{4}-\d{2}-\d{2}/', $s)) return explode(' ', $s)[0]; if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $s, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]); $t = strtotime($s); return $t ? date('Y-m-d', $t) : null; }
}
if (!function_exists('formatDateForDisplay')) {
    function formatDateForDisplay($s) { if (empty($s) || $s === '0000-00-00') return ''; if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) return sprintf('%02d/%02d/%04d', $m[2], $m[3], $m[1]); $t = strtotime($s); return $t ? date('m/d/Y', $t) : $s; }
}
if (!function_exists('formatDatesInArray')) {
    function formatDatesInArray($data, $fields = null) { if (!is_array($data)) return $data; $fields = $fields ?? ['date','entry_date','invoice_date','bill_date','due_date','payment_date','voucher_date','created_at','updated_at','transaction_date']; foreach ($data as $k => $v) { if (is_array($v)) $data[$k] = formatDatesInArray($v, $fields); elseif (in_array($k, $fields) && !empty($v)) $data[$k] = formatDateForDisplay($v); } return $data; }
}
require_once __DIR__ . '/core/ReceiptPaymentVoucherManager.php';

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
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$voucherType = $_GET['type'] ?? 'receipt'; // 'receipt' or 'payment'

// Validate voucher type
if (!in_array($voucherType, ['receipt', 'payment'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid voucher type. Must be "receipt" or "payment"']);
    exit;
}

// Check permissions
$permissionModule = $voucherType === 'receipt' ? 'receipt-vouchers' : 'payment-vouchers';

if ($method === 'GET') {
    enforceApiPermission($permissionModule, 'view');
} elseif ($method === 'POST') {
    enforceApiPermission($permissionModule, 'create');
} elseif ($method === 'PUT') {
    enforceApiPermission($permissionModule, 'update');
} elseif ($method === 'DELETE') {
    enforceApiPermission($permissionModule, 'delete');
}

try {
    // One-time renumber: set both tables to PY00001 / RC00001 format
    if ($method === 'GET' && ($_GET['action'] ?? '') === 'renumber') {
        enforceApiPermission('payment-vouchers', 'update');
        enforceApiPermission('receipt-vouchers', 'update');
        $paymentManager = new ReceiptPaymentVoucherManager($pdo, 'payment', $_SESSION['user_id']);
        $receiptManager = new ReceiptPaymentVoucherManager($pdo, 'receipt', $_SESSION['user_id']);
        $paymentResult = $paymentManager->renumberVoucherNumbers();
        $receiptResult = $receiptManager->renumberVoucherNumbers();
        echo json_encode([
            'success' => $paymentResult['success'] && $receiptResult['success'],
            'payment' => $paymentResult,
            'receipt' => $receiptResult,
            'message' => 'Payment: ' . $paymentResult['message'] . ' Receipt: ' . $receiptResult['message']
        ]);
        exit;
    }

    $manager = new ReceiptPaymentVoucherManager($pdo, $voucherType, $_SESSION['user_id']);
    
    if ($method === 'GET') {
        $voucherId = isset($_GET['id']) ? intval($_GET['id']) : null;
        $action = $_GET['action'] ?? null;
        $export = isset($_GET['export']) && $_GET['export'] === '1';
        $format = isset($_GET['format']) ? trim($_GET['format']) : '';
        
        if ($export && $format === 'csv') {
            $result = $manager->list([], 1, 50000);
            $vouchers = $result['vouchers'] ?? [];
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="vouchers_' . $voucherType . '_' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Voucher #', 'Date', 'Type', 'Amount', 'Currency', 'Status', 'Reference', 'Description']);
            foreach ($vouchers as $v) {
                if (!is_array($v)) continue;
                $num = $v['voucher_number'] ?? $v['receipt_number'] ?? $v['id'] ?? '';
                $date = $v['voucher_date'] ?? $v['payment_date'] ?? '';
                $type = $voucherType === 'payment' ? 'Payment' : 'Receipt';
                $amount = isset($v['amount']) ? (is_numeric($v['amount']) ? $v['amount'] : 0) : 0;
                $currency = isset($v['currency']) ? (string) $v['currency'] : 'SAR';
                $status = isset($v['status']) ? (string) $v['status'] : '';
                $ref = isset($v['reference_number']) ? (string) $v['reference_number'] : '';
                $desc = isset($v['description']) ? str_replace(["\r","\n"], ' ', (string) $v['description']) : (isset($v['notes']) ? str_replace(["\r","\n"], ' ', (string) $v['notes']) : '');
                fputcsv($out, [$num, $date, $type, $amount, $currency, $status, $ref, $desc]);
            }
            fclose($out);
            exit;
        }
        
        if ($action === 'list') {
            // List vouchers
            $filters = [];
            if (isset($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
            if (isset($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
            if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
            if (isset($_GET['posting_status'])) $filters['posting_status'] = $_GET['posting_status'];
            if (isset($_GET['customer_id'])) $filters['customer_id'] = intval($_GET['customer_id']);
            if (isset($_GET['vendor_id'])) $filters['vendor_id'] = intval($_GET['vendor_id']);
            if (isset($_GET['branch_id'])) $filters['branch_id'] = intval($_GET['branch_id']);
            
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
            
            $result = $manager->list($filters, $page, $perPage);
            echo json_encode($result);
            
        } elseif ($action === 'fields') {
            // Get dynamic fields configuration
            $reflection = new ReflectionClass($manager);
            $property = $reflection->getProperty('dynamicFields');
            $property->setAccessible(true);
            $fields = $property->getValue($manager);
            
            echo json_encode([
                'success' => true,
                'fields' => $fields,
                'voucher_type' => $voucherType
            ]);
            
        } elseif ($voucherId) {
            // Get single voucher
            $result = $manager->get($voucherId);
            // Format dates and ensure bank_account_id is always present for frontend
            if (isset($result['voucher']) && is_array($result['voucher'])) {
                $v = &$result['voucher'];
                $v = formatDatesInArray($v);
                // Ensure bank_account_id exists - try alternate column names
                if (!array_key_exists('bank_account_id', $v) || $v['bank_account_id'] === null) {
                    $v['bank_account_id'] = $v['cash_account_id'] ?? $v['bank_account_id'] ?? null;
                }
                // Normalize to int or null so frontend gets consistent value
                if (isset($v['bank_account_id']) && $v['bank_account_id'] !== null && $v['bank_account_id'] !== '') {
                    $v['bank_account_id'] = (int) $v['bank_account_id'];
                } elseif (!isset($v['bank_account_id']) || $v['bank_account_id'] === '') {
                    $v['bank_account_id'] = null;
                }
                // Normalize account_id (Payee/Expense GL) so frontend gets consistent value
                if (isset($v['account_id']) && $v['account_id'] !== null && $v['account_id'] !== '') {
                    $v['account_id'] = (int) $v['account_id'];
                } elseif (!isset($v['account_id']) || $v['account_id'] === '') {
                    $v['account_id'] = null;
                }
                // Normalize source_account_id (Cash/Bank when GL) so frontend gets consistent value
                if (isset($v['source_account_id']) && $v['source_account_id'] !== null && $v['source_account_id'] !== '') {
                    $v['source_account_id'] = (int) $v['source_account_id'];
                } elseif (!isset($v['source_account_id']) || $v['source_account_id'] === '') {
                    $v['source_account_id'] = null;
                }
            }
            echo json_encode($result);
        } else {
            // Default: list
            $result = $manager->list([], 1, 50);
            // Format dates in response
            if (isset($result['vouchers']) && is_array($result['vouchers'])) {
                $result['vouchers'] = array_map(function($voucher) {
                    return formatDatesInArray($voucher);
                }, $result['vouchers']);
            }
            echo json_encode($result);
        }
        
    } elseif ($method === 'POST') {
        $action = $_GET['action'] ?? 'create';
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($action === 'create') {
            // Convert dates from MM/DD/YYYY to YYYY-MM-DD for database
            if (isset($data['voucher_date'])) {
                $data['voucher_date'] = formatDateForDatabase($data['voucher_date']);
            }
            // Create voucher
            $result = $manager->create($data);
            if (isset($result['voucher']) && is_array($result['voucher'])) {
                $result['voucher'] = formatDatesInArray($result['voucher']);
            }
            http_response_code($result['success'] ? 201 : 400);
            echo json_encode($result);
            
        } elseif ($action === 'post') {
            // Post voucher
            $voucherId = isset($_GET['id']) ? intval($_GET['id']) : null;
            if (!$voucherId) {
                throw new Exception('Voucher ID required');
            }
            $result = $manager->post($voucherId);
            // Format dates in response
            if (isset($result['voucher']) && is_array($result['voucher'])) {
                $result['voucher'] = formatDatesInArray($result['voucher']);
            }
            http_response_code($result['success'] ? 200 : 400);
            echo json_encode($result);
            
        } elseif ($action === 'reverse') {
            // Reverse voucher
            $voucherId = isset($_GET['id']) ? intval($_GET['id']) : null;
            if (!$voucherId) {
                throw new Exception('Voucher ID required');
            }
            $reversalDate = $data['reversal_date'] ?? null;
            $description = $data['description'] ?? null;
            // Convert reversal_date from MM/DD/YYYY to YYYY-MM-DD if provided
            if ($reversalDate) {
                $reversalDate = formatDateForDatabase($reversalDate);
            }
            $result = $manager->reverse($voucherId, $reversalDate, $description);
            // Format dates in response
            if (isset($result['voucher']) && is_array($result['voucher'])) {
                $result['voucher'] = formatDatesInArray($result['voucher']);
            }
            http_response_code($result['success'] ? 200 : 400);
            echo json_encode($result);
            
        } elseif ($action === 'add-field') {
            // Add dynamic field
            $fieldName = $data['field_name'] ?? null;
            $fieldLabel = $data['field_label'] ?? null;
            $fieldType = $data['field_type'] ?? 'text';
            $fieldOptions = $data['field_options'] ?? null;
            $isRequired = isset($data['is_required']) ? (bool)$data['is_required'] : false;
            $displayOrder = isset($data['display_order']) ? intval($data['display_order']) : 0;
            
            if (!$fieldName || !$fieldLabel) {
                throw new Exception('Field name and label are required');
            }
            
            $success = $manager->addDynamicField($fieldName, $fieldLabel, $fieldType, $fieldOptions, $isRequired, $displayOrder);
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Dynamic field added successfully' : 'Failed to add dynamic field'
            ]);
            
        } elseif ($action === 'remove-field') {
            // Remove dynamic field
            $fieldName = $data['field_name'] ?? null;
            if (!$fieldName) {
                throw new Exception('Field name is required');
            }
            
            $success = $manager->removeDynamicField($fieldName);
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Dynamic field removed successfully' : 'Failed to remove dynamic field'
            ]);
            
        } elseif ($action === 'duplicate') {
            // Duplicate voucher: get by id then create copy
            $voucherId = isset($_GET['id']) ? intval($_GET['id']) : null;
            if (!$voucherId) {
                throw new Exception('Voucher ID required for duplicate');
            }
            $getResult = $manager->get($voucherId);
            if (!$getResult['success'] || empty($getResult['voucher'])) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => $getResult['message'] ?? 'Voucher not found']);
                exit;
            }
            $v = $getResult['voucher'];
            $copy = [
                'voucher_date' => $v['voucher_date'] ?? date('Y-m-d'),
                'amount' => $v['amount'] ?? 0,
                'currency' => $v['currency'] ?? 'SAR',
                'payment_method' => $v['payment_method'] ?? 'Cash',
                'bank_account_id' => $v['bank_account_id'] ?? null,
                'source_account_id' => $v['source_account_id'] ?? null,
                'account_id' => $v['account_id'] ?? null,
                'cheque_number' => $v['cheque_number'] ?? null,
                'reference_number' => ($v['reference_number'] ?? '') ? ($v['reference_number'] . ' (Copy)') : null,
                'customer_id' => $v['customer_id'] ?? null,
                'vendor_id' => $v['vendor_id'] ?? null,
                'entity_type' => $v['entity_type'] ?? null,
                'entity_id' => $v['entity_id'] ?? null,
                'cost_center_id' => $v['cost_center_id'] ?? null,
                'branch_id' => $v['branch_id'] ?? null,
                'notes' => $v['notes'] ?? $v['description'] ?? null,
                'description' => $v['description'] ?? $v['notes'] ?? null,
                'status' => 'Draft',
            ];
            if (!empty($v['dynamic_fields']) && is_array($v['dynamic_fields'])) {
                foreach ($v['dynamic_fields'] as $key => $val) {
                    $copy[$key] = $val;
                }
            }
            $result = $manager->create($copy);
            if (isset($result['voucher']) && is_array($result['voucher'])) {
                $result['voucher'] = formatDatesInArray($result['voucher']);
            }
            http_response_code($result['success'] ? 201 : 400);
            echo json_encode($result);
            
        } else {
            throw new Exception('Invalid action');
        }
        
    } elseif ($method === 'PUT') {
        $voucherId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$voucherId) {
            throw new Exception('Voucher ID required');
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        // Convert dates from MM/DD/YYYY to YYYY-MM-DD for database
        if (isset($data['voucher_date'])) {
            $data['voucher_date'] = formatDateForDatabase($data['voucher_date']);
        }
        $result = $manager->update($voucherId, $data);
        // Format dates in response
        if (isset($result['voucher']) && is_array($result['voucher'])) {
            $result['voucher'] = formatDatesInArray($result['voucher']);
        }
        http_response_code($result['success'] ? 200 : 400);
        echo json_encode($result);
        
    } elseif ($method === 'DELETE') {
        $voucherId = isset($_GET['id']) ? intval($_GET['id']) : null;
        if (!$voucherId) {
            throw new Exception('Voucher ID required');
        }
        
        $result = $manager->delete($voucherId);
        http_response_code($result['success'] ? 200 : 400);
        echo json_encode($result);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getTraceAsString() : null
    ]);
}
