<?php
/**
 * Public site CMS images — uploads + bundled defaults.
 * Served from /public/ (same deploy path as ratib-build.txt).
 *
 * Usage: /public/cms-media.php?f=cms-profile-gov-image-control.png
 *
 * Prefer direct /uploads/ratib_cms_media/ URLs when possible (see ratib_site_content_media_public_url).
 */
declare(strict_types=1);

while (ob_get_level() > 0) {
    ob_end_clean();
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/site-content.php';

$name = (string) ($_GET['f'] ?? '');
ratib_site_content_media_serve($name);
