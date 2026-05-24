<?php
declare(strict_types=1);

/**
 * Must load before ratib-home-public-nav-bootstrap.php assigns nav asset $GLOBALS.
 * Prevents notices when an older bootstrap copy runs on the server.
 */
if (!isset($ratibHomePublicCssQuery)) {
    $ratibHomePublicCssQuery = '';
}
if (!isset($ratibMegaNavCssQuery)) {
    $ratibMegaNavCssQuery = '';
}
if (!isset($ratibPublicNavBrandCssQuery)) {
    $ratibPublicNavBrandCssQuery = '';
}
if (!isset($ratibEnterpriseCalmCssQuery)) {
    $ratibEnterpriseCalmCssQuery = '';
}
if (!isset($ratibMegaNavJsQuery)) {
    $ratibMegaNavJsQuery = '';
}
