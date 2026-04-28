<?php
/**
 * EN: Handles application behavior in `paypal-checkout/webhook.php`.
 * AR: يدير سلوك جزء من التطبيق في `paypal-checkout/webhook.php`.
 */
/**
 * PayPal Webhook Handler
 * 
 * Handles async payment notifications from PayPal.
 * Webhooks provide real-time updates about payment status changes.
 * 
 * Setup:
 * 1. Create webhook in PayPal Dashboard: https://developer.paypal.com/dashboard/webhooks
 * 2. Set webhook URL to: https://yourdomain.com/paypal-checkout/webhook.php
 * 3. Subscribe to events: PAYMENT.CAPTURE.COMPLETED, PAYMENT.CAPTURE.DENIED
 * 4. Copy Webhook ID to .env file
 */

require_once __DIR__ . '/config.php';

// Set content type
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get webhook payload
$payload = file_get_contents('php://input');
$headers = getallheaders();

// Verify webhook signature (security: ensures request is from PayPal)
function verifyWebhookSignature($headers, $payload) {
    $webhookId = getenv('PAYPAL_WEBHOOK_ID');
    if (empty($webhookId)) {
        error_log('PayPal Webhook: Webhook ID not configured');
        return false; // Skip verification if not configured
    }

    $authAlgo = $headers['Paypal-Auth-Algo'] ?? '';
    $certUrl = $headers['Paypal-Cert-Url'] ?? '';
    $transmissionId = $headers['Paypal-Transmission-Id'] ?? '';
    $transmissionSig = $headers['Paypal-Transmission-Sig'] ?? '';
    $transmissionTime = $headers['Paypal-Transmission-Time'] ?? '';

    if (empty($authAlgo) || empty($certUrl) || empty($transmissionId) || empty($transmissionSig) || empty($transmissionTime)) {
        error_log('PayPal Webhook: Missing verification headers');
        return false;
    }

    // Verify webhook signature with PayPal
    $verifyData = [
        'auth_algo' => $authAlgo,
        'cert_url' => $certUrl,
        'transmission_id' => $transmissionId,
        'transmission_sig' => $transmissionSig,
        'transmission_time' => $transmissionTime,
        'webhook_id' => $webhookId,
        'webhook_event' => json_decode($payload, true),
    ];

    $result = paypalApiRequest('/v1/notifications/verify-webhook-signature', 'POST', $verifyData);

    if (!$result || !$result['success']) {
        error_log('PayPal Webhook: Signature verification failed');
        return false;
    }

    $verificationStatus = $result['data']['verification_status'] ?? '';
    return $verificationStatus === 'SUCCESS';
}

// Verify webhook signature
if (!verifyWebhookSignature($headers, $payload)) {
    error_log('PayPal Webhook: Invalid signature - possible fraud attempt');
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// Parse webhook event
$event = json_decode($payload, true);

if (!$event) {
    error_log('PayPal Webhook: Invalid JSON payload');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// Log webhook event
error_log('PayPal Webhook Received: ' . json_encode($event));

$eventType = $event['event_type'] ?? '';
$resource = $event['resource'] ?? [];

// Handle different event types
switch ($eventType) {
    case 'PAYMENT.CAPTURE.COMPLETED':
        // Payment was successfully captured
        $transactionId = $resource['id'] ?? '';
        $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? '';
        $amount = $resource['amount']['value'] ?? 0;
        $currency = $resource['amount']['currency_code'] ?? '';
        $status = $resource['status'] ?? '';

        // Log transaction
        logTransaction($orderId, $transactionId, $amount, $currency);

        // Update your database or trigger actions here
        // Example: Update registration status, send confirmation email, etc.
        
        error_log(sprintf(
            'PayPal Webhook: Payment Completed - Order: %s, Transaction: %s, Amount: %s %s',
            $orderId,
            $transactionId,
            $amount,
            $currency
        ));

        // TODO: Add your business logic here
        // - Update registration request status
        // - Send confirmation email
        // - Activate user account
        // - etc.

        break;

    case 'PAYMENT.CAPTURE.DENIED':
    case 'PAYMENT.CAPTURE.REFUNDED':
        // Payment was denied or refunded
        $transactionId = $resource['id'] ?? '';
        
        error_log(sprintf(
            'PayPal Webhook: Payment %s - Transaction: %s',
            $eventType,
            $transactionId
        ));

        // TODO: Handle refund/denial
        // - Update order status
        // - Notify admin
        // - etc.

        break;

    default:
        error_log('PayPal Webhook: Unhandled event type: ' . $eventType);
}

// Always return 200 to acknowledge receipt
http_response_code(200);
echo json_encode(['status' => 'received']);
