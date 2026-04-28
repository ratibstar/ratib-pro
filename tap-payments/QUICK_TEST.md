# Quick Test Guide - 5 Minutes

## 🚀 Quick Test Steps

### 1. Verify Configuration (1 minute)

Visit: `https://yourdomain.com/tap-payments/test-config.php`

**Should show:**
- ✅ Secret key configured
- ✅ API connection successful

### 2. Test Payment (2 minutes)

1. Open: `https://yourdomain.com/tap-payments/index.php`
2. Fill form:
   - Amount: `99.00`
   - Name: `Test Customer`
   - Email: `test@example.com`
3. Click "Pay with Tap"
4. On Tap page, use test card:
   - **Card:** `5123456789012346`
   - **Expiry:** `12/25` (any future date)
   - **CVV:** `123` (any 3 digits)
5. Click "Pay"

**Expected:** ✅ Redirects to success page

### 3. Check Logs (1 minute)

Check: `tap-payments/logs/tap_success.log`

**Should see:**
```
2026-02-13 10:30:45 | tap_id=chg_TS... | ip=...
```

### 4. Test Failed Payment (1 minute)

1. Use decline card: `4000000000000002`
2. Complete payment
3. **Expected:** ✅ Redirects to failed page

## ✅ All Tests Pass?

**You're ready!** See `TESTING_GUIDE.md` for complete testing checklist.

## 🚀 Ready for Live?

After all tests pass:
1. Get live key (`sk_live_xxx`) from Tap Dashboard
2. Update `config.php`:
   ```php
   define('TAP_SECRET_KEY', 'sk_live_YOUR_KEY');
   define('TAP_LIVE_MODE', true);
   ```
3. Test with $1.00 real transaction first!

---

**Full Guide:** See `TESTING_GUIDE.md`
