<?php
declare(strict_types=1);

/**
 * Must load before rateb-home-public-nav-bootstrap.php assigns nav asset $GLOBALS.
 * Prevents notices when an older bootstrap copy runs on the server.
 */
if (!isset($ratebHomePublicCssQuery)) {
    $ratebHomePublicCssQuery = '';
}
if (!isset($ratebMegaNavCssQuery)) {
    $ratebMegaNavCssQuery = '';
}
if (!isset($ratebPublicNavBrandCssQuery)) {
    $ratebPublicNavBrandCssQuery = '';
}
if (!isset($ratebEnterpriseCalmCssQuery)) {
    $ratebEnterpriseCalmCssQuery = '';
}
if (!isset($ratebMegaNavJsQuery)) {
    $ratebMegaNavJsQuery = '';
}
