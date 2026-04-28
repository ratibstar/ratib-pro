<?php
/**
 * EN: Handles application behavior in `paypal-checkout/success.php`.
 * AR: يدير سلوك جزء من التطبيق في `paypal-checkout/success.php`.
 */
/**
 * Payment Success Page
 * 
 * Optional success page shown after successful payment.
 * You can customize this or redirect to your main application.
 */

$transactionId = isset($_GET['transaction']) ? htmlspecialchars($_GET['transaction']) : '';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Ratib Program</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body { background: #1a252f; color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .success-container { text-align: center; max-width: 500px; padding: 2rem; }
        .success-icon { font-size: 5rem; color: #2ecc71; margin-bottom: 1.5rem; animation: scaleIn 0.5s ease-out; }
        @keyframes scaleIn {
            from { transform: scale(0); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        h1 { font-size: 2rem; margin-bottom: 1rem; }
        .transaction-id { background: rgba(255,255,255,0.1); padding: 1rem; border-radius: 8px; margin: 1.5rem 0; font-family: monospace; }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1>Payment Successful!</h1>
        <p class="lead">Thank you for your payment. Your transaction has been processed successfully.</p>
        <?php if ($transactionId): ?>
        <div class="transaction-id">
            <strong>Transaction ID:</strong><br>
            <?php echo $transactionId; ?>
        </div>
        <?php endif; ?>
        <p>We will process your registration and contact you soon.</p>
        <a href="../pages/home.php" class="btn btn-primary mt-3">
            <i class="fas fa-home me-2"></i> Return to Home
        </a>
    </div>
</body>
</html>
