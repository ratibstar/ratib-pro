<?php
declare(strict_types=1);
$mint = json_decode((string) shell_exec('php /tmp/remote-auth.php mint'), true);
if (!is_array($mint) || empty($mint['session_id'])) {
    fwrite(STDERR, "mint failed\n");
    exit(1);
}
$cookie = ($mint['session_name'] ?? 'rateb_erp') . '=' . $mint['session_id'];
$urls = [
    'https://rateb.sa/rateb-erp/public/admin/',
    'https://rateb.sa/rateb-erp/public/admin/hr/attendance?company_id=22',
    'https://rateb.sa/rateb-erp/public/admin/ops/inventory?company_id=22',
    'https://rateb.sa/rateb-erp/public/admin/ops/accounting?company_id=22',
];
$out = [];
foreach ($urls as $u) {
    for ($pass = 1; $pass <= 2; $pass++) {
        $ch = curl_init($u);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_COOKIE => $cookie,
            CURLOPT_TIMEOUT => 90,
            CURLOPT_HTTPHEADER => ['Accept: text/html', 'Cache-Control: no-cache'],
        ]);
        $r = curl_exec($ch);
        $sep = is_string($r) ? strpos($r, "\r\n\r\n") : false;
        $h = ($sep !== false) ? substr($r, 0, $sep) : '';
        $out[] = [
            'url' => $u,
            'pass' => $pass,
            'http' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'ttfb_ms' => round(curl_getinfo($ch, CURLINFO_STARTTRANSFER_TIME) * 1000, 1),
            'total_ms' => round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000, 1),
            'bytes' => (int) curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD),
            'has_server_timing' => stripos($h, 'server-timing:') !== false,
            'has_x_rateb_st' => stripos($h, 'x-rateb-server-timing') !== false,
            'header_lines' => array_values(array_filter(array_map('trim', preg_split("/\r\n/", $h) ?: []), static function ($line) {
                return $line !== '' && (
                    stripos($line, 'server-timing') !== false
                    || stripos($line, 'x-rateb') !== false
                    || stripos($line, 'HTTP/') === 0
                    || stripos($line, 'content-type') === 0
                    || stripos($line, 'content-length') === 0
                );
            })),
        ];
        curl_close($ch);
    }
}
echo json_encode(['cookie_user' => $mint['email'] ?? null, 'probes' => $out], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
