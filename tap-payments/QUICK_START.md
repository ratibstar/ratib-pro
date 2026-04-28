# Quick Start Guide - 3 Steps

## ⚡ Get Started in 3 Minutes

### Step 1: Update Secret Key (30 seconds)

Open `config.php` and replace line 16:

```php
// Change this:
define('TAP_SECRET_KEY', 'sk_test_xxxxxxxxxxxxxxxxx');

// To your actual key:
define('TAP_SECRET_KEY', 'sk_test_YOUR_ACTUAL_KEY_HERE');
```

**Get your key from:** https://tap.company → Settings → API Keys

### Step 2: Test Configuration (1 minute)

Visit: `https://yourdomain.com/tap-payments/test-config.php`

Should show: ✓ Secret key configured ✓ API connection successful

### Step 3: Test Payment (1 minute)

1. Open: `https://yourdomain.com/tap-payments/index.php`
2. Fill form and click "Pay with Tap"
3. Use test card: `5123456789012346` (any expiry, any CVV)
4. Complete payment → Should redirect to success page

## ✅ Done!

Your Tap Payments integration is ready!

## 🐛 Troubleshooting

**If you see "charge_failed":**
- Secret key not configured → Update `config.php` line 16
- Check `logs/tap_errors.log` for details
- Run `test-config.php` to verify setup

**If you see "config_error":**
- Secret key is still placeholder → Update with real key

## 📚 More Help

- **Full Guide:** See `README.md`
- **Installation:** See `INSTALLATION.md`
- **Fix Errors:** See `FIX_CHARGE_FAILED.md`
- **Test Config:** Run `test-config.php`

## 🔑 Key Points

- ✅ Only need **Secret Key** (`sk_test_xxx`)
- ❌ Don't need **Public Key** (`pk_test_xxx`) - ignore it
- 🔒 Secret key stays in backend only
- 🧪 Test mode uses `sk_test_` keys
- 🚀 Live mode uses `sk_live_` keys

## Next Steps

1. ✅ Configure secret key
2. ✅ Test payment flow
3. ✅ Integrate into your app (replace `index.php` form)
4. ✅ Switch to live mode when ready

**That's it!** 🎉
