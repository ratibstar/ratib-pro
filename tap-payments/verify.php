<?php
/**
 * EN: Handles application behavior in `tap-payments/verify.php`.
 * AR: يدير سلوك جزء من التطبيق في `tap-payments/verify.php`.
 */
/**
 * Tap Payments - Verify Charge (Secure Backend Verification)
 *
 * Tap redirects user here with tap_id (charge ID) after payment attempt.
 * We fetch the charge from Tap API and verify status on our server (prevents client manipulation).
 * Then redirect to success.php or failed.php.
 */

// Ensure we're not outputting JSON - this is a redirect handler, not an API endpoint
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

// -----------------------------------------------------------------------------
// 1. Get tap_id from query (Tap passes this on redirect)
// -----------------------------------------------------------------------------
// SECURITY: Sanitize tap_id to prevent injection attacks
// Tap charge IDs are alphanumeric with underscores (e.g., chg_TS...)
$tapId = isset($_GET['tap_id']) ? trim($_GET['tap_id']) : (isset($_GET['charge_id']) ? trim($_GET['charge_id']) : (isset($_GET['id']) ? trim($_GET['id']) : ''));
$tapId = preg_replace('/[^a-zA-Z0-9_]/', '', $tapId);  // Remove any non-alphanumeric chars except underscore

if (empty($tapId)) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Location: ' . buildTapUrl('failed.php?reason=missing_tap_id'));
    exit;
}

if (empty(TAP_SECRET_KEY)) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Location: ' . buildTapUrl('failed.php?reason=config_error'));
    exit;
}

// -----------------------------------------------------------------------------
// 3. Fetch charge from Tap API (secure server-to-server verification)
// -----------------------------------------------------------------------------
// SECURITY: Never trust client-side data. Always verify payment status via API.
// This prevents users from manipulating payment status by modifying URLs.
$ch = curl_init('https://api.tap.company/v2/charges/' . $tapId);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,  // Return response as string
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . TAP_SECRET_KEY,  // Secret key in header
    ],
    CURLOPT_SSL_VERIFYPEER => true,  // Verify SSL certificate (production)
    CURLOPT_TIMEOUT       => 30,      // 30 second timeout
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

// Handle API errors
if ($err || $httpCode !== 200) {
    // Log error for debugging (optional - uncomment to enable)
    // require_once __DIR__ . '/error_log.php';
    // logTapError("Tap Verification Error: HTTP $httpCode", ['tap_id' => $tapId, 'error' => $err, 'response' => $response]);
    
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Location: ' . buildTapUrl('failed.php?tap_id=' . urlencode($tapId) . '&reason=verification_failed'));
    exit;
}

$charge = json_decode($response, true);
$status = isset($charge['status']) ? strtoupper($charge['status']) : '';
$registrationId = isset($charge['metadata']['udf1']) ? trim($charge['metadata']['udf1']) : '';
$amount = isset($charge['amount']) ? (float)$charge['amount'] : 0;
$currency = isset($charge['currency']) ? $charge['currency'] : 'USD';
$description = isset($charge['description']) ? $charge['description'] : 'SaaS Subscription';
$customerEmail = isset($charge['customer']['email']) ? trim($charge['customer']['email']) : '';
$customerName = isset($charge['customer']['first_name']) ? trim($charge['customer']['first_name']) : 'Customer';
if (isset($charge['customer']['last_name']) && !empty($charge['customer']['last_name'])) {
    $customerName .= ' ' . trim($charge['customer']['last_name']);
}

// -----------------------------------------------------------------------------
// 4. Verify payment status - only CAPTURED = success
// -----------------------------------------------------------------------------
// SECURITY: Only accept CAPTURED status as successful payment
// Other statuses: INITIATED, AUTHORIZED, ABANDONED, CANCELLED, FAILED, DECLINED
// Only CAPTURED means money was successfully charged
if ($status !== 'CAPTURED') {
    // Send failure email if customer email is available
    if (!empty($customerEmail)) {
        require_once __DIR__ . '/email_helper.php';
        $paymentData = [
            'reason' => 'Payment was not completed. Status: ' . htmlspecialchars($status),
            'tap_id' => $tapId,
            'amount' => $amount,
            'description' => $description
        ];
        @sendPaymentFailureEmail($customerEmail, $customerName, $paymentData);
    }
    
    $reason = 'Payment was not completed. Status: ' . htmlspecialchars($status);
    $failedUrl = buildTapUrl('failed.php?tap_id=' . urlencode($tapId) . '&reason=' . urlencode($reason));
    if (!empty($customerEmail)) {
        $failedUrl .= '&email=' . urlencode($customerEmail);
    }
    if (!empty($customerName)) {
        $failedUrl .= '&name=' . urlencode($customerName);
    }
    // Clear any output buffers before redirecting
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Location: ' . $failedUrl);
    exit;
}

