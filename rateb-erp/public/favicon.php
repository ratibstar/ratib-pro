<?php
declare(strict_types=1);

header('Content-Type: image/svg+xml');
header('Cache-Control: public, max-age=86400');
echo '<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32">
  <rect width="32" height="32" rx="6" fill="#6b21a8"/>
  <text x="16" y="22" font-size="18" font-family="sans-serif" fill="white" text-anchor="middle">R</text>
</svg>';
