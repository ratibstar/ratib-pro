<?php
/**
 * EN: Handles application behavior in `tap-payments/index.php`.
 * AR: يدير سلوك جزء من التطبيق في `tap-payments/index.php`.
 */
/**
 * Tap Payments - Demo Checkout Page
 *
 * Simple checkout form that posts to pay.php. In production, integrate this
 * form into your existing SaaS subscription flow.
 */
require_once __DIR__ . '/config.php';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
    . dirname($_SERVER['SCRIPT_NAME']);

// Check for redirect status from verify.php
// NOTE: If Tap redirects here with tap_id, redirect to verify.php for proper verification
$tapId = isset($_GET['tap_id']) ? trim($_GET['tap_id']) : (isset($_GET['charge_id']) ? trim($_GET['charge_id']) : (isset($_GET['id']) ? trim($_GET['id']) : ''));
if (!empty($tapId)) {
    // Tap redirected here with tap_id - redirect to verify.php for proper verification
    header('Location: verify.php?tap_id=' . urlencode($tapId));
    exit;
}

$paymentStatus = isset($_GET['payment_status']) ? $_GET['payment_status'] : (isset($_GET['payment']) ? $_GET['payment'] : '');
$isSuccess = in_array(strtolower($paymentStatus), ['success', 'captured']);
$isFailed = in_array(strtolower($paymentStatus), ['failed', 'cancelled', 'declined', 'config_error', 'charge_failed', 'verify_failed', 'missing_tap_id', 'invalid_charge', 'invalid_amount', 'invalid_email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tap Payments - Checkout</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; max-width: 480px; margin: 2rem auto; padding: 0 1rem; }
        .card { background: #f8f9fa; border-radius: 8px; padding: 1.5rem; margin-bottom: 1rem; }
        .alert { padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-danger { background: #f8d7da; color: #721c24; }
        label { display: block; margin-bottom: 0.25rem; font-weight: 500; }
        input { width: 100%; padding: 0.5rem; margin-bottom: 1rem; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #0070ba; color: #fff; border: none; padding: 0.75rem 1.5rem; border-radius: 6px; font-size: 1rem; cursor: pointer; width: 100%; }
        button:hover { background: #005ea6; }
        h1 { font-size: 1.5rem; margin-top: 0; }
    </style>
</head>
<body>
    <h1>Software Subscription Checkout</h1>

    <?php if ($isSuccess): ?>
    <div class="alert alert-success">Payment successful! Thank you for your subscription.</div>
    <?php endif; ?>

    <?php if ($isFailed): ?>
    <div class="alert alert-danger">
        <strong>Payment Failed</strong><br>
        Status: <?php echo htmlspecialchars($paymentStatus); ?>
        <?php if (isset($_GET['details'])): ?>
        <br><small>Details: <?php echo htmlspecialchars(urldecode($_GET['details'])); ?></small>
        <?php endif; ?>
        <br><small style="margin-top: 0.5rem; display: block;">Check logs/tap_errors.log for more details.</small>
    </div>
    <?php endif; ?>

    <div class="card">
        <form action="pay.php" method="POST" id="checkoutForm">
            <label>Amount (USD) *</label>
            <input type="number" name="amount" step="0.01" min="0.10" max="100000" value="99.00" required>

            <label>Description</label>
            <input type="text" name="description" value="Premium Plan - 1 Year" maxlength="500">

            <label>Customer Name *</label>
            <input type="text" name="customer_name" value="Test Customer" required maxlength="200">

            <label>Customer Email *</label>
            <input type="email" name="customer_email" value="customer@example.com" required>

            <label>Customer Phone (optional)</label>
            <input type="tel" name="customer_phone" value="" placeholder="+966501234567">
            <small style="color: #666; font-size: 0.875rem;">Include country code (e.g., +966 for Saudi Arabia)</small>

            <label>Registration ID (optional)</label>
            <input type="text" name="registration_id" value="" placeholder="123">
            <small style="color: #666; font-size: 0.875rem;">Your order/subscription ID for tracking</small>

            <button type="submit">Pay with Tap</button>
        </form>
    </div>

    <p style="font-size: 0.875rem; color: #666;">
        You will be redirected to Tap's secure payment page. Cards, Mada, and other methods supported.
    </p>

    <script>
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            var amount = parseFloat(this.amount.value);
            if (amount < 0.10 || amount > 100000) {
                e.preventDefault();
                alert('Amount must be between $0.10 and $100,000');
            }
        });
    </script>
</body>
</html>
