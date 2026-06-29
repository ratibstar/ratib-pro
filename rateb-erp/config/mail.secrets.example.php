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
 * Alternative (Gmail delivery on Hetzner — SendGrid relay):
 *   RATEB_ERP_SMTP_HOST=smtp.sendgrid.net
 *   RATEB_ERP_SMTP_USER=apikey
 *   RATEB_ERP_SMTP_PASS=SG.your_api_key
 *
 * Avoid localhost for external customers unless SPF/DKIM are perfect — Gmail often blocks.
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
