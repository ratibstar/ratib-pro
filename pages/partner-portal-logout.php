<?php
require_once __DIR__ . '/../includes/config.php';

if (function_exists('ratib_partner_portal_clear')) {
    ratib_partner_portal_clear();
}

header('Location: ' . pageUrl('partner-portal-login.php'));
exit;
