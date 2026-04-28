<?php
/**
 * EN: Handles configuration/runtime setup behavior in `config/ngenius.secrets.example.php`.
 * AR: يدير سلوك إعدادات النظام وتهيئة التشغيل في `config/ngenius.secrets.example.php`.
 */

declare(strict_types=1);

/**
 * Copy to ngenius.secrets.php in config/ or config/env/ and fill in values from the N-Genius portal.
 * Do not commit ngenius.secrets.php to git.
 *
 * Each line must end with a comma except the last entry before ]; a missing comma causes
 * "unexpected '=>', expecting ']'" when PHP loads this file.
 *
 * HTTP 400 badTokenRequest: use a Service Account key from the portal (not a user password).
 * If the portal shows Client ID + Secret separately, set both NGENIUS_API_KEY and NGENIUS_API_SECRET.
 * If NI gives one API key string for Basic (often base64), put it in NGENIUS_API_KEY only — leave NGENIUS_API_SECRET empty.
 * Do not fill API_SECRET if API_KEY is already the full blob; that produces badTokenRequest.
 * If you paste plaintext "id:secret" as one line, same — leave NGENIUS_API_SECRET empty.
 * KSA live merchants: keys must call https://api-gateway.ksa.ngenius-payments.com (see config/env/out_ratib_sa.php).
 * Live realm: networkinternational.
 *
 * Optional (same keys as .env / config/env.php): site list prices are USD; KSA outlets usually charge SAR.
 * NGENIUS_CHECKOUT_CURRENCY=SAR and NGENIUS_USD_TO_SAR=3.75 — omit to use defaults from config/env.php.
 *
 * @return array<string, string>
 */
return [
    'NGENIUS_OUTLET_ID' => '',
    'NGENIUS_API_KEY' => '',
    'NGENIUS_API_SECRET' => '',
    'NGENIUS_REALM' => 'networkinternational',
    'NGENIUS_CHECKOUT_CURRENCY' => 'SAR',
    'NGENIUS_USD_TO_SAR' => '3.75',
];
