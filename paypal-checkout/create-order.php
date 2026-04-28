<?php
/**
 * EN: Handles application behavior in `paypal-checkout/create-order.php`.
 * AR: يدير سلوك جزء من التطبيق في `paypal-checkout/create-order.php`.
 */
/**
 * PayPal Checkout - Create Order Endpoint
 * 
 * This endpoint creates a PayPal order on the backend.
 * The frontend will receive an order ID to use with PayPal Smart Button.
 * 
 * Security: All order creation happens server-side to prevent tampering.
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, null, 'Method not allowed', 405);
}

// Get and validate input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse(false, null, 'Invalid JSON input', 400);
}

// Validate required fields
$amount = isset($input['amount']) ? (float)$input['amount'] : 0;
$tax = isset($input['tax']) ? (float)$input['tax'] : ($amount * 0.15); // Default 15% tax if not provided
$total = isset($input['total']) ? (float)$input['total'] : ($amount + $tax);
$plan = isset($input['plan']) ? trim($input['plan']) : '';
$years = isset($input['years']) ? (int)$input['years'] : 1;

// Ensure total matches amount + tax
if (abs($total - ($amount + $tax)) > 0.01) {
    $total = $amount + $tax;
}

// Security: Validate plan name (whitelist)
$allowedPlans = ['gold', 'platinum'];
if (!in_array(strtolower($plan), $allowedPlans)) {
    jsonResponse(false, null, 'Invalid plan selected', 400);
}

// Security: Validate amount range
if ($amount <= 0 || $amount > 100000) { // Max $100,000
    jsonResponse(false, null, 'Invalid amount', 400);
}

// Security: Validate years range
if ($years < 1 || $years > 10) {
    jsonResponse(false, null, 'Invalid years selection', 400);
}

if (empty($plan)) {
    jsonResponse(false, null, 'Plan is required', 400);
}

// Validate amount range (security: prevent extremely high/low amounts)
if ($amount < 1 || $amount > 100000) {
    jsonResponse(false, null, 'Amount out of valid range', 400);
}

// Sanitize plan name
$plan = preg_replace('/[^a-zA-Z0-9_-]/', '', $plan);

/**
 * Build PayPal Order Request
 * 
 * PayPal Orders API v2 structure:
 * - intent: CAPTURE (immediate payment) or AUTHORIZE (authorize for later)
 * - purchase_units: Array of items being purchased
 * - application_context: UI customization and return URLs
 */
$orderData = [
    'intent' => 'CAPTURE', // CAPTURE = immediate payment, AUTHORIZE = authorize for later capture
    'purchase_units' => [
        [
            'reference_id' => 'ratib_' . uniqid(), // Unique reference for your system
            'description' => sprintf('Ratib %s Plan - %d Year%s', ucfirst($plan), $years, $years > 1 ? 's' : ''),
            'custom_id' => sprintf('plan:%s:years:%d', $plan, $years), // For tracking in your system
            'amount' => [
                'currency_code' => CURRENCY,
                'value' => number_format($total, 2, '.', ''), // Total including tax
                'breakdown' => [
                    'item_total' => [
                        'currency_code' => CURRENCY,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                    'tax_total' => [
                        'currency_code' => CURRENCY,
                        'value' => number_format($tax, 2, '.', ''),
                    ],
                ],
            ],
            'items' => [
                [
                    'name' => sprintf('Ratib %s Plan - %d Year%s', ucfirst($plan), $years, $years > 1 ? 's' : ''),
                    'description' => sprintf('Ratib %s Plan subscription for %d year%s', ucfirst($plan), $years, $years > 1 ? 's' : ''),
                    'unit_amount' => [
                        'currency_code' => CURRENCY,
                        'value' => number_format($amount, 2, '.', ''),
                    ],
                    'quantity' => '1',
                    'tax' => [
                        'currency_code' => CURRENCY,
                        'value' => number_format($tax, 2, '.', ''),
                    ],
                ],
            ],
            'shipping' => [
                'name' => [
                    'full_name' => 'Not Required', // Digital product, no shipping
                ],
            ],
        ],
    ],
    'application_context' => [
        'brand_name' => 'Ratib Software Foundation',
        'locale' => 'en-US',
        'landing_page' => 'NO_PREFERENCE', // BILLING, LOGIN, or NO_PREFERENCE
        'shipping_preference' => 'NO_SHIPPING', // Digital product
        'user_action' => 'PAY_NOW', // Shows "Pay Now" button instead of "Continue"
        'return_url' => getenv('PAYPAL_RETURN_URL') ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/capture-order.php'),
        'cancel_url' => getenv('PAYPAL_CANCEL_URL') ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/index.php?canceled=1'),
    ],
];

// Make API request to create order
$result = paypalApiRequest('/v2/checkout/orders', 'POST', $orderData);

if (!$result || !$result['success']) {
    $errorMsg = 'Failed to create PayPal order';
    if (isset($result['data']['message'])) {
        $errorMsg = $result['data']['message'];
    }
    error_log('PayPal Create Order Failed: ' . json_encode($result));
    jsonResponse(false, null, $errorMsg, 500);
}

$order = $result['data'];

// Validate response structure
if (!isset($order['id']) || !isset($order['status'])) {
    error_log('PayPal Invalid Response: ' . json_encode($order));
    jsonResponse(false, null, 'Invalid response from PayPal', 500);
}

// Log order creation (for debugging - remove sensitive data in production)
error_log(sprintf(
    'PayPal Order Created: ID=%s, Status=%s, Amount=%s %s',
    $order['id'],
    $order['status'],
    $amount,
    CURRENCY
));

// Return order ID to frontend
jsonResponse(true, [
    'orderId' => $order['id'],
    'status' => $order['status'],
], 'Order created successfully');
