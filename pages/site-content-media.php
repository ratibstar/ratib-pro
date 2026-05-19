<?php
declare(strict_types=1);

/** @deprecated Use /public/cms-media.php — kept for old bookmarks. */
$query = (string) ($_SERVER['QUERY_STRING'] ?? '');
$target = '/public/cms-media.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target, true, 302);
exit;
