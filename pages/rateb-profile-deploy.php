<?php
/**
 * Profile deploy (under /pages/ — works when root .php is blocked).
 * https://rateb.sa/pages/rateb-profile-deploy.php?deploy=1&key=rateb-deploy-sync-2026
 */
$_GET['deploy'] = '1';
require dirname(__DIR__) . '/rateb-profile-check.php';
