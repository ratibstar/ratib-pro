<?php
declare(strict_types=1);

/**
 * Copy to config/infra.secrets.php (gitignored). Do not commit real values.
 *
 * Required for encrypted provider secrets (production-verify secret_encryption PASS):
 *   RATEB_INFRA_SECRET_KEY — generate: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
 *
 * Optional provider credentials (or save via Control Panel → Infrastructure → Control):
 *   RATEB_INFRA_CPANEL_BASE_URL, RATEB_INFRA_CPANEL_USERNAME, RATEB_INFRA_CPANEL_API_TOKEN
 *   RATEB_INFRA_NAMECHEAP_API_USER, RATEB_INFRA_NAMECHEAP_API_KEY,
 *   RATEB_INFRA_NAMECHEAP_USERNAME, RATEB_INFRA_NAMECHEAP_CLIENT_IP
 *   RATEB_INFRA_CLOUDFLARE_API_TOKEN
 *
 * @return array<string, string>
 */
return [
    'RATEB_INFRA_SECRET_KEY' => '',
    // 'RATEB_INFRA_CPANEL_BASE_URL' => 'https://hostname:2087',
    // 'RATEB_INFRA_CPANEL_USERNAME' => 'root',
    // 'RATEB_INFRA_CPANEL_API_TOKEN' => '',
];
