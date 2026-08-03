<?php
declare(strict_types=1);

/**
 * Copy to config/env/moyasar.secrets.php and fill in values from the Moyasar dashboard.
 * Do not commit the live secrets file to git.
 *
 * @return array<string, string>
 */
return [
    'MOYASAR_ENABLED' => '0',
    'MOYASAR_MODE' => 'sandbox',
    'MOYASAR_PUBLISHABLE_KEY' => '',
    'MOYASAR_SECRET_KEY' => '',
    'MOYASAR_WEBHOOK_SECRET' => '',
    'MOYASAR_ENCRYPTION_KEY' => '',
];
