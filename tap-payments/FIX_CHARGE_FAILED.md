# Fix "charge_failed" Error

## Common Causes

### 1. Secret Key Not Configured ⚠️ **MOST COMMON**

**Problem:** Secret key is still set to placeholder `sk_test_xxxxxxxxxxxxxxxxx`

**Solution:**
1. Open `config.php`
2. Go to line 16
3. Replace `'sk_test_xxxxxxxxxxxxxxxxx'` with your actual Tap secret key
4. Get your key from: https://tap.company → Settings → API Keys

**Example:**
```php
// Before (wrong):
define('TAP_SECRET_KEY', 'sk_test_xxxxxxxxxxxxxxxxx');

// After (correct):
define('TAP_SECRET_KEY', 'sk_test_AbCdEf1234567890XyZ');
```

### 2. Invalid Secret Key

**Problem:** Secret key is incorrect or expired

**Solution:**
- Verify key in Tap Dashboard
- Ensure you're using TEST key for test mode
- Ensure you're using LIVE key for live mode
- Keys start with `sk_test_` (test) or `sk_live_` (production)

### 3. API Connection Issues

**Problem:** Server cannot reach Tap API

**Solution:**
- Check server firewall allows outbound HTTPS
- Verify cURL is enabled: `php -m | grep curl`
- Test connection: Run `test-config.php`

### 4. Invalid Request Data

**Problem:** Missing required fields or invalid format

**Solution:**
- Check all form fields are filled
- Verify email format is valid
- Ensure amount is between $0.10 and $100,000

## Debugging Steps

### Step 1: Check Configuration

Run the test script:
```
https://yourdomain.com/tap-payments/test-config.php
```

This will show:
- ✓ Secret key status
- ✓ API connection test
- ✓ Error logs

### Step 2: Check Error Logs

View detailed errors:
```
tap-payments/logs/tap_errors.log
```

The log shows:
- HTTP status codes
- Tap API error messages
- Request payload
- Response details

### Step 3: Verify Secret Key

1. Login to Tap Dashboard: https://tap.company
2. Go to **Settings → API Keys**
3. Copy your **Test Secret Key** (starts with `sk_test_`)
4. Update `config.php` line 16

### Step 4: Test Again

After updating secret key:
1. Clear browser cache
2. Try payment again
3. Check `logs/tap_errors.log` if it still fails

## Quick Fix Checklist

- [ ] Secret key updated in `config.php` (line 16)
- [ ] Secret key starts with `sk_test_` (test mode)
- [ ] No extra spaces or quotes around key
- [ ] Tested with `test-config.php`
- [ ] Checked `logs/tap_errors.log` for details
- [ ] Verified server has internet access
- [ ] cURL extension is enabled

## Still Not Working?

1. **Check Error Log:**
   ```bash
   tail -f tap-payments/logs/tap_errors.log
   ```

2. **Run Test Script:**
   ```
   https://yourdomain.com/tap-payments/test-config.php
   ```

3. **Verify Tap Dashboard:**
   - Account is active
   - API keys are enabled
   - Test mode is enabled (for test keys)

4. **Contact Support:**
   - Tap Support: https://tap.company/support
   - Include error log details

## Example Error Log Entry

```
[2026-02-13 10:30:45] [192.168.1.1] Tap API Error: HTTP 401 | {"error":"Unauthorized","http_code":401,"response":"{\"errors\":[{\"code\":\"UNAUTHORIZED\",\"message\":\"Invalid API key\"}]}"}
```

This shows:
- **HTTP 401** = Authentication failed
- **Invalid API key** = Secret key is wrong

**Fix:** Update secret key in `config.php`
