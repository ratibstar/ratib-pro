# Quick Setup Guide

## 1. Configure Secret Key

Edit `config.php` line 15:

```php
define('TAP_SECRET_KEY', 'sk_test_xxxxxxxxxxxxxxxxx');
```

Replace `sk_test_xxxxxxxxxxxxxxxxx` with your actual test secret key from Tap Dashboard.

## 2. Test Integration

1. Open `index.php` in browser
2. Fill form and click "Pay with Tap"
3. Use test card: `5123456789012346` (any expiry, any CVV)
4. Verify redirect to `success.php`

## 3. Integrate into Your App

Replace demo form in `index.php` with your checkout:

```php
<form action="tap-payments/pay.php" method="POST">
    <input type="hidden" name="amount" value="99.00">
    <input type="hidden" name="customer_name" value="John Doe">
    <input type="hidden" name="customer_email" value="customer@example.com">
    <input type="hidden" name="description" value="Premium Plan">
    <input type="hidden" name="registration_id" value="123">
    <button type="submit">Pay Now</button>
</form>
```

## 4. Switch to Live Mode

1. Get live key: `sk_live_xxx` from Tap Dashboard
2. Update `config.php`:
   ```php
   define('TAP_SECRET_KEY', 'sk_live_xxxxxxxxxxxxxxxxx');
   define('TAP_LIVE_MODE', true);
   ```
3. Enable HTTPS (required)
4. Configure webhook: `https://yourdomain.com/tap-payments/webhook.php`

## File Structure

```
tap-payments/
├── config.php      # Secret key configuration
├── index.php       # Demo checkout (replace with your form)
├── pay.php         # Creates charge, redirects to Tap
├── verify.php      # Verifies payment, redirects to success/failed
├── success.php     # Success page (logs transaction)
├── failed.php      # Failed page
├── webhook.php     # Webhook handler (optional)
└── logs/           # Transaction logs (auto-created)
```

## Security Checklist

- ✅ Secret key only in backend (`config.php`)
- ✅ Amount validation (prevents manipulation)
- ✅ Server-side payment verification
- ✅ HTTPS enforced in production
- ✅ Input sanitization
- ✅ Transaction logging

## Support

- Full documentation: See `README.md`
- Tap Docs: https://tap.company/docs
- Tap Dashboard: https://tap.company
