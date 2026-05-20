<?php
declare(strict_types=1);

/**
 * Copy to config/infra.secrets.php (gitignored). Do not commit real values.
 *
 * Required for encrypted provider secrets (production-verify secret_encryption PASS):
 *   RATIB_INFRA_SECRET_KEY — generate: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
 *
 * Optional provider credentials (or save via Control Panel → Infrastructure → Control):
 *   RATIB_INFRA_CPANEL_BASE_URL, RATIB_INFRA_CPANEL_USERNAME, RATIB_INFRA_CPANEL_API_TOKEN
 *   RATIB_INFRA_NAMECHEAP_API_USER, RATIB_INFRA_NAMECHEAP_API_KEY,
 *   RATIB_INFRA_NAMECHEAP_USERNAME, RATIB_INFRA_NAMECHEAP_CLIENT_IP
 *   RATIB_INFRA_CLOUDFLARE_API_TOKEN
 *
 * @return array<string, string>
 */
return [
    'RATIB_INFRA_SECRET_KEY' => '',
    // 'RATIB_INFRA_CPANEL_BASE_URL' => 'https://hostname:2087',
    // 'RATIB_INFRA_CPANEL_USERNAME' => 'root',
    // 'RATIB_INFRA_CPANEL_API_TOKEN' => '',
];
