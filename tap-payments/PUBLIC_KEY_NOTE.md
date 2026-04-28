# Public Key vs Secret Key - Important Note

## You Have Both Keys - Which One Do You Need?

### ✅ Secret Key (`sk_test_xxx` or `sk_live_xxx`)
**REQUIRED for this integration**

- Used in: `config.php` (line 16)
- Purpose: Backend API authentication
- Security: **NEVER expose to frontend**
- Used to: Create charges, verify payments, access Tap API

**This is what you need to configure!**

### ❌ Public Key (`pk_test_xxx` or `pk_live_xxx`)
**NOT needed for this integration**

- Used for: Frontend SDK integrations (JavaScript/React/Vue)
- Purpose: Client-side payment forms
- Security: Safe to expose in frontend
- Not used: In this backend-only integration

## Why This Integration Doesn't Need Public Key

This integration uses a **backend-only approach**:

1. ✅ All API calls happen server-side (PHP)
2. ✅ Secret key stays secure in backend
3. ✅ User redirected to Tap hosted payment page
4. ✅ No frontend JavaScript SDK needed

**Result:** Public key is not required!

## If You Want to Use Public Key (Optional)

If you want to use Tap's frontend SDK instead:

1. Include Tap JavaScript SDK in your HTML
2. Use public key in frontend JavaScript
3. Create payment form with Tap SDK
4. This is a different integration approach

**But for this integration:** You only need the secret key.

## Summary

- ✅ **Configure:** Secret key in `config.php`
- ❌ **Ignore:** Public key (not needed)
- 🔒 **Security:** Secret key stays in backend only

## Current Setup

Your `config.php` should have:

```php
define('TAP_SECRET_KEY', 'sk_test_YOUR_SECRET_KEY');
```

**That's all you need!** The public key can be safely ignored for this integration.
