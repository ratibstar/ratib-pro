<?php
/**
 * Public company profile (Ratib Company) — enterprise About RATIB page.
 * URLs: /profile (canonical) · /pages/company-profile.php (legacy → redirect)
 */
declare(strict_types=1);

$ratibReqUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
if (preg_match('#/pages/company-profile\.php#i', $ratibReqUri)) {
    $ratibQs = isset($_SERVER['QUERY_STRING']) ? trim((string) $_SERVER['QUERY_STRING']) : '';
    $ratibDest = '/profile' . ($ratibQs !== '' ? '?' . $ratibQs : '');
    if (!headers_sent()) {
        header('Location: ' . $ratibDest, true, 302);
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    exit;
}

require __DIR__ . '/about.php';
