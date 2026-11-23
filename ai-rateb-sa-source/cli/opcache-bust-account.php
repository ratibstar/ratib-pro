<?php
header('Content-Type: text/plain');
$root = dirname(__DIR__);
foreach (['/account.php','/app/account/save.php','/app/nav.php','/app/public-gate.php','/public/assets/js/app.js'] as $f) {
  $p = $root . $f;
  if (is_file($p) && function_exists('opcache_invalidate')) { opcache_invalidate($p, true); echo 'ok='.basename($p).PHP_EOL; }
}
