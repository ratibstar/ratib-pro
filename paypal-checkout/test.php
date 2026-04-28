<?php
/**
 * EN: Handles application behavior in `paypal-checkout/test.php`.
 * AR: يدير سلوك جزء من التطبيق في `paypal-checkout/test.php`.
 */
/**
 * PayPal Integration Test Page
 * 
 * Simple test page to verify PayPal credentials and API connectivity.
 * Remove or protect this file in production.
 */

require_once __DIR__ . '/config.php';

// Security: Only allow in development/sandbox
if (strpos(PAYPAL_API_BASE, 'sandbox') === false && !isset($_GET['force'])) {
    die('Test page only available in sandbox mode. Add ?force=1 to override.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Integration Test</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #1a252f; color: #fff; padding: 2rem; }
        .test-container { max-width: 800px; margin: 0 auto; }
        .test-result { padding: 1rem; border-radius: 8px; margin: 1rem 0; }
        .success { background: rgba(46,204,113,0.2); border: 1px solid #2ecc71; }
        .error { background: rgba(231,76,60,0.2); border: 1px solid #e74c3c; }
        .info { background: rgba(52,152,219,0.2); border: 1px solid #3498db; }
        code { background: rgba(0,0,0,0.3); padding: 0.2rem 0.5rem; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="test-container">
        <h1><i class="fas fa-vial"></i> PayPal Integration Test</h1>
        <p class="text-muted">Testing PayPal API connectivity and configuration</p>

        <?php
        // Test 1: Check credentials
        echo '<div class="test-result info">';
        echo '<h3>1. Configuration Check</h3>';
        echo '<p><strong>API Base:</strong> <code>' . htmlspecialchars(PAYPAL_API_BASE) . '</code></p>';
        echo '<p><strong>Client ID:</strong> <code>' . (empty(PAYPAL_CLIENT_ID) ? 'NOT SET' : substr(PAYPAL_CLIENT_ID, 0, 20) . '...') . '</code></p>';
        echo '<p><strong>Secret:</strong> <code>' . (empty(PAYPAL_SECRET) ? 'NOT SET' : '***SET***') . '</code></p>';
        echo '<p><strong>Currency:</strong> <code>' . CURRENCY . '</code></p>';
        echo '<p><strong>Country:</strong> <code>' . COUNTRY_CODE . '</code></p>';
        echo '</div>';

        // Test 2: Get access token
        echo '<div class="test-result">';
        echo '<h3>2. Access Token Test</h3>';
        $token = getPayPalAccessToken();
        if ($token) {
            echo '<div class="success">';
            echo '<p><strong>✓ Success!</strong> Access token obtained.</p>';
            echo '<p><strong>Token:</strong> <code>' . substr($token, 0, 30) . '...</code></p>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<p><strong>✗ Failed!</strong> Could not obtain access token.</p>';
            echo '<p>Check your credentials in .env file.</p>';
            echo '</div>';
        }
        echo '</div>';

        // Test 3: Create test order
        if ($token) {
            echo '<div class="test-result">';
            echo '<h3>3. Create Order Test</h3>';
            $testOrder = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => 'test_' . uniqid(),
                    'description' => 'Test Order',
                    'amount' => [
                        'currency_code' => CURRENCY,
                        'value' => '1.00',
                    ],
                ]],
            ];
            
            $result = paypalApiRequest('/v2/checkout/orders', 'POST', $testOrder);
            if ($result && $result['success'] && isset($result['data']['id'])) {
                echo '<div class="success">';
                echo '<p><strong>✓ Success!</strong> Test order created.</p>';
                echo '<p><strong>Order ID:</strong> <code>' . htmlspecialchars($result['data']['id']) . '</code></p>';
                echo '<p><strong>Status:</strong> <code>' . htmlspecialchars($result['data']['status']) . '</code></p>';
                echo '</div>';
            } else {
                echo '<div class="error">';
                echo '<p><strong>✗ Failed!</strong> Could not create test order.</p>';
                if (isset($result['data']['message'])) {
                    echo '<p><strong>Error:</strong> ' . htmlspecialchars($result['data']['message']) . '</p>';
                }
                echo '</div>';
            }
            echo '</div>';
        }

        // Test 4: File permissions
        echo '<div class="test-result">';
        echo '<h3>4. File Permissions Check</h3>';
        $logsDir = __DIR__ . '/logs';
        if (is_writable($logsDir) || (!is_dir($logsDir) && is_writable(__DIR__))) {
            echo '<div class="success">';
            echo '<p><strong>✓ Success!</strong> Logs directory is writable.</p>';
            echo '</div>';
        } else {
            echo '<div class="error">';
            echo '<p><strong>✗ Warning!</strong> Logs directory may not be writable.</p>';
            echo '<p>Run: <code>chmod 755 ' . htmlspecialchars($logsDir) . '</code></p>';
            echo '</div>';
        }
        echo '</div>';
        ?>

        <div class="test-result info">
            <h3>Next Steps</h3>
            <ol>
                <li>If all tests pass, your integration is ready!</li>
                <li>Update <code>index.php</code> with your PayPal Client ID</li>
                <li>Test the checkout flow with a sandbox account</li>
                <li>Remove or protect this test file in production</li>
            </ol>
        </div>

        <div class="mt-4">
            <a href="index.php?plan=gold&years=1&amount=550" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> Go to Checkout
            </a>
            <a href="../pages/home.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Back to Home
            </a>
        </div>
    </div>
</body>
</html>