// -----------------------------------------------------------------------------
// 5. Optional: Update registration (if integrated with main app)
// -----------------------------------------------------------------------------
$plan = '';
$years = 1;
$configPath = __DIR__ . '/../includes/config.php';
if (file_exists($configPath)) {
    try {
        require_once $configPath;
    } catch (Exception $e) {
        // If config.php fails, log but continue - we can still verify payment
        error_log("Tap verify.php: config.php error - " . $e->getMessage());
    }
    
    $conn = $GLOBALS['conn'] ?? null;
    if ($conn && !empty($registrationId)) {
        $col = $conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_status'");
        if ($col && $col->num_rows > 0) {
            $rid = (int) $registrationId;
            $stmt = $conn->prepare("UPDATE control_registration_requests SET payment_status = 'paid', payment_method = 'tap' WHERE id = ? AND (payment_status IS NULL OR payment_status = 'pending')");
            if ($stmt) {
                $stmt->bind_param('i', $rid);
                $stmt->execute();
            }
            
            // Get plan and years for email (check if years column exists first)
            $colYears = $conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'years'");
            $hasYearsColumn = $colYears && $colYears->num_rows > 0;
            
            if ($hasYearsColumn) {
                $stmt2 = $conn->prepare("SELECT plan, years FROM control_registration_requests WHERE id = ?");
            } else {
                $stmt2 = $conn->prepare("SELECT plan FROM control_registration_requests WHERE id = ?");
            }
            
            if ($stmt2) {
                $stmt2->bind_param('i', $rid);
                $stmt2->execute();
                $result = $stmt2->get_result();
                if ($row = $result->fetch_assoc()) {
                    $plan = $row['plan'] ?? '';
                    if ($hasYearsColumn) {
                        $years = (int)($row['years'] ?? 1);
                    } else {
                        $years = 1; // Default to 1 year if column doesn't exist
                    }
                }
                $stmt2->close();
            }
        }
    }
}

// -----------------------------------------------------------------------------
// 6. Send payment confirmation email with voucher/receipt
// -----------------------------------------------------------------------------
if (!empty($customerEmail)) {
    require_once __DIR__ . '/email_helper.php';
    
    $tax = calculateTax($amount);
    $total = $amount + $tax;
    
    $paymentData = [
        'plan' => ucfirst($plan ?: 'Subscription'),
        'amount' => $amount,
        'tax' => $tax,
        'total' => $total,
        'tap_id' => $tapId,
        'registration_id' => $registrationId,
        'description' => $description,
        'years' => $years
    ];
    
    // Send confirmation email (non-blocking - don't wait for it)
    @sendPaymentConfirmationEmail($customerEmail, $customerName, $paymentData);
}

// -----------------------------------------------------------------------------
// 6. Redirect to success page (transaction logged in success.php)
// -----------------------------------------------------------------------------
// Build absolute URL to success.php with customer info for email display
$successUrl = buildTapUrl('success.php?tap_id=' . urlencode($tapId));
if (!empty($customerEmail)) {
    $successUrl .= '&email=' . urlencode($customerEmail);
}
if (!empty($customerName)) {
    $successUrl .= '&name=' . urlencode($customerName);
}

// Debug: Log the redirect URL (optional - remove in production)
// error_log("Tap verify.php redirecting to: " . $successUrl);

// Ensure we're redirecting to success.php, not index.php
// Clear any output buffers that might contain JSON or other content
while (ob_get_level()) {
    ob_end_clean();
}

header('Location: ' . $successUrl);
exit;
