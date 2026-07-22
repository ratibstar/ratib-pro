<?php
declare(strict_types=1);

return [
    'whatsapp' => [
        'api_url' => getenv('RCC_WHATSAPP_API_URL') ?: 'https://graph.facebook.com/v19.0',
        'phone_number_id' => getenv('RCC_WHATSAPP_PHONE_NUMBER_ID') ?: '',
        'access_token' => getenv('RCC_WHATSAPP_ACCESS_TOKEN') ?: '',
    ],
    'email' => [
        'smtp_host' => getenv('RCC_SMTP_HOST') ?: getenv('SMTP_HOST') ?: '',
        'smtp_port' => (int) (getenv('RCC_SMTP_PORT') ?: getenv('SMTP_PORT') ?: 587),
        'smtp_user' => getenv('RCC_SMTP_USER') ?: getenv('SMTP_USER') ?: '',
        'smtp_pass' => getenv('RCC_SMTP_PASS') ?: getenv('SMTP_PASS') ?: '',
        'from_email' => getenv('RCC_SMTP_FROM') ?: getenv('SMTP_FROM') ?: 'noreply@rateb.sa',
        'from_name' => getenv('RCC_SMTP_FROM_NAME') ?: 'RATEB Contact Center',
        'imap_host' => getenv('RCC_IMAP_HOST') ?: '',
        'imap_port' => (int) (getenv('RCC_IMAP_PORT') ?: 993),
        'imap_user' => getenv('RCC_IMAP_USER') ?: '',
        'imap_pass' => getenv('RCC_IMAP_PASS') ?: '',
        'imap_mailbox' => getenv('RCC_IMAP_MAILBOX') ?: 'INBOX',
    ],
    'web_chat' => [
        'widget_enabled' => (getenv('RCC_WEBCHAT_ENABLED') ?: '1') === '1',
        'allowed_origins' => array_filter(array_map('trim', explode(',', getenv('RCC_WEBCHAT_ORIGINS') ?: 'https://rateb.sa'))),
    ],
];
