<?php
declare(strict_types=1);
echo function_exists('opcache_reset') && opcache_reset() ? "opcache_ok\n" : "no_opcache\n";
