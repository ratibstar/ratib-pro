# Tap Payments Testing Guide - Before Going Live

## 🧪 Complete Testing Checklist

### Step 1: Verify Test Mode Configuration

**Check `config.php`:**

```php
// Should be set to TEST mode:
define('TAP_SECRET_KEY', 'sk_test_YOUR_TEST_KEY');  // Must start with sk_test_
define('TAP_LIVE_MODE', false);  // Must be false for testing
```

✅ **Verify:**
- Secret key starts with `sk_test_` (not `sk_live_`)
- `TAP_LIVE_MODE = false`
- Secret key is your actual test key (not placeholder)

### Step 2: Test Configuration Script

**Run the test script:**
```
https://yourdomain.com/tap-payments/test-config.php
```

**Expected Results:**
- ✅ Secret key is configured
- ✅ API connection successful (HTTP 200)
- ✅ Payment URL received
- ✅ Logs directory writable

**If errors appear:**
- Check `logs/tap_errors.log` for details
- Verify secret key is correct
- Ensure server can reach `api.tap.company`

### Step 3: Test Payment Flow

#### Test Card Details

**Success Card:**
- **Card Number:** `5123456789012346`
- **Expiry:** Any future date (e.g., `12/25`)
- **CVV:** Any 3 digits (e.g., `123`)
- **Name:** Any name

**Decline Card (for testing failures):**
- **Card Number:** `4000000000000002`
- **Expiry:** Any future date
- **CVV:** Any 3 digits

**3D Secure Card (for testing 3DS):**
- **Card Number:** `4000000000003220`
- **Expiry:** Any future date
- **CVV:** Any 3 digits

#### Test Payment Steps

1. **Open Test Checkout:**
   ```
   https://yourdomain.com/tap-payments/index.php
   ```

2. **Fill Test Data:**
   - Amount: `99.00` (or any amount between $0.10 - $100,000)
   - Customer Name: `Test Customer`
   - Customer Email: `test@example.com`
   - Description: `Test Payment`

3. **Click "Pay with Tap"**

4. **On Tap Payment Page:**
   - Enter test card: `5123456789012346`
   - Enter any future expiry: `12/25`
   - Enter any CVV: `123`
   - Click "Pay"

5. **Expected Result:**
   - ✅ Redirects to `success.php`
   - ✅ Shows transaction reference
   - ✅ Logs entry in `logs/tap_success.log`

### Step 4: Test Error Scenarios

#### Test 1: Invalid Amount
- **Test:** Enter amount less than $0.10
- **Expected:** Error message, no redirect to Tap

#### Test 2: Invalid Email
- **Test:** Enter invalid email format
- **Expected:** Error message, no redirect to Tap

#### Test 3: Declined Card
- **Test:** Use decline card `4000000000000002`
- **Expected:** Redirects to `failed.php` with error message

#### Test 4: Cancel Payment
- **Test:** Start payment, then cancel on Tap page
- **Expected:** Redirects to `failed.php`

### Step 5: Verify Logs

**Check Success Log:**
```bash
tail -f tap-payments/logs/tap_success.log
```

**Expected Entry:**
```
2026-02-13 10:30:45 | tap_id=chg_TS123456789 | ip=192.168.1.1
```

**Check Error Log:**
```bash
tail -f tap-payments/logs/tap_errors.log
```

Should be empty if everything works correctly.

### Step 6: Test Webhook (Optional)

**If webhook is configured:**

1. Make a test payment
2. Check `logs/tap_webhook.log`
3. Verify webhook receives `charge.captured` event

**Webhook URL in Tap Dashboard:**
```
https://yourdomain.com/tap-payments/webhook.php
```

### Step 7: Test Integration with Your App

**If integrating into your app:**

1. Replace `index.php` form with your checkout
2. Test with your actual form
3. Verify `registration_id` is passed correctly
4. Check database updates after payment

## ✅ Pre-Live Testing Checklist

Before switching to live mode, verify:

