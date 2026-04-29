<?php
declare(strict_types=1);

/**
 * Optional environment-level N-Genius secrets override.
 * Keep values empty in repo/local unless explicitly configured.
 */
return [
    'NGENIUS_OUTLET_ID' => '',
    'NGENIUS_API_KEY' => '',
    'NGENIUS_API_SECRET' => '',
    'NGENIUS_REALM' => 'networkinternational',
    'NGENIUS_CHECKOUT_CURRENCY' => 'SAR',
    'NGENIUS_USD_TO_SAR' => '3.75',
];
