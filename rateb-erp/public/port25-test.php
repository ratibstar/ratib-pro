<?php
declare(strict_types=1);

/**
 * Port 25 connectivity diagnostic — runs from the production server.
 * Open via browser: https://rateb.sa/rateb-erp/public/port25-test.php
 */
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');

$targets = [
    ['host' => 'gmail-smtp-in.l.google.com', 'port' => 25, 'desc' => 'Gmail inbound SMTP'],
    ['host' => 'alt1.gmail-smtp-in.l.google.com', 'port' => 25, 'desc' => 'Gmail inbound SMTP alt'],
    ['host' => 'mail.rateb.sa', 'port' => 25, 'desc' => 'Local mail host SMTP'],
    ['host' => 'mail.rateb.sa', 'port' => 587, 'desc' => 'Local mail host submission'],
];

$serverIpv4 = '';
if (function_exists('gethostbyname')) {
    $serverIpv4 = @gethostbyname(php_uname('n')) ?: '';
}

echo "=== Rateb ERP Port 25 Diagnostic ===\n";
echo "Server time: " . date('c') . "\n";
echo "Server hostname: " . php_uname('n') . "\n";
echo "Server IPv4 (from hostname): " . ($serverIpv4 !== '' ? $serverIpv4 : 'unknown') . "\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'on' : 'off') . "\n";
echo "stream_socket_client available: " . (function_exists('stream_socket_client') ? 'yes' : 'no') . "\n";
echo "fsockopen available: " . (function_exists('fsockopen') ? 'yes' : 'no') . "\n";
echo "\n";

foreach ($targets as $t) {
    $host = $t['host'];
    $port = $t['port'];
    $desc = $t['desc'];
    $remote = 'tcp://' . $host . ':' . $port;

    $resolved = '';
    if (function_exists('gethostbyname')) {
        $resolved = @gethostbyname($host) ?: '';
    }

    echo "--- Testing {$host}:{$port} ({$desc}) ---\n";
    echo "Resolved IPv4: " . ($resolved !== '' ? $resolved : 'failed') . "\n";

    $start = microtime(true);
    $fp = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT);
    $elapsed = round((microtime(true) - $start) * 1000, 1);

    if (is_resource($fp)) {
        $banner = @fgets($fp, 515) ?: '';
        fclose($fp);
        echo "Result: OK (connected in {$elapsed} ms)\n";
        echo "Banner: " . trim($banner) . "\n";
    } else {
        echo "Result: FAILED (after {$elapsed} ms)\n";
        echo "Error code: {$errno}\n";
        echo "Error text: " . ($errstr !== '' ? $errstr : 'no error text') . "\n";
    }
    echo "\n";
}

// Optional: try to read what the server IP looks like from an external service.
echo "=== External IPv4 lookup (rateb.sa) ===\n";
$externalIp = @file_get_contents('https://api.ipify.org?format=text', false, stream_context_create(['http' => ['timeout' => 5]]));
if ($externalIp !== false) {
    echo $externalIp . "\n";
} else {
    echo "Could not determine external IP.\n";
}

echo "\nDone.\n";
