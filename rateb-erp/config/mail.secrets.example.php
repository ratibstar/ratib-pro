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
 * Alternative (Gmail on Hetzner — REQUIRED until port 25 is unblocked):
 *   1. Create free API key at sendgrid.com (Authenticated: info@rateb.sa)
 *   2. In server .env:
 *      RATEB_ERP_SMTP_HOST=smtp.sendgrid.net
 *      RATEB_ERP_SMTP_USER=apikey
 *      RATEB_ERP_SMTP_PASS=SG.your_api_key
 *   3. In Sahabah DNS SPF add: include:sendgrid.net
 *
 * Hetzner blocks outbound port 25 — mail.rateb.sa accepts mail but cannot deliver to Gmail.
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
