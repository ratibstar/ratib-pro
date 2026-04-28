<?php
/**
 * EN: Handles application behavior in `tap-payments/success.php`.
 * AR: يدير سلوك جزء من التطبيق في `tap-payments/success.php`.
 */
/**
 * Tap Payments - Success Page
 *
 * User lands here after verify.php confirms payment is CAPTURED.
 * Displays success message and logs transaction ID securely.
 */
require_once __DIR__ . '/config.php';

// -----------------------------------------------------------------------------
// 1. Get tap_id from query (passed by verify.php)
// -----------------------------------------------------------------------------
$tapId = isset($_GET['tap_id']) ? trim($_GET['tap_id']) : '';
$tapId = preg_replace('/[^a-zA-Z0-9_]/', '', $tapId);

// Get customer email from session or query if available (for displaying alert)
$customerEmail = isset($_GET['email']) ? trim($_GET['email']) : '';
$customerName = isset($_GET['name']) ? trim($_GET['name']) : 'Customer';

// -----------------------------------------------------------------------------
// 2. Log transaction ID after success (secure server-side logging)
// -----------------------------------------------------------------------------
if (!empty($tapId)) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = $logDir . '/tap_success.log';
    $logEntry = date('Y-m-d H:i:s') . ' | tap_id=' . $tapId . ' | ip=' . ($_SERVER['REMOTE_ADDR'] ?? '') . PHP_EOL;
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// -----------------------------------------------------------------------------
// 3. Build redirect URL - redirect to main registration page, not checkout form
// -----------------------------------------------------------------------------
// Get registration ID from metadata if available (for customer portal redirect)
$registrationId = '';
if (!empty($tapId)) {
    // Try to get registration ID from charge metadata (if we stored it)
    // This would require fetching the charge again, but for now we'll redirect to home page
}

// Build URL to main registration page (home.php) with success status
// Use the same method as verify.php for consistency
function buildHomeUrl() {
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // Get script path (e.g., /tap-payments/success.php)
    $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '/tap-payments/success.php';
    // Get directory (e.g., /tap-payments)
    $scriptDir = dirname($scriptPath);
    // Go up one level to get base (e.g., /)
    $basePath = dirname($scriptDir);
    // Normalize path separators
    $basePath = str_replace('\\', '/', $basePath);
    // Ensure base path starts with /
    if ($basePath !== '/' && substr($basePath, 0, 1) !== '/') {
        $basePath = '/' . $basePath;
    }
    // Remove trailing slash
    $basePath = rtrim($basePath, '/');
    return $scheme . '://' . $host . $basePath . '/pages/home.php';
}

$homeUrl = buildHomeUrl() . '?payment_status=success&tap_id=' . urlencode($tapId);

// Alternative: Redirect to customer portal if registration ID is available
// $customerPortalUrl = $scheme . '://' . $host . $basePath . '/pages/customer-portal.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Tap Payments</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 480px; margin: 3rem auto; padding: 0 1rem; text-align: center; }
        .success-icon { font-size: 4rem; color: #28a745; margin-bottom: 1rem; }
        h1 { color: #28a745; font-size: 1.5rem; margin-bottom: 0.5rem; }
        p { color: #555; margin-bottom: 1rem; }
        .ref { font-size: 0.875rem; color: #888; word-break: break-all; }
        a { display: inline-block; margin-top: 1.5rem; padding: 0.75rem 1.5rem; background: #0070ba; color: #fff; text-decoration: none; border-radius: 6px; }
        a:hover { background: #005ea6; }
    </style>
</head>
<body>
    <div class="success-icon">&#10003;</div>
    <h1>Payment Successful</h1>
    <p>Thank you for your subscription. Your payment has been completed successfully.</p>
    <?php if (!empty($tapId)): ?>
    <p class="ref">Reference: <?php echo htmlspecialchars($tapId); ?></p>
    <?php endif; ?>
    
    <?php if (!empty($customerEmail)): ?>
    <div style="background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; padding: 15px; margin: 20px 0; text-align: left;">
        <p style="margin: 0; color: #155724; font-size: 14px;">
            <strong>✓ Confirmation Email Sent</strong><br>
            <small style="color: #6c757d;">A payment receipt/voucher has been sent to: <strong><?php echo htmlspecialchars($customerEmail); ?></strong></small>
        </p>
    </div>
    <?php else: ?>
    <div style="background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 6px; padding: 15px; margin: 20px 0; text-align: left;">
        <p style="margin: 0; color: #856404; font-size: 14px;">
            <strong>📧 Check Your Email</strong><br>
            <small>A payment confirmation email with receipt has been sent to your registered email address.</small>
        </p>
    </div>
    <?php endif; ?>
    
    <p style="margin-top: 1.5rem; color: #666; font-size: 0.9rem;">Redirecting you back...</p>
    <script>
        // Show alert for successful payment
        alert('Payment Successful!\n\n✓ Your payment has been confirmed.\n✓ A receipt/voucher has been sent to your email.\n✓ Please check your inbox for the payment confirmation.');
        
        // Auto-redirect after 3 seconds (give time to read alert)
        setTimeout(function() {
            window.location.href = <?php echo json_encode($homeUrl); ?>;
        }, 3000);
    </script>
    <a href="<?php echo htmlspecialchars($homeUrl); ?>" style="margin-top: 1rem;">Continue to Registration Page</a>
</body>
</html>
