<?php
/**
 * EN: Handles application behavior in `paypal-checkout/capture-order.php`.
 * AR: يدير سلوك جزء من التطبيق في `paypal-checkout/capture-order.php`.
 */
/**
 * PayPal Checkout - Capture Order Endpoint
 * 
 * This endpoint captures the payment after user approves on PayPal.
 * Called automatically by PayPal redirect or manually from frontend.
 * 
 * Security: Payment capture happens server-side to prevent fraud.
 */

require_once __DIR__ . '/config.php';

// Set JSON response header
header('Content-Type: application/json; charset=UTF-8');

// Security: Check if required PHP extensions are available
if (!function_exists('curl_init')) {
    jsonResponse(false, null, 'cURL extension is required', 500);
}
if (!function_exists('json_encode')) {
    jsonResponse(false, null, 'JSON extension is required', 500);
}

// Security: Validate credentials are set
if (empty(PAYPAL_CLIENT_ID) || empty(PAYPAL_SECRET)) {
    jsonResponse(false, null, 'PayPal credentials not configured', 500);
}

// Handle both GET (redirect) and POST (AJAX) requests
$orderId = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AJAX call from frontend
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = isset($input['orderId']) ? trim($input['orderId']) : null;
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Redirect from PayPal
    $orderId = isset($_GET['token']) ? trim($_GET['token']) : null;
}

if (empty($orderId)) {
    jsonResponse(false, null, 'Order ID is required', 400);
}

// Validate order ID format (PayPal order IDs are alphanumeric, typically 17+ chars)
// Format: Usually starts with letters and contains uppercase alphanumeric
if (!preg_match('/^[A-Z0-9_-]{10,}$/i', $orderId)) {
    jsonResponse(false, null, 'Invalid order ID format', 400);
}

/**
 * Capture PayPal Order
 * 
 * After user approves payment on PayPal, we capture it here.
 * This finalizes the transaction and transfers funds.
 */
$result = paypalApiRequest('/v2/checkout/orders/' . urlencode($orderId) . '/capture', 'POST');

if (!$result || !$result['success']) {
    $errorMsg = 'Failed to capture payment';
    if (isset($result['data']['message'])) {
        $errorMsg = $result['data']['message'];
    }
    if (isset($result['data']['details'][0]['description'])) {
        $errorMsg = $result['data']['details'][0]['description'];
    }
    error_log('PayPal Capture Failed: ' . json_encode($result));
    jsonResponse(false, null, $errorMsg, 500);
}

$captureData = $result['data'];

// Validate capture response
if (!isset($captureData['status']) || $captureData['status'] !== 'COMPLETED') {
    error_log('PayPal Capture Not Completed: ' . json_encode($captureData));
    jsonResponse(false, $captureData, 'Payment not completed', 400);
}

// Extract transaction details
$purchaseUnit = $captureData['purchase_units'][0] ?? null;
$capture = $purchaseUnit['payments']['captures'][0] ?? null;

if (!$capture) {
    error_log('PayPal Capture Missing Data: ' . json_encode($captureData));
    jsonResponse(false, null, 'Invalid capture response', 500);
}

$transactionId = $capture['id'] ?? '';
$amount = $capture['amount']['value'] ?? 0;
$currency = $capture['amount']['currency_code'] ?? CURRENCY;
$status = $capture['status'] ?? '';

// Log successful transaction
logTransaction($orderId, $transactionId, $amount, $currency);

// Log capture details (for debugging)
error_log(sprintf(
    'PayPal Payment Captured: Order=%s, Transaction=%s, Amount=%s %s, Status=%s',
    $orderId,
    $transactionId,
    $amount,
    $currency,
    $status
));

// Return success response
jsonResponse(true, [
    'orderId' => $orderId,
    'transactionId' => $transactionId,
    'amount' => $amount,
    'currency' => $currency,
    'status' => $status,
    'payer' => $captureData['payer'] ?? null,
], 'Payment captured successfully');
