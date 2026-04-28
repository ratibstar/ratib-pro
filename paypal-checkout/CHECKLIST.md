# PayPal Integration Setup Checklist

Use this checklist to ensure your PayPal integration is properly configured and secure.

## 📋 Pre-Deployment Checklist

### Environment Setup
- [ ] `.env` file created from `.env.example`
- [ ] `.env` file contains correct PayPal Client ID
- [ ] `.env` file contains correct PayPal Secret
- [ ] `.env` file has correct API base URL (sandbox or live)
- [ ] `.env` file is NOT in git (checked `.gitignore`)
- [ ] `.env` file permissions set to 600 (read/write owner only)
- [ ] `logs/` directory exists and is writable
- [ ] PHP version is 7.4 or higher
- [ ] cURL extension is installed and enabled
- [ ] JSON extension is installed and enabled

### File Structure
- [ ] `config.php` exists and is readable
- [ ] `create-order.php` exists and is accessible
- [ ] `capture-order.php` exists and is accessible
- [ ] `index.php` exists and is accessible
- [ ] `webhook.php` exists (if using webhooks)
- [ ] `test.php` exists (for testing)
- [ ] `.gitignore` includes `.env` and `logs/`

### Security Checks
- [ ] All credentials stored in `.env` (not hardcoded)
- [ ] `.env` file is not web-accessible (outside web root or protected)
- [ ] SSL verification enabled in `config.php` (CURLOPT_SSL_VERIFYPEER = true)
- [ ] Input validation implemented in `create-order.php`
- [ ] Amount validation checks for min/max values
- [ ] Plan validation uses whitelist (gold, platinum)
- [ ] Years validation checks range (1-10)
- [ ] Order ID validation implemented
- [ ] Error messages are generic (no sensitive info exposed)
- [ ] Transaction logging enabled
- [ ] Webhook signature verification enabled (if using webhooks)

### PayPal Configuration
- [ ] PayPal Developer account created
- [ ] Sandbox app created (for testing)
- [ ] Live app created (for production)
- [ ] Client ID and Secret copied correctly
- [ ] Webhook configured (if using webhooks)
- [ ] Webhook URL set correctly
- [ ] Webhook events subscribed (PAYMENT.CAPTURE.COMPLETED, etc.)
- [ ] Webhook ID copied to `.env`

### Frontend Integration
- [ ] PayPal SDK script tag updated with Client ID
- [ ] Checkout page (`index.php`) displays order summary correctly
- [ ] Plan parameter passed correctly from home page
- [ ] Years parameter passed correctly from home page
- [ ] Amount parameter passed correctly from home page
- [ ] Error handling displays user-friendly messages
- [ ] Loading states work correctly
- [ ] Success redirect works correctly
- [ ] Cancel redirect works correctly

### Testing Checklist

#### Sandbox Testing
- [ ] Test with valid PayPal sandbox account
- [ ] Test order creation with valid data
- [ ] Test order creation with invalid plan → Should fail
- [ ] Test order creation with invalid amount → Should fail
- [ ] Test order creation with invalid years → Should fail
- [ ] Test payment approval flow
- [ ] Test payment capture
- [ ] Test payment cancellation
- [ ] Test transaction logging (check `logs/transactions.log`)
- [ ] Test error handling (network failure, invalid JSON, etc.)
- [ ] Test webhook (if configured) → Should verify signature

#### Security Testing
- [ ] Try negative amount → Should fail
- [ ] Try extremely high amount → Should fail
- [ ] Try SQL injection in plan name → Should fail
- [ ] Try XSS in plan name → Should be sanitized
- [ ] Try invalid order ID format → Should fail
- [ ] Test with missing credentials → Should fail gracefully
- [ ] Test with wrong credentials → Should fail gracefully

#### Production Readiness
- [ ] All sandbox tests passed
- [ ] `.env` updated with LIVE credentials
- [ ] `PAYPAL_API_BASE` set to `https://api.paypal.com`
- [ ] PayPal business account verified
- [ ] SSL certificate installed and valid
- [ ] HTTPS enabled (not HTTP)
- [ ] Return URLs updated for production
- [ ] Cancel URLs updated for production
- [ ] Webhook URL updated for production (if using)
- [ ] Test with real PayPal account (small amount)
- [ ] Verify transaction appears in PayPal dashboard
- [ ] Verify transaction logged correctly
- [ ] Monitor for errors in first 24 hours

## 🔍 Verification Steps

### 1. Test Configuration
Visit: `https://yourdomain.com/paypal-checkout/test.php`

Expected output:
- ✅ API Base URL: https://api.sandbox.paypal.com (or live)
- ✅ Client ID: [first 20 chars]...
- ✅ Secret: ***SET***
- ✅ Connection test: Success

### 2. Test Order Creation
Visit: `https://yourdomain.com/paypal-checkout/index.php?plan=gold&years=1&amount=550`

Expected:
- Order summary displays correctly
- PayPal button appears
- Clicking button creates order
- Redirects to PayPal

### 3. Test Payment Flow
1. Click PayPal button
2. Approve payment in PayPal
3. Should redirect back and capture payment
4. Should show success message
5. Check `logs/transactions.log` for entry

### 4. Test Error Handling
- Try invalid URL parameters
- Try missing credentials
- Try network failure (disconnect internet)
- All should fail gracefully with user-friendly errors

## 🚨 Common Issues

### Issue: "PayPal credentials not configured"
**Solution:** Check `.env` file exists and has correct values

### Issue: "cURL extension is required"
**Solution:** Install/enable PHP cURL extension

### Issue: "Failed to create order"
**Solution:** 
- Check credentials are correct
- Check API base URL is correct
- Check internet connection
- Check PayPal API status

### Issue: "Transaction log failed"
**Solution:**
- Check `logs/` directory exists
- Check directory is writable (chmod 755)
- Check disk space available

### Issue: "Webhook signature verification failed"
**Solution:**
- Check `PAYPAL_WEBHOOK_ID` in `.env`
- Verify webhook is configured in PayPal dashboard
- Check webhook URL is correct

## 📞 Support

- PayPal Developer Docs: https://developer.paypal.com/docs/
- PayPal Support: https://developer.paypal.com/support
- Security Issues: security@paypal.com

## ✅ Final Sign-Off

Before going live, ensure:
- [ ] All checklist items completed
- [ ] All tests passed
- [ ] Security review completed
- [ ] Documentation reviewed
- [ ] Team trained on integration
- [ ] Monitoring set up
- [ ] Backup plan ready

**Date:** _______________
**Reviewed by:** _______________
**Approved by:** _______________
