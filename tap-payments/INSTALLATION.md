# Tap Payments Installation Guide

## Quick Start (5 Minutes)

### Step 1: Get Your Keys

1. Login to [Tap Dashboard](https://tap.company)
2. Go to **Settings → API Keys**
3. Copy your **Test Secret Key** (starts with `sk_test_`)
4. **Note:** Public key (`pk_test_xxx`) is NOT needed for this backend integration

### Step 2: Configure Secret Key

Edit `config.php` line 16:

```php
define('TAP_SECRET_KEY', 'sk_test_YOUR_ACTUAL_KEY_HERE');
```

Replace `sk_test_YOUR_ACTUAL_KEY_HERE` with your actual key from Step 1.

### Step 3: Test Configuration

Visit: `https://yourdomain.com/tap-payments/test-config.php`

This will verify:
- ✓ Secret key is configured correctly
- ✓ API connection works
- ✓ All settings are correct

### Step 4: Test Payment

1. Open `index.php` in browser
2. Fill the form
3. Click "Pay with Tap"
4. Use test card: `5123456789012346` (any expiry, any CVV)
5. Complete payment
6. Should redirect to `success.php`

## File Permissions

Ensure these directories are writable:

```bash
chmod 755 tap-payments/
chmod 755 tap-payments/logs/
```

The `logs/` directory will be created automatically if it doesn't exist.

## Security Checklist

- [ ] Secret key updated in `config.php`
- [ ] `.htaccess` is in place (protects config.php)
- [ ] `logs/` directory is writable
- [ ] HTTPS enabled (for production)
- [ ] `test-config.php` deleted (after testing, for production)

## Troubleshooting

### Error: "charge_failed"

**Most Common Cause:** Secret key not configured

**Fix:**
1. Check `config.php` line 16
2. Ensure key starts with `sk_test_` (test) or `sk_live_` (production)
3. Run `test-config.php` to verify

**See:** `FIX_CHARGE_FAILED.md` for detailed troubleshooting

### Error: "config_error"

**Cause:** Secret key is still placeholder

**Fix:** Update `config.php` with your actual Tap secret key

### Check Error Logs

View detailed errors:
```
tap-payments/logs/tap_errors.log
```

## Integration into Your App

### Option 1: Form POST (Recommended)

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

### Option 2: JavaScript/AJAX

```javascript
fetch('tap-payments/pay.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
        amount: '99.00',
        customer_name: 'John Doe',
        customer_email: 'customer@example.com',
        description: 'Premium Plan',
        registration_id: '123'
    })
})
.then(response => {
    if (response.redirected) {
        window.location.href = response.url;
    }
});
```

## Public Key vs Secret Key

**Secret Key (`sk_test_xxx`):**
- ✅ Required for backend integration
- ✅ Used in `config.php`
- ✅ Never expose to frontend
- ✅ Used to create charges via API

**Public Key (`pk_test_xxx`):**
- ❌ NOT needed for this integration
- Only used for frontend SDK (JavaScript/React)
- This integration uses backend-only approach
- You can ignore the public key

## Production Checklist

Before going live:

- [ ] Secret key updated to `sk_live_xxx`
- [ ] `TAP_LIVE_MODE = true` in config.php
- [ ] HTTPS enabled and verified
- [ ] Webhook configured in Tap Dashboard
- [ ] Test with small real transaction ($1.00)
- [ ] `test-config.php` deleted
- [ ] Error logging enabled
- [ ] Monitor `logs/tap_errors.log`

## Support

- **Documentation:** See `README.md`
- **Troubleshooting:** See `FIX_CHARGE_FAILED.md`
- **Test Config:** Run `test-config.php`
- **Tap Support:** https://tap.company/support
