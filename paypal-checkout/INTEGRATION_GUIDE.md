# PayPal Checkout Integration Guide

## Quick Start

### 1. Setup Credentials

1. Create `.env` file from `.env.example`
2. Add your PayPal sandbox credentials
3. Update `index.php` with your Client ID

### 2. Test Integration

Visit: `test.php` to verify configuration

### 3. Use in Your Application

Link to checkout from your registration form:

```php
// Example: Link from home.php registration form
$paypalUrl = $baseUrl . '/paypal-checkout/index.php';
$paypalUrl .= '?plan=' . urlencode($plan);
$paypalUrl .= '&years=' . (int)$years;
$paypalUrl .= '&amount=' . (float)$amount;

echo '<a href="' . htmlspecialchars($paypalUrl) . '" class="btn btn-primary">Pay with PayPal</a>';
```

## Integration with Registration Form

### Option 1: Redirect to PayPal After Form Submission

In your registration form handler (`api/registration-request.php`):

```php
// After saving registration request
if ($planAmount > 0) {
    // Redirect to PayPal checkout
    $checkoutUrl = getBaseUrl() . '/paypal-checkout/index.php';
    $checkoutUrl .= '?plan=' . urlencode($plan);
    $checkoutUrl .= '&years=' . (int)$years;
    $checkoutUrl .= '&amount=' . (float)$planAmount;
    $checkoutUrl .= '&reg_id=' . (int)$registrationId; // Optional: link to registration
    
    header('Location: ' . $checkoutUrl);
    exit;
}
```

### Option 2: Add PayPal Button to Registration Page

In `pages/home.php` or `pages/agency-request.php`, add PayPal button:

```php
<?php if ($planAmount > 0): ?>
<div class="mt-3">
    <a href="<?php echo htmlspecialchars($baseUrl . '/paypal-checkout/index.php?plan=' . urlencode($plan) . '&years=' . (int)$years . '&amount=' . (float)$planAmount); ?>" 
       class="btn btn-warning btn-lg w-100">
        <i class="fab fa-paypal me-2"></i> Pay with PayPal - $<?php echo number_format($planAmount, 2); ?>
    </a>
</div>
<?php endif; ?>
```

## Handling Payment Success

After successful payment, you can:

1. **Update Registration Status** - Mark registration as paid
2. **Send Confirmation Email** - Notify user and admin
3. **Activate Account** - Auto-approve if payment received
4. **Log Transaction** - Already handled in `capture-order.php`

### Example: Update Registration After Payment

In `capture-order.php`, after successful capture:

```php
// After logTransaction() call, add:

// Update registration request status
if (isset($input['reg_id'])) {
    $regId = (int)$input['reg_id'];
    // Update your database
    // $conn->query("UPDATE control_registration_requests SET payment_status = 'paid', transaction_id = '$transactionId' WHERE id = $regId");
}
```

## Security Checklist

- [ ] `.env` file exists and has correct credentials
- [ ] `.env` is in `.gitignore` (never committed)
- [ ] Using HTTPS in production
- [ ] SSL verification enabled (CURLOPT_SSL_VERIFYPEER = true)
- [ ] Amount validation on backend
- [ ] Input sanitization implemented
- [ ] Error messages don't expose sensitive info
- [ ] Transaction logging enabled
- [ ] Webhook signature verification (if using webhooks)
- [ ] Rate limiting considered (prevent abuse)

## Production Checklist

- [ ] Switch to live PayPal credentials
- [ ] Update API base URL to live
- [ ] Update return URLs to production domain
- [ ] Test with real PayPal account (small amount)
- [ ] Set up webhook for async notifications
- [ ] Configure proper file permissions
- [ ] Set up monitoring/alerting
- [ ] Document transaction flow
- [ ] Train staff on PayPal dashboard

## Common Issues

### "Invalid Client ID"
- Check `.env` file has correct Client ID
- Verify Client ID matches sandbox/live environment
- Ensure no extra spaces in `.env` file

### "Access Token Failed"
- Verify Secret is correct
- Check API base URL matches environment
- Ensure credentials are for same PayPal account

### "Order Creation Failed"
- Check amount format (must be "550.00" not "550")
- Verify currency code is valid
- Check PayPal account status

### "Capture Failed"
- Order may have expired (15 minutes)
- User may have canceled
- Check order status in PayPal dashboard

## Support

- PayPal Developer Docs: https://developer.paypal.com/docs/
- PayPal Support: https://developer.paypal.com/support/
- Test this integration: `test.php`
