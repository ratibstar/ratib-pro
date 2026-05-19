<?php
/**
 * Serve Public site content media (CMS uploads + bundled defaults).
 * URL: /pages/site-content-media.php?f=cms-profile-image-hero.png
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/site-content.php';

$name = (string) ($_GET['f'] ?? '');
ratib_site_content_media_serve($name);
