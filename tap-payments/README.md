# Tap Payments Integration - Complete Guide

## Overview
Secure Tap Payments integration for SaaS software subscriptions using plain PHP, cURL, and Tap REST API.

**Payment Flow:**
1. User submits checkout form → `index.php`
2. Form posts to → `pay.php` (creates charge via Tap API)
3. User redirected to → Tap hosted payment page
4. Tap redirects to → `verify.php` (verifies payment server-side)
5. User redirected to → `success.php` or `failed.php`

## Folder Structure

```
tap-payments/
├── config.php      # Configuration (secret key, API URL, mode)
├── index.php       # Demo checkout page (integrate into your app)
├── pay.php         # Create charge and redirect to Tap
├── verify.php      # Verify payment status (server-side)
├── success.php     # Success page (logs transaction)
├── failed.php      # Failed page
├── webhook.php     # Webhook handler (optional, for async updates)
└── logs/           # Transaction logs (auto-created)
    ├── tap_success.log
    └── tap_webhook.log
```

## Setup Instructions

### 1. Configure Secret Key

Edit `config.php` and replace with your actual secret key:

```php
// TEST MODE
define('TAP_SECRET_KEY', 'sk_test_xxxxxxxxxxxxxxxxx');

// LIVE MODE (after testing)
define('TAP_SECRET_KEY', 'sk_live_xxxxxxxxxxxxxxxxx');
define('TAP_LIVE_MODE', true);
```

**⚠️ SECURITY:**
- Secret key MUST stay in backend only
- Never expose secret key in frontend JavaScript
- Keep `config.php` outside web root or protect with `.htaccess`
- Use environment variables in production (optional)

### 2. Get Your Keys

1. Login to [Tap Dashboard](https://tap.company)
2. Go to **Settings > API Keys**
3. Copy your **Test Secret Key** (`sk_test_xxx`)
4. For production, copy **Live Secret Key** (`sk_live_xxx`)

### 3. Test Integration

1. Open `index.php` in browser
2. Fill checkout form with test data
3. Click "Pay with Tap"
4. Use Tap test card: `5123456789012346` (any future expiry, any CVV)
5. Verify redirect to `success.php` after payment

### 4. Integrate into Your App

Replace `index.php` demo form with your actual checkout:

```php
<form action="tap-payments/pay.php" method="POST">
    <input type="hidden" name="amount" value="99.00">
    <input type="hidden" name="customer_name" value="John Doe">
    <input type="hidden" name="customer_email" value="customer@example.com">
    <input type="hidden" name="description" value="Premium Plan - 1 Year">
    <input type="hidden" name="registration_id" value="123">
    <button type="submit">Pay Now</button>
</form>
```

## Switching to Live Mode

### Step-by-Step Guide

1. **Get Live Keys**
   - Login to Tap Dashboard
   - Go to Settings > API Keys
   - Copy live secret key (`sk_live_xxx`)

2. **Update config.php**
   ```php
   // Comment out test key
   // define('TAP_SECRET_KEY', 'sk_test_xxx');
   
   // Uncomment and set live key
   define('TAP_SECRET_KEY', 'sk_live_xxxxxxxxxxxxxxxxx');
   define('TAP_LIVE_MODE', true);
   ```

3. **Enable HTTPS**
   - Tap requires HTTPS for production
   - Verify SSL certificate is valid
   - Test all URLs use `https://`

4. **Configure Webhook** (Recommended)
   - Go to Tap Dashboard > Developers > Webhooks
   - Add URL: `https://yourdomain.com/tap-payments/webhook.php`
   - Select events: `charge.captured`, `charge.failed`
   - Save webhook signing key (optional)

5. **Test with Real Transaction**
   - Test with small amount ($1.00)
   - Verify webhook receives events
   - Check database updates correctly
   - Monitor logs for errors

6. **Monitor Production**
   - Check `logs/tap_success.log` for successful payments
   - Check `logs/tap_webhook.log` for webhook events
   - Set up error alerts

## API Endpoints

### POST `/pay.php`
Creates a charge and redirects to Tap payment page.

**Parameters:**
- `amount` (required): Payment amount (USD, min 0.10)
- `customer_name` (required): Customer full name
- `customer_email` (required): Valid email address
- `description` (optional): Payment description
- `registration_id` (optional): Your order/subscription ID

**Response:** Redirects to Tap hosted payment page

### GET `/verify.php?tap_id=xxx`
Verifies payment status after Tap redirect.

**Parameters:**
- `tap_id` (required): Charge ID from Tap

**Response:** Redirects to `success.php` or `failed.php`

### POST `/webhook.php`
Receives server-to-server notifications from Tap.

**Events:**
- `charge.captured` - Payment successful
- `charge.failed` - Payment failed
- `charge.cancelled` - Payment cancelled

## Security Features

✅ **Amount Validation** - Server-side validation prevents manipulation  
✅ **HTTPS Required** - All production URLs use HTTPS  
✅ **Secret Key Protection** - Never exposed to frontend  
✅ **Server-Side Verification** - Payment status verified via API  
✅ **Input Sanitization** - All inputs sanitized and validated  
✅ **Transaction Logging** - All successful payments logged  

## Payment Status Flow

```
Pending → pay.php creates charge
    ↓
Tap Payment Page (user enters card)
    ↓
CAPTURED → verify.php → success.php → Log transaction
    ↓
FAILED/CANCELLED → verify.php → failed.php
```

## Webhook Setup (Optional)

Webhooks provide async payment notifications. Recommended for production.

### Setup Steps:

1. **Configure in Tap Dashboard**
   - URL: `https://yourdomain.com/tap-payments/webhook.php`
   - Events: `charge.captured`, `charge.failed`

2. **Verify Webhook** (Optional)
   - Get webhook signing key from dashboard
   - Uncomment signature verification in `webhook.php`
   - Add: `define('TAP_WEBHOOK_SECRET', 'whsec_xxx');`

3. **Test Webhook**
   - Make test payment
   - Check `logs/tap_webhook.log`
   - Verify database updates

## Error Handling

All errors redirect with status codes:

- `config_error` - Secret key not configured
- `invalid_amount` - Amount outside valid range
- `invalid_email` - Invalid email address
- `charge_failed` - Failed to create charge
- `verification_failed` - Failed to verify payment
- `missing_tap_id` - Missing charge ID

## Testing

### Test Cards (Test Mode Only)

- **Success:** `5123456789012346` (any expiry, any CVV)
- **Decline:** `4000000000000002`
- **3D Secure:** `4000000000003220`

### Test Amounts

- Minimum: $0.10 USD
- Maximum: $100,000.00 USD

## Troubleshooting

### Payment stuck on "Loading..."
- Check `config.php` has valid secret key
- Verify HTTPS is enabled (production)
- Check browser console for errors

### Redirect to failed.php
- Check `logs/tap_webhook.log` for details
- Verify charge was created in Tap Dashboard
- Check network connectivity to Tap API

### Webhook not receiving events
- Verify webhook URL is accessible (public HTTPS)
- Check Tap Dashboard webhook status
- Review `logs/tap_webhook.log` for errors

## Support

- **Tap Documentation:** https://tap.company/docs
- **Tap Dashboard:** https://tap.company
- **API Reference:** https://tap.company/docs/api

## License

Production-ready code for SaaS subscriptions. Customize as needed.
