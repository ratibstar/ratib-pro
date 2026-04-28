<?php
/**
 * EN: Handles application behavior in `tap-payments/failed.php`.
 * AR: يدير سلوك جزء من التطبيق في `tap-payments/failed.php`.
 */
/**
 * Tap Payments - Failed Page
 * 
 * User lands here when verify.php determines payment was not CAPTURED
 * (declined, cancelled, or other failure).
 */
require_once __DIR__ . '/config.php';

// -----------------------------------------------------------------------------
// 1. Get tap_id and optional reason from query
// -----------------------------------------------------------------------------
$tapId = isset($_GET['tap_id']) ? trim($_GET['tap_id']) : '';
$tapId = preg_replace('/[^a-zA-Z0-9_]/', '', $tapId);
$reason = isset($_GET['reason']) ? trim($_GET['reason']) : 'Payment could not be completed.';
$customerEmail = isset($_GET['email']) ? trim($_GET['email']) : '';
$customerName = isset($_GET['name']) ? trim($_GET['name']) : 'Customer';

// -----------------------------------------------------------------------------
// 2. Send failure notification email (if email available)
// -----------------------------------------------------------------------------
// Note: Email is already sent in verify.php before redirect, but we can send here too if needed
if (!empty($customerEmail) && !empty($tapId)) {
    require_once __DIR__ . '/email_helper.php';
    $paymentData = [
        'reason' => $reason,
        'tap_id' => $tapId
    ];
    @sendPaymentFailureEmail($customerEmail, $customerName, $paymentData);
}

// -----------------------------------------------------------------------------
// 2. Build index URL for "Try Again" link
// -----------------------------------------------------------------------------
$indexUrl = dirname($_SERVER['SCRIPT_NAME'] ?? '');
if (substr($indexUrl, -1) !== '/') {
    $indexUrl = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
}
$base = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$indexFull = rtrim($base . $indexUrl, '/') . '/index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - Tap Payments</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 480px; margin: 3rem auto; padding: 0 1rem; text-align: center; }
        .fail-icon { font-size: 4rem; color: #dc3545; margin-bottom: 1rem; }
        h1 { color: #dc3545; font-size: 1.5rem; margin-bottom: 0.5rem; }
        p { color: #555; margin-bottom: 1rem; }
        .ref { font-size: 0.875rem; color: #888; word-break: break-all; }
        a { display: inline-block; margin-top: 1.5rem; padding: 0.75rem 1.5rem; background: #0070ba; color: #fff; text-decoration: none; border-radius: 6px; }
        a:hover { background: #005ea6; }
    </style>
</head>
<body>
    <div class="fail-icon">✕</div>
    <h1>Payment Failed</h1>
    <p><?php echo htmlspecialchars($reason); ?></p>
    <p>Please try again or use a different payment method.</p>
    <?php if (!empty($tapId)): ?>
    <p class="ref">Reference: <?php echo htmlspecialchars($tapId); ?></p>
    <?php endif; ?>
    
    <?php if (!empty($customerEmail)): ?>
    <div style="background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 6px; padding: 15px; margin: 20px 0; text-align: left;">
        <p style="margin: 0; color: #721c24; font-size: 14px;">
            <strong>📧 Notification Sent</strong><br>
            <small>A notification email has been sent to: <strong><?php echo htmlspecialchars($customerEmail); ?></strong></small>
        </p>
    </div>
    <?php endif; ?>
    
    <script>
        // Show alert for failed payment
        alert('Payment Not Completed\n\n⚠ Your payment could not be processed.\n📧 A notification has been sent to your email.\n\nPlease try again with a different payment method.');
    </script>
    
    <a href="<?php echo htmlspecialchars($indexFull); ?>">Try Again</a>
</body>
</html>
