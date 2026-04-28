# Tap Payments Keys Setup Guide

## You Have Two Keys - Here's What You Need

### ✅ Secret Key (`sk_test_xxx`) - **REQUIRED**

**This is what you MUST configure!**

- **Location:** `config.php` line 16
- **Purpose:** Backend API authentication
- **Used for:** Creating charges, verifying payments
- **Security:** NEVER expose to frontend

**Action Required:**
1. Go to Tap Dashboard → Settings → API Keys
2. Copy your **Test Secret Key** (starts with `sk_test_`)
3. Update `config.php` line 16:
   ```php
   define('TAP_SECRET_KEY', 'sk_test_YOUR_SECRET_KEY_HERE');
   ```

### ❌ Public Key (`pk_test_xxx`) - **NOT NEEDED**

**You can safely ignore this!**

- **Your public key:** `pk_test_••••••••••••••••••`
- **Purpose:** Frontend SDK integrations only
- **Used for:** JavaScript/React/Vue payment forms
- **This integration:** Does NOT use public key

**Action Required:** None - just ignore it!

## Why Only Secret Key?

This integration uses **backend-only approach**:

```
User Form → Your Server (PHP) → Tap API (uses secret key)
                ↓
         Tap Payment Page
                ↓
         Your Server (verify.php)
```

**No frontend JavaScript SDK needed** = **No public key needed**

## Quick Setup

### Step 1: Get Secret Key
1. Login to https://tap.company
2. Go to **Settings → API Keys**
3. Copy **Test Secret Key** (`sk_test_xxx`)

### Step 2: Update config.php
Edit line 16:
```php
define('TAP_SECRET_KEY', 'sk_test_YOUR_ACTUAL_SECRET_KEY');
```

### Step 3: Test
Visit: `https://yourdomain.com/tap-payments/test-config.php`

## Summary

| Key Type | Needed? | Where to Use |
|----------|---------|--------------|
| **Secret Key** (`sk_test_xxx`) | ✅ **YES** | `config.php` line 16 |
| **Public Key** (`pk_test_xxx`) | ❌ **NO** | Ignore it |

## Common Mistake

❌ **Wrong:** Using public key in config.php
```php
define('TAP_SECRET_KEY', 'pk_test_xxx'); // WRONG!
```

✅ **Correct:** Using secret key in config.php
```php
define('TAP_SECRET_KEY', 'sk_test_xxx'); // CORRECT!
```

## Still Confused?

- **Secret Key** = Backend authentication (what you need)
- **Public Key** = Frontend SDK (not needed for this integration)

**Just use the secret key (`sk_test_xxx`) and ignore the public key!**
