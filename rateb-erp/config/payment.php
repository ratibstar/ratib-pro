<?php
declare(strict_types=1);

/**
 * Non-secret payment gateway defaults for RATIB ERP.
 */
return [
    'default_gateway' => 'moyasar',
    'callback_url' => 'site/customer/finance/payment/callback',
    'webhook_url' => 'api/v1/payments/webhooks/moyasar',
    'http_timeout_seconds' => 30,
    'replay_window_seconds' => 300,
];
