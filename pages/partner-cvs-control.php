<?php
/**
 * Legacy URL: CV sharing is now done from Workers (bulk) → Partner Agencies.
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/permissions.php';
rateb_staff_page_require_session();
header('Location: ' . rateb_nav_url('partner-agencies.php'));
exit;
