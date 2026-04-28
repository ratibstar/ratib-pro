<?php
/**
 * EN: Handles application behavior in `tap-payments/config.php`.
 * AR: يدير سلوك جزء من التطبيق في `tap-payments/config.php`.
 */
/**
 * Tap Payments Configuration
 * 
 * TEST MODE: Use pk_test_xxx and sk_test_xxx
 * LIVE MODE: Use pk_live_xxx and sk_live_xxx (set TAP_LIVE_MODE = true)
 * 
 * Secret key MUST stay in backend only. Never expose in frontend.
 * 
 * SECURITY: This file should be outside web root or protected by .htaccess
 */
// -----------------------------------------------------------------------------
// 1. Secret Key - Replace with your actual Tap Payments secret key
// -----------------------------------------------------------------------------
// TEST MODE: Use your test secret key from Tap Dashboard
define('TAP_SECRET_KEY', 'sk_test_xxxxxxxxxxxxxxxxx');

// LIVE MODE: Uncomment and use live secret key (after testing)
// define('TAP_SECRET_KEY', 'sk_live_xxxxxxxxxxxxxxxxx');

// -----------------------------------------------------------------------------
// Public Key (Optional - NOT used in this integration)
// -----------------------------------------------------------------------------
// Your public key: pk_test_••••••••••••••••••
// NOTE: Public key is NOT needed for this backend-only integration
// Public key is only used for frontend SDK integrations (JavaScript/React/etc)
// This integration uses backend-only approach, so only secret key is required
// You can safely ignore the public key - it won't be used anywhere

// -----------------------------------------------------------------------------
// 2. API URL - charges endpoint
// -----------------------------------------------------------------------------
define('TAP_API_URL', 'https://api.tap.company/v2/charges');

// -----------------------------------------------------------------------------
// 3. Mode: TEST or LIVE
// -----------------------------------------------------------------------------
define('TAP_LIVE_MODE', false); // Set true for production

/**
 * HOW TO SWITCH TO LIVE MODE:
 * 
 * Step 1: Get live keys from Tap Dashboard
 *   - Login to https://tap.company dashboard
 *   - Go to Settings > API Keys
 *   - Copy your live secret key (sk_live_xxx)
 * 
 * Step 2: Update config.php
 *   - Comment out: define('TAP_SECRET_KEY', 'sk_test_xxx');
 *   - Uncomment: define('TAP_SECRET_KEY', 'sk_live_xxx');
 *   - Set: define('TAP_LIVE_MODE', true);
 * 
 * Step 3: Ensure HTTPS is enabled
 *   - Tap requires HTTPS for production
 *   - Verify SSL certificate is valid
 * 
 * Step 4: Configure webhook in Tap Dashboard
 *   - Go to Developers > Webhooks
 *   - Add webhook URL: https://yourdomain.com/tap-payments/webhook.php
 *   - Select events: charge.captured, charge.failed
 *   - Save webhook signing key (optional but recommended)
 * 
 * Step 5: Test with small real transaction
 *   - Test with $1.00 first
 *   - Verify webhook receives events
 *   - Check database updates correctly
 * 
 * Step 6: Monitor logs
 *   - Check tap-payments/logs/tap_success.log
 *   - Check tap-payments/logs/tap_webhook.log
 *   - Monitor for errors in production
 */

// -----------------------------------------------------------------------------
// 4. Currency and country (for display/logging)
// -----------------------------------------------------------------------------
define('TAP_CURRENCY', 'USD');
define('TAP_COUNTRY', 'SA'); // Saudi Arabia

// -----------------------------------------------------------------------------
// 5. Amount limits (prevents manipulation - server-side validation)
// -----------------------------------------------------------------------------
define('TAP_MIN_AMOUNT', 0.10);
define('TAP_MAX_AMOUNT', 100000.00);

// -----------------------------------------------------------------------------
// 6. Tax rate (single source - used in verify.php, email templates)
// -----------------------------------------------------------------------------
define('TAP_TAX_RATE', 0.15); // 15%
