<?php
/**
 * EN: Handles application behavior in `tap-payments/webhook.php`.
 * AR: يدير سلوك جزء من التطبيق في `tap-payments/webhook.php`.
 */
/**
 * Tap Payments - Webhook Handler Example
 *
 * Tap sends server-to-server notifications (charge.captured, charge.failed, etc.)
 * to a URL you configure in the Tap Dashboard.
 *
 * Setup:
 * 1. Go to Tap Dashboard > Developers > Webhooks
 * 2. Add your webhook URL: https://yourdomain.com/tap-payments/webhook.php
 * 3. Select events: charge.captured, charge.failed, etc.
 * 4. (Optional) Configure webhook signing key for verification
 */
require_once __DIR__ . '/config.php';

// -----------------------------------------------------------------------------
// 1. Only accept POST (Tap sends POST)
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// -----------------------------------------------------------------------------
// 2. Get raw body (Tap sends JSON)
// -----------------------------------------------------------------------------
$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);

if (!is_array($payload)) {
    http_response_code(400);
    exit('Invalid JSON');
}

// -----------------------------------------------------------------------------
// 3. Optional: Verify webhook signature (recommended in production)
// Get your webhook signing key from Tap Dashboard > Webhooks
// -----------------------------------------------------------------------------
// define('TAP_WEBHOOK_SECRET', 'whsec_xxx');
// $signature = $_SERVER['HTTP_X_TAP_SIGNATURE'] ?? '';
// if (!empty(TAP_WEBHOOK_SECRET) && !verifyTapSignature($raw, $signature, TAP_WEBHOOK_SECRET)) {
//     http_response_code(401);
//     exit('Invalid signature');
// }

// -----------------------------------------------------------------------------
// 4. Extract event type and data
// -----------------------------------------------------------------------------
$eventType = $payload['event'] ?? $payload['type'] ?? '';
$chargeId = $payload['id'] ?? $payload['charge_id'] ?? '';
$status = isset($payload['object']) ? ($payload['object']['status'] ?? '') : ($payload['status'] ?? '');
$metadata = isset($payload['object']['metadata']) ? $payload['object']['metadata'] : ($payload['metadata'] ?? []);
$registrationId = isset($metadata['udf1']) ? trim($metadata['udf1']) : '';

// -----------------------------------------------------------------------------
// 5. Log webhook for debugging (optional - disable in production or use proper logger)
// -----------------------------------------------------------------------------
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/tap_webhook.log';
$logEntry = date('Y-m-d H:i:s') . ' | event=' . $eventType . ' | charge_id=' . $chargeId . ' | status=' . $status . ' | udf1=' . $registrationId . PHP_EOL;
@file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// -----------------------------------------------------------------------------
// 6. Process events
// -----------------------------------------------------------------------------
switch ($eventType) {
    case 'charge.captured':
    case 'CHARGE_CAPTURED':
        // Payment successful - update your database, send confirmation, etc.
        if (!empty($registrationId)) {
            $configPath = __DIR__ . '/../includes/config.php';
            if (file_exists($configPath)) {
                require_once $configPath;
                $conn = $GLOBALS['conn'] ?? null;
                if ($conn) {
                    $col = $conn->query("SHOW COLUMNS FROM control_registration_requests LIKE 'payment_status'");
                    if ($col && $col->num_rows > 0) {
                        $rid = (int) $registrationId;
                        $stmt = $conn->prepare("UPDATE control_registration_requests SET payment_status = 'paid', payment_method = 'tap' WHERE id = ?");
                        if ($stmt) {
                            $stmt->bind_param('i', $rid);
                            $stmt->execute();
                        }
                    }
                }
            }
        }
        break;

    case 'charge.failed':
    case 'CHARGE_FAILED':
        // Payment failed - optionally update order status
        break;

    case 'charge.cancelled':
    case 'CHARGE_CANCELLED':
        // User cancelled
        break;

    default:
        // Unknown event - log only
        break;
}

// -----------------------------------------------------------------------------
// 7. Respond 200 quickly (Tap retries on non-2xx)
// -----------------------------------------------------------------------------
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['received' => true]);
