<?php
declare(strict_types=1);
$paths = [
    '/rateb-erp/public/admin/',
    '/rateb-erp/public/admin/hr/attendance?company_id=22',
    '/rateb-erp/public/admin/ops/inventory?company_id=22',
    '/rateb-erp/public/admin/ops/accounting?company_id=22',
    '/rateb-erp/public/admin/crm?company_id=22',
];
$mintCmd = 'php /tmp/remote-auth-pa.php mintpos 2>/dev/null';
$m = json_decode((string) shell_exec($mintCmd), true);
if (!$m) {
    $m = json_decode((string) shell_exec('php /home/admin/domains/rateb.sa/public_html/rateb-erp/tools/boot-bench/remote-auth.php mint 2>/dev/null'), true);
}
if (!$m) {
    fwrite(STDERR, "mint fail\n");
    exit(1);
}
$cn = $m['session_name'] ?? $m['cookie_name'] ?? 'rateb_erp';
$cv = $m['session_id'] ?? $m['cookie_value'] ?? $m['value'];
$cookie = $cn . '=' . $cv;
$out = [];
foreach ($paths as $p) {
    for ($pass = 1; $pass <= 2; $pass++) {
        $ch = curl_init('https://rateb.sa' . $p);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIE => $cookie,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => ['Accept: text/html'],
        ]);
        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ttfb = curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME) * 1000;
        $total = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
        $size = (int) curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        curl_close($ch);
        $hdrEnd = is_string($raw) ? strpos($raw, "\r\n\r\n") : false;
        $body = $hdrEnd === false ? '' : substr((string) $raw, $hdrEnd + 4);
        $out[] = [
            'path' => $p,
            'pass' => $pass,
            'http' => $code,
            'ttfb_ms' => round($ttfb, 1),
            'total_ms' => round($total, 1),
            'bytes' => $size,
            'body_len' => strlen($body),
            'has_sidebar' => (bool) preg_match('/rateb-sidebar|__RATEB_ERP_SHELL/i', $body),
            'uncached' => (bool) preg_match('/data-rateb-uncached-page/i', $body),
        ];
    }
}
echo json_encode(['server_ttfb' => $out], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
