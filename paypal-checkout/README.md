# PayPal Checkout Integration - Secure Implementation

Complete PayPal REST API v2 integration for Ratib Program with backend order creation and capture.

## 🔒 Security Features

- ✅ Backend-only order creation (prevents tampering)
- ✅ Backend-only payment capture (prevents fraud)
- ✅ Client secret never exposed to frontend
- ✅ SSL verification enabled
- ✅ Input validation and sanitization
- ✅ Amount range validation
- ✅ Transaction logging
- ✅ Error handling with proper HTTP codes

## 📁 File Structure

```
paypal-checkout/
├── config.php           # Configuration and API functions
├── create-order.php     # Backend endpoint to create PayPal orders
├── capture-order.php    # Backend endpoint to capture payments
├── index.php            # Frontend checkout page
├── .env.example         # Environment variables template
├── .env                 # Your actual credentials (DO NOT COMMIT)
├── logs/                # Transaction logs directory
└── README.md           # This file
```

## 🚀 Setup Instructions

### 1. Get PayPal Credentials

**Sandbox Mode (Testing):**
1. Go to https://developer.paypal.com
2. Sign in or create account
3. Navigate to Dashboard → Apps & Credentials
4. Create a new app (Sandbox)
5. Copy Client ID and Secret

**Live Mode (Production):**
1. Same process but use Live credentials
2. Ensure your PayPal business account is verified
3. Complete PayPal business verification

### 2. Configure Environment Variables

1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```

2. Edit `.env` and add your credentials:
   ```env
   PAYPAL_API_BASE=https://api.sandbox.paypal.com
   PAYPAL_CLIENT_ID=your_client_id_here
   PAYPAL_SECRET=your_secret_here
   ```

3. **IMPORTANT:** Add `.env` to `.gitignore`:
   ```
   .env
   logs/
   ```

### 3. Update Frontend Client ID

Edit `index.php` line with PayPal SDK:
```html
<script src="https://www.paypal.com/sdk/js?client-id=YOUR_PAYPAL_CLIENT_ID&currency=USD&intent=capture"></script>
```

Replace `YOUR_PAYPAL_CLIENT_ID` with your actual Client ID.

**Note:** Client ID can be public (it's safe to expose), but Secret must stay on backend.

### 4. Set File Permissions

```bash
chmod 755 paypal-checkout/
chmod 644 paypal-checkout/*.php
chmod 600 paypal-checkout/.env
chmod 755 paypal-checkout/logs/
```

### 5. Test the Integration

1. Open `index.php` in browser with parameters:
   ```
   index.php?plan=gold&years=1&amount=550
   ```

2. Click PayPal button
3. Use PayPal sandbox test account:
   - Email: sb-xxxxx@business.example.com
   - Password: (from PayPal dashboard)

## 🔄 Switching from Sandbox to Live

### Step 1: Update Environment Variables

Edit `.env`:
```env
PAYPAL_API_BASE=https://api.paypal.com
PAYPAL_CLIENT_ID=your_live_client_id
PAYPAL_SECRET=your_live_secret
```

### Step 2: Update Frontend Client ID

Update `index.php` PayPal SDK script to use live Client ID:
```html
<script src="https://www.paypal.com/sdk/js?client-id=YOUR_LIVE_CLIENT_ID&currency=USD&intent=capture"></script>
```

### Step 3: Update Return URLs (Optional)

In `.env`, set production URLs:
```env
PAYPAL_RETURN_URL=https://yourdomain.com/paypal-checkout/capture-order.php
PAYPAL_CANCEL_URL=https://yourdomain.com/paypal-checkout/index.php
```

### Step 4: Test with Real Account

Use a real PayPal account (small amount) to verify everything works.

## 📝 API Endpoints

### POST /create-order.php

Creates a PayPal order on backend.

**Request:**
```json
{
  "plan": "gold",
  "years": 1,
  "amount": 550
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "orderId": "5O190127TN364715T",
    "status": "CREATED"
  }
}
```

### POST /capture-order.php

Captures payment after user approval.

**Request:**
```json
{
  "orderId": "5O190127TN364715T"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "orderId": "5O190127TN364715T",
    "transactionId": "3C679866HH908993F",
    "amount": "550.00",
    "currency": "USD",
    "status": "COMPLETED"
  }
}
```

## 🔐 Security Best Practices

1. **Never expose PayPal Secret** - Only Client ID goes to frontend
2. **Validate amounts server-side** - Prevent price tampering
3. **Use HTTPS** - Required for production
4. **Verify webhooks** - If using webhooks, verify signatures
5. **Log transactions** - Keep records for disputes
6. **Rate limiting** - Add rate limiting to prevent abuse
7. **Input validation** - Always validate and sanitize inputs
8. **Error handling** - Don't expose sensitive error details

## 📊 Transaction Logging

Successful transactions are logged to `logs/transactions.log`:
```
[2026-02-13 14:30:25] Order: 5O190127TN364715T | Transaction: 3C679866HH908993F | Amount: 550.00 USD | IP: 192.168.1.1
```

## 🐛 Troubleshooting

### "Failed to create order"
- Check `.env` file exists and has correct credentials
- Verify PayPal API base URL is correct
- Check PHP error logs

### "Invalid order ID format"
- Ensure order ID comes from PayPal (not tampered)
- Check order ID format matches PayPal pattern

### "Payment not completed"
- User may have canceled on PayPal
- Check PayPal account status
- Verify order status in PayPal dashboard

### PayPal SDK not loading
- Check internet connection
- Verify Client ID is correct
- Check browser console for errors

## 📚 Additional Resources

- PayPal REST API Docs: https://developer.paypal.com/docs/api/orders/v2/
- PayPal Developer Dashboard: https://developer.paypal.com/dashboard
- PayPal Testing Guide: https://developer.paypal.com/docs/api-basics/sandbox/

## ⚠️ Important Notes

1. **Sandbox vs Live**: Always test thoroughly in sandbox before going live
2. **Webhooks**: Consider implementing webhooks for async payment notifications
3. **Refunds**: Implement refund functionality if needed
4. **Compliance**: Ensure compliance with local payment regulations (Saudi Arabia)
5. **Testing**: Use PayPal test accounts for all testing

## 📞 Support

For PayPal API issues, contact PayPal Developer Support:
- https://developer.paypal.com/support/

For integration issues, check error logs in `logs/` directory.
