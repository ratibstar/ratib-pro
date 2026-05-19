<?php
/**
 * Public site CMS images — uploads + bundled defaults.
 * Served from /public/ (same deploy path as ratib-build.txt).
 *
 * Usage: /public/cms-media.php?f=cms-profile-gov-image-control.png
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/site-content.php';

$name = (string) ($_GET['f'] ?? '');
ratib_site_content_media_serve($name);
