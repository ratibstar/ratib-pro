<?php
/**
 * Profile deploy (under /pages/ — works when root .php is blocked).
 * https://rateb.sa/pages/ratib-profile-deploy.php?deploy=1&key=ratib-deploy-sync-2026
 */
$_GET['deploy'] = '1';
require dirname(__DIR__) . '/ratib-profile-check.php';
