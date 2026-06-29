<?php
declare(strict_types=1);

/**
 * Copy to mail.secrets.php (same folder) or project-root config/mail.secrets.php
 * Do not commit mail.secrets.php to git.
 *
 * Recommended production (DirectAdmin mailbox):
 *   RATEB_ERP_SMTP_HOST=mail.rateb.sa
 *   RATEB_ERP_SMTP_USER=info@rateb.sa
 *   RATEB_ERP_SMTP_PASS=mailbox_password_from_DirectAdmin
 *
 * Hetzner Cloud blocks outbound port 25 by default. Request unblock via Hetzner Support
 * (after 1 month + paid invoice) — see mail DNS panel in ERP settings.
 *
 * Avoid localhost for external customers — use mail.rateb.sa on port 587 with TLS.
 *
 * @return array<string, string>
 */
return [
    'RATEB_ERP_SMTP_HOST' => 'mail.rateb.sa',
    'RATEB_ERP_SMTP_PORT' => '587',
    'RATEB_ERP_SMTP_ENCRYPTION' => 'tls',
    'RATEB_ERP_SMTP_USER' => 'info@rateb.sa',
    'RATEB_ERP_SMTP_FROM_EMAIL' => 'info@rateb.sa',
    'RATEB_ERP_SMTP_FROM_NAME' => 'Rateb ERP',
    'RATEB_ERP_SMTP_PASS' => '',
];