- [ ] Test mode works correctly (`TAP_LIVE_MODE = false`)
- [ ] Test payment completes successfully
- [ ] Success page displays correctly
- [ ] Failed payments handled correctly
- [ ] Transaction logged in `logs/tap_success.log`
- [ ] Error logging works (`logs/tap_errors.log`)
- [ ] Webhook receives events (if configured)
- [ ] Database updates correctly (if integrated)
- [ ] All form validations work
- [ ] Error messages display properly

## 🚀 Switching to Live Mode

**Only after all tests pass!**

### Step 1: Get Live Keys

1. Login to Tap Dashboard
2. Go to **Settings → API Keys**
3. Copy **Live Secret Key** (`sk_live_xxx`)

### Step 2: Update config.php

```php
// Comment out test key:
// define('TAP_SECRET_KEY', 'sk_test_xxx');

// Uncomment and set live key:
define('TAP_SECRET_KEY', 'sk_live_YOUR_LIVE_KEY');
define('TAP_LIVE_MODE', true);  // Change to true
```

### Step 3: Enable HTTPS

- Verify HTTPS is enabled
- SSL certificate is valid
- All URLs use `https://`

### Step 4: Configure Webhook (Production)

1. Go to Tap Dashboard → Developers → Webhooks
2. Add production webhook URL:
   ```
   https://yourdomain.com/tap-payments/webhook.php
   ```
3. Select events: `charge.captured`, `charge.failed`

### Step 5: Test with Small Real Transaction

**IMPORTANT:** Test with real money first!

1. Make a test payment with **$1.00** (minimum)
2. Use your real card
3. Verify:
   - ✅ Payment completes
   - ✅ Webhook receives event
   - ✅ Database updates
   - ✅ Success email sent (if configured)

### Step 6: Monitor Production

- Check `logs/tap_success.log` for successful payments
- Check `logs/tap_errors.log` for any errors
- Monitor Tap Dashboard for transactions
- Set up error alerts

## 🧪 Test Scenarios Summary

| Test | Card Number | Expected Result |
|------|-------------|-----------------|
| Success | `5123456789012346` | Redirects to success.php |
| Decline | `4000000000000002` | Redirects to failed.php |
| 3D Secure | `4000000000003220` | 3DS challenge, then success |
| Invalid Amount | `< $0.10` | Error before redirect |
| Invalid Email | `invalid-email` | Error before redirect |
| Cancel | Any card (cancel) | Redirects to failed.php |

## 📝 Test Results Template

**Date:** _______________

**Test Mode Configuration:**
- [ ] Secret key configured (`sk_test_xxx`)
- [ ] `TAP_LIVE_MODE = false`
- [ ] Test config script passes

**Payment Flow:**
- [ ] Success payment works
- [ ] Failed payment handled
- [ ] Cancel payment handled
- [ ] Error messages display

**Logs:**
- [ ] Success log created
- [ ] Error log empty (or expected errors only)
- [ ] Webhook log (if configured)

**Integration:**
- [ ] Form integration works
- [ ] Database updates correctly
- [ ] Registration ID tracked

**Ready for Live:** [ ] Yes / [ ] No

## ⚠️ Important Notes

1. **Test Mode:** Use `sk_test_` keys and `TAP_LIVE_MODE = false`
2. **No Real Charges:** Test mode doesn't charge real money
3. **Test Cards Only:** Use provided test cards in test mode
4. **Verify Everything:** Test all scenarios before going live
5. **Small Test First:** When going live, test with $1.00 first

## 🆘 Troubleshooting

**If test payment fails:**
1. Check `logs/tap_errors.log`
2. Run `test-config.php`
3. Verify secret key is correct
4. Check server can reach Tap API

**If webhook not working:**
1. Verify webhook URL is accessible
2. Check `logs/tap_webhook.log`
3. Verify webhook is configured in Tap Dashboard

## ✅ Ready for Live?

Only switch to live mode when:
- ✅ All test scenarios pass
- ✅ Test payments work correctly
- ✅ Logs are working
- ✅ Webhook configured (if using)
- ✅ HTTPS enabled
- ✅ Small real transaction tested ($1.00)

**Good luck with your testing!** 🎉
