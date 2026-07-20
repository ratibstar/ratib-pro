<?php
declare(strict_types=1);

/**
 * Mobile push config placeholders — copy values to env / secrets, never commit secrets.
 *
 * Env (preferred):
 *   RATEB_MOBILE_PUSH_OUTBOX_ENABLED=0|1
 *   RATEB_MOBILE_PUSH_CLIENT_APPS=ess,manager
 *   RATEB_MOBILE_PUSH_FCM_PROJECT_ID=
 *   RATEB_MOBILE_PUSH_FCM_CREDENTIALS_PATH=
 *   RATEB_MOBILE_PUSH_APNS_KEY_ID=
 *   RATEB_MOBILE_PUSH_APNS_TEAM_ID=
 *   RATEB_MOBILE_PUSH_APNS_BUNDLE_ID=
 *   RATEB_MOBILE_PUSH_APNS_KEY_PATH=
 *
 * Optional local file: config/mobile-push.secrets.php (gitignored pattern *.secrets.php)
 *
 * @return array<string, string>
 */
return [
    'RATEB_MOBILE_PUSH_OUTBOX_ENABLED' => '0',
    'RATEB_MOBILE_PUSH_CLIENT_APPS' => 'ess,manager',
    'RATEB_MOBILE_PUSH_FCM_PROJECT_ID' => '',
    'RATEB_MOBILE_PUSH_FCM_CREDENTIALS_PATH' => '',
    'RATEB_MOBILE_PUSH_APNS_KEY_ID' => '',
    'RATEB_MOBILE_PUSH_APNS_TEAM_ID' => '',
    'RATEB_MOBILE_PUSH_APNS_BUNDLE_ID' => '',
    'RATEB_MOBILE_PUSH_APNS_KEY_PATH' => '',
];
