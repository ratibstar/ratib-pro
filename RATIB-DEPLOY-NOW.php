<?php
/**
 * Smallest deploy entry — upload to public_html root only.
 * https://out.ratib.sa/RATIB-DEPLOY-NOW.php?key=ratib-deploy-sync-2026
 */
$_GET['deploy'] = '1';
if (!isset($_GET['key'])) {
    $_GET['key'] = 'ratib-deploy-sync-2026';
}
require __DIR__ . '/ratib-profile-check.php';
