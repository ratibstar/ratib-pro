<?php
/**
 * EN: Handles application behavior in `paypal-checkout/index.php`.
 * AR: يدير سلوك جزء من التطبيق في `paypal-checkout/index.php`.
 */
/**
 * PayPal Checkout Page - Redirected to Registration Form
 * 
 * This page is no longer used. Payment is now integrated into the registration form.
 * Redirecting to registration form with plan parameters.
 */
$plan = isset($_GET['plan']) ? htmlspecialchars($_GET['plan']) : 'gold';
$amount = isset($_GET['amount']) ? (float)$_GET['amount'] : 550;
$years = isset($_GET['years']) ? (int)$_GET['years'] : 1;

// Get base URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$path = dirname(dirname($_SERVER['SCRIPT_NAME']));
$baseUrl = $protocol . '://' . $host . $path;

// Redirect to registration form
$redirectUrl = $baseUrl . '/pages/home.php?plan=' . urlencode($plan) . '&amount=' . urlencode($amount) . '&years=' . urlencode($years) . '#register';
header('Location: ' . $redirectUrl);
exit;
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting... - Ratib Program</title>
    <meta http-equiv="refresh" content="0;url=<?php echo htmlspecialchars($redirectUrl); ?>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background: #1a252f; color: #fff; min-height: 100vh; padding: 2rem; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .checkout-container { max-width: 600px; margin: 0 auto; background: rgba(255,255,255,0.05); border-radius: 16px; padding: 2rem; border: 1px solid rgba(255,255,255,0.1); }
        .checkout-header { text-align: center; margin-bottom: 2rem; }
        .checkout-header h1 { font-size: 2rem; margin-bottom: 0.5rem; }
        .checkout-header p { color: #aaa; }
        .order-summary { background: rgba(0,0,0,0.2); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; }
        .order-summary h3 { font-size: 1.2rem; margin-bottom: 1rem; color: #f1c40f; }
        .summary-row { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .summary-row:last-child { border-bottom: none; font-weight: 700; font-size: 1.1rem; color: #f1c40f; }
        .paypal-button-container { margin: 2rem 0; text-align: center; }
        .alert { border-radius: 8px; }
        .loading { display: none; text-align: center; padding: 2rem; }
        .loading.show { display: block; }
        .spinner { border: 3px solid rgba(255,255,255,0.3); border-top-color: #f1c40f; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div class="checkout-header">
            <h1><i class="fas fa-credit-card me-2"></i>Complete Your Payment</h1>
            <p>Secure payment via PayPal</p>
        </div>

        <!-- Order Summary -->
        <div class="order-summary">
            <h3><i class="fas fa-receipt me-2"></i>Order Summary</h3>
            <div class="summary-row">
                <span>Plan:</span>
                <span id="summaryPlan">-</span>
            </div>
            <div class="summary-row">
                <span>Duration:</span>
                <span id="summaryYears">-</span>
            </div>
            <div id="summaryAmount" style="margin-top: 0.5rem;">
                <!-- Tax breakdown will be inserted here by JavaScript -->
            </div>
        </div>

        <!-- Alert Messages -->
        <div id="alertContainer"></div>

        <!-- Loading Indicator -->
        <div class="loading" id="loadingIndicator">
            <div class="spinner"></div>
            <p class="mt-3">Processing payment...</p>
        </div>

        <!-- PayPal Button Container -->
        <div class="paypal-button-container">
            <div id="paypal-button-container"></div>
        </div>

        <!-- Cancel Link -->
        <div class="text-center mt-3">
            <a href="javascript:history.back()" class="text-muted" style="text-decoration: none;">
                <i class="fas fa-arrow-left me-1"></i> Cancel and go back
            </a>
        </div>
    </div>

    <!-- PayPal SDK -->
    <!-- IMPORTANT: Replace YOUR_PAYPAL_CLIENT_ID with your actual Client ID from .env file -->
    <script src="https://www.paypal.com/sdk/js?client-id=YOUR_PAYPAL_CLIENT_ID&currency=USD&intent=capture"></script>
    
    <script>
        /**
         * PayPal Checkout Integration
         * 
         * This script handles the PayPal Smart Button integration.
         * Order creation and capture happen on the backend for security.
         */

        // Get order details from URL or form
        const urlParams = new URLSearchParams(window.location.search);
        const plan = urlParams.get('plan') || 'gold';
        const years = parseInt(urlParams.get('years')) || 1;
        const subtotal = parseFloat(urlParams.get('amount')) || 550;
        const tax = parseFloat(urlParams.get('tax')) || (subtotal * 0.15);
        const total = parseFloat(urlParams.get('total')) || (subtotal + tax);

        // Update order summary
        document.getElementById('summaryPlan').textContent = plan.charAt(0).toUpperCase() + plan.slice(1) + ' Plan';
        document.getElementById('summaryYears').textContent = years + ' year' + (years > 1 ? 's' : '');
        
        // Update summary with tax breakdown
        const summaryAmountEl = document.getElementById('summaryAmount');
        if (summaryAmountEl) {
            summaryAmountEl.innerHTML = `
                <div class="summary-row">
                    <span>Subtotal:</span>
                    <span>$${subtotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                </div>
                <div class="summary-row">
                    <span>Tax (15%):</span>
                    <span>$${tax.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                </div>
                <div class="summary-row" style="border-top: 2px solid rgba(255,255,255,0.2); margin-top: 0.5rem; padding-top: 0.75rem; font-weight: 700; font-size: 1.1rem; color: #f1c40f;">
                    <span>Total:</span>
                    <span>$${total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                </div>
            `;
        }

        // Check if payment was canceled
        if (urlParams.get('canceled') === '1') {
            showAlert('Payment was canceled. You can try again.', 'warning');
        }

        /**
         * Show Alert Message
         */
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : type === 'warning' ? 'alert-warning' : 'alert-info';
            alertContainer.innerHTML = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
        }

        /**
         * Create PayPal Order
         * 
         * Called when user clicks PayPal button.
         * Creates order on backend and returns order ID.
         */
        async function createOrder() {
            try {
                const response = await fetch('create-order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        plan: plan,
                        years: years,
                        amount: subtotal,
                        tax: tax,
                        total: total,
                    }),
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to create order');
                }

                return data.data.orderId;
            } catch (error) {
                console.error('Create Order Error:', error);
                showAlert('Failed to create order: ' + error.message, 'error');
                throw error;
            }
        }

        /**
         * Capture PayPal Order
         * 
         * Called after user approves payment on PayPal.
         * Captures payment on backend.
         */
        async function captureOrder(orderId) {
            try {
                document.getElementById('loadingIndicator').classList.add('show');
                document.getElementById('paypal-button-container').style.display = 'none';

                const response = await fetch('capture-order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        orderId: orderId,
                    }),
                });

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Failed to capture payment');
                }

                // Success - show success message
                showAlert(
                    `Payment successful! Transaction ID: ${data.data.transactionId}`,
                    'success'
                );

                // Log transaction (optional - for frontend tracking)
                console.log('Payment Captured:', data.data);

                // Redirect to success page or show success message
                // You can customize this based on your needs
                setTimeout(() => {
                    // Option 1: Redirect to success page
                    // window.location.href = 'success.php?transaction=' + data.data.transactionId;
                    
                    // Option 2: Show success message (current)
                    document.getElementById('loadingIndicator').classList.remove('show');
                    document.getElementById('loadingIndicator').innerHTML = `
                        <div class="text-center">
                            <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                            <h3>Payment Successful!</h3>
                            <p>Transaction ID: <strong>${data.data.transactionId}</strong></p>
                            <p class="text-muted">We will process your registration and contact you soon.</p>
                        </div>
                    `;
                    document.getElementById('loadingIndicator').classList.add('show');
                }, 1000);

            } catch (error) {
                console.error('Capture Order Error:', error);
                showAlert('Payment failed: ' + error.message, 'error');
                document.getElementById('loadingIndicator').classList.remove('show');
                document.getElementById('paypal-button-container').style.display = 'block';
            }
        }

        // Initialize PayPal Buttons
        if (typeof paypal !== 'undefined') {
            paypal.Buttons({
                /**
                 * Create Order Handler
                 * 
                 * Called when user clicks PayPal button.
                 * Must return a Promise that resolves to an order ID.
                 */
                createOrder: function(data, actions) {
                    return createOrder();
                },

                /**
                 * On Approve Handler
                 * 
                 * Called after user approves payment on PayPal.
                 * We capture the payment on our backend.
                 */
                onApprove: function(data, actions) {
                    return captureOrder(data.orderID);
                },

                /**
                 * On Cancel Handler
                 * 
                 * Called if user cancels payment on PayPal.
                 */
                onCancel: function(data) {
                    showAlert('Payment was canceled. You can try again.', 'warning');
                },

                /**
                 * On Error Handler
                 * 
                 * Called if there's an error during the payment process.
                 */
                onError: function(err) {
                    console.error('PayPal Error:', err);
                    showAlert('An error occurred: ' + err.message, 'error');
                },

                /**
                 * Button Style
                 * 
                 * Customize PayPal button appearance
                 */
                style: {
                    layout: 'vertical', // vertical or horizontal
                    color: 'gold', // gold, blue, silver, white, black
                    shape: 'rect', // pill or rect
                    label: 'paypal', // paypal, checkout, buynow, pay, credit
                    height: 50,
                },
            }).render('#paypal-button-container');
        } else {
            showAlert('PayPal SDK failed to load. Please check your internet connection.', 'error');
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
