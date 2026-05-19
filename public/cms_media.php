<?php
/**
 * Legacy alias (underscore) — redirects to cms-media.php (hyphen).
 */
declare(strict_types=1);

$query = (string) ($_SERVER['QUERY_STRING'] ?? '');
$target = '/public/cms-media.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target, true, 301);
exit;
