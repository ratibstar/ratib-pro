<?php
require_once __DIR__ . '/_auth.inc.php';

$query = $_GET;
$query['catalog'] = '1';
unset($query['embed'], $query['compatibility']);
$target = ratib_nav_url('client/domains.php', http_build_query($query));
header('Location: ' . $target, true, 302);
exit;
