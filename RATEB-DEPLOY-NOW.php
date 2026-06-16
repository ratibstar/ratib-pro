<?php
/**
 * Smallest deploy entry — upload to public_html root only.
 * https://rateb.sa/RATEB-DEPLOY-NOW.php?key=rateb-deploy-sync-2026
 */
$_GET['deploy'] = '1';
if (!isset($_GET['key'])) {
    $_GET['key'] = 'rateb-deploy-sync-2026';
}
require __DIR__ . '/rateb-profile-check.php';
