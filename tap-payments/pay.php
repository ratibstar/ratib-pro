<?php
/**
 * EN: Handles application behavior in `tap-payments/pay.php`.
 * AR: يدير سلوك جزء من التطبيق في `tap-payments/pay.php`.
 */
/**
 * Tap Payments - Create Charge and Redirect to Tap Hosted Page
 *
 * Receives checkout data, creates a charge via Tap REST API using cURL,
 * then redirects user to Tap hosted payment page.
 * Secret key is used ONLY in backend.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// -----------------------------------------------------------------------------
// 1. Check secret key
// -----------------------------------------------------------------------------
if (empty(TAP_SECRET_KEY) || TAP_SECRET_KEY === 'sk_test_xxxxxxxxxxxxxxxxx' || TAP_SECRET_KEY === 'sk_live_xxxxxxxxxxxxxxxxx') {
    require_once __DIR__ . '/error_log.php';
    logTapError("Tap secret key not configured", [
        'key_set' => !empty(TAP_SECRET_KEY),
        'key_value' => substr(TAP_SECRET_KEY, 0, 10) . '...' // First 10 chars only
    ]);
    header('Location: ' . buildTapUrl('index.php', ['payment_status' => 'config_error&details=Secret key not configured. Please update config.php with your Tap secret key.']));
    exit;
}

// -----------------------------------------------------------------------------
// 3. Build verify URL (Tap redirects here after payment) - use HTTPS
// -----------------------------------------------------------------------------
// IMPORTANT: In production, ensure HTTPS is enforced
// Tap requires HTTPS for production payments
// CRITICAL: This URL must be absolute and point to verify.php (NOT index.php)
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
if (TAP_LIVE_MODE && $scheme !== 'https') {
    // Force HTTPS in production
    $scheme = 'https';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/tap-payments'));
$tapBase = rtrim($scriptDir, '/');
// Ensure verify.php is explicitly in the path (not index.php)
$verifyUrl = $scheme . '://' . $host . $tapBase . '/verify.php';

// Debug: Log the verify URL (optional - remove in production)
// error_log("Tap pay.php redirect URL: " . $verifyUrl);

// -----------------------------------------------------------------------------
// 4. Get and validate input (prevent amount manipulation)
// -----------------------------------------------------------------------------
// SECURITY: Always validate and sanitize user input
// Amount validation prevents client-side manipulation

// Get amount and handle locale-specific formats (Arabic numerals, commas, etc.)
$amountRaw = isset($_POST['amount']) ? $_POST['amount'] : (isset($_GET['amount']) ? $_GET['amount'] : '0');
// Convert Arabic/Persian numerals to Western numerals
$amountRaw = str_replace(['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'], ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'], $amountRaw);
// Remove commas and spaces, keep only digits and decimal point
$amountRaw = preg_replace('/[^\d.]/', '', $amountRaw);
$amount = (float) $amountRaw;

$registrationId = isset($_POST['registration_id']) ? trim((string) $_POST['registration_id']) : (isset($_GET['registration_id']) ? trim((string) $_GET['registration_id']) : '');
$customerName = isset($_POST['customer_name']) ? trim((string) $_POST['customer_name']) : 'Customer';
$customerEmail = isset($_POST['customer_email']) ? trim((string) $_POST['customer_email']) : '';
$customerPhone = isset($_POST['customer_phone']) ? trim((string) $_POST['customer_phone']) : '';
$description = isset($_POST['description']) ? trim((string) $_POST['description']) : 'SaaS Subscription';

// Round amount to 2 decimal places (USD standard)
$amount = round($amount, 2);

// Validate amount range (prevents manipulation)
if ($amount < TAP_MIN_AMOUNT || $amount > TAP_MAX_AMOUNT) {
    header('Location: ' . buildTapUrl('index.php', ['payment_status' => 'invalid_amount']));
    exit;
}

// Validate email format
if (empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    header('Location: ' . buildTapUrl('index.php', ['payment_status' => 'invalid_email']));
    exit;
}

// -----------------------------------------------------------------------------
// 5. Parse customer name
// -----------------------------------------------------------------------------
$nameParts = preg_split('/\s+/', $customerName, 2);
$firstName = $nameParts[0] ?? 'Customer';
$lastName = $nameParts[1] ?? '';

// -----------------------------------------------------------------------------
// 6. Build charge payload for Tap charges endpoint
// -----------------------------------------------------------------------------
$customerData = [
    'first_name' => $firstName,
    'last_name'  => $lastName,
    'email'      => $customerEmail,
];

// Add phone if provided (optional but recommended for better customer experience)
if (!empty($customerPhone)) {
    $customerData['phone'] = [
        'country_code' => '966',  // Saudi Arabia country code (change if needed)
        'number'       => preg_replace('/[^0-9]/', '', $customerPhone),  // Remove non-digits
    ];
}

$payload = [
    'amount'      => $amount,
    'currency'    => TAP_CURRENCY,
    'customer'    => $customerData,
    'source'      => ['id' => 'src_all'],  // Accept all payment methods
    'redirect'    => ['url' => $verifyUrl],
    'metadata'    => ['udf1' => $registrationId],  // Store registration ID for tracking
    'description' => $description,
];

// -----------------------------------------------------------------------------
// 7. Create charge via cURL (Tap REST API)
// -----------------------------------------------------------------------------
// SECURITY: Secret key is sent in Authorization header (never exposed to client)
// All communication with Tap API uses HTTPS
$ch = curl_init(TAP_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,  // Return response as string
    CURLOPT_POST          => true,   // Use POST method
    CURLOPT_HTTPHEADER    => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . TAP_SECRET_KEY,  // Secret key in header
    ],
    CURLOPT_POSTFIELDS    => json_encode($payload),  // JSON payload
    CURLOPT_SSL_VERIFYPEER => true,  // Verify SSL certificate (production)
    CURLOPT_TIMEOUT       => 30,      // 30 second timeout
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

// Handle cURL errors or non-2xx HTTP codes
if ($err || $httpCode < 200 || $httpCode >= 300) {
    // Log error for debugging
    require_once __DIR__ . '/error_log.php';
    logTapError("Tap API Error: HTTP $httpCode", [
        'error' => $err,
        'http_code' => $httpCode,
        'response' => substr($response, 0, 500), // First 500 chars
        'payload' => json_encode($payload),
        'url' => TAP_API_URL
    ]);
    
    // Try to extract error message from Tap response
    $errorMsg = 'charge_failed';
    if ($response) {
        $errorData = json_decode($response, true);
        if (isset($errorData['errors']) && is_array($errorData['errors']) && !empty($errorData['errors'])) {
            $firstError = $errorData['errors'][0];
            if (isset($firstError['message'])) {
                $errorMsg = 'charge_failed&details=' . urlencode(substr($firstError['message'], 0, 50));
            }
        } elseif (isset($errorData['message'])) {
            $errorMsg = 'charge_failed&details=' . urlencode(substr($errorData['message'], 0, 50));
        }
    }
    
    header('Location: ' . buildTapUrl('index.php', ['payment_status' => $errorMsg]));
    exit;
}

// Parse JSON response
$data = json_decode($response, true);

// Check if response is valid JSON
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    require_once __DIR__ . '/error_log.php';
    logTapError("Invalid JSON response from Tap API", [
        'response' => substr($response, 0, 500),
        'json_error' => json_last_error_msg()
    ]);
    header('Location: ' . buildTapUrl('index.php', ['payment_status' => 'charge_failed&details=invalid_response']));
    exit;
}

$paymentUrl = $data['transaction']['url'] ?? null;

// Verify payment URL exists in response
if (empty($paymentUrl)) {
    require_once __DIR__ . '/error_log.php';
    logTapError("Payment URL missing from Tap response", [
        'response' => substr($response, 0, 500),
        'data_keys' => array_keys($data ?? [])
    ]);
    
    // Check for errors in response
    $errorDetails = '';
    if (isset($data['errors']) && is_array($data['errors'])) {
        $errorDetails = '&details=' . urlencode('Tap API error: ' . ($data['errors'][0]['message'] ?? 'Unknown'));
    } elseif (isset($data['message'])) {
        $errorDetails = '&details=' . urlencode($data['message']);
    }
    
    header('Location: ' . buildTapUrl('index.php', ['payment_status' => 'charge_failed' . $errorDetails]));
    exit;
}

// -----------------------------------------------------------------------------
// 8. Redirect user to Tap hosted payment page
// -----------------------------------------------------------------------------
header('Location: ' . $paymentUrl);
exit;
