<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/site-content.php';

$name = (string) ($_GET['f'] ?? '');
rateb_site_content_media_serve($name);
