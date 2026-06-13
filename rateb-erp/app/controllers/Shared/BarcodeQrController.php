<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Controller;
use Rateb\App\Services\DocumentBarcodeService;

final class BarcodeQrController extends Controller
{
    public function image(): void
    {
        $data = trim((string) ($_GET['data'] ?? ''));
        if ($data === '' || strlen($data) > 500) {
            http_response_code(400);
            echo 'Invalid data';
            return;
        }
        $size = max(160, min(500, (int) ($_GET['size'] ?? 280)));
        $url = (new DocumentBarcodeService())->qrImageUrl($data, $size);
        $bin = $this->fetchRemote($url);
        if ($bin === '') {
            http_response_code(502);
            echo 'QR unavailable';
            return;
        }
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . (string) strlen($bin));
        echo $bin;
        exit;
    }

    private function fetchRemote(string $url): string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return '';
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_USERAGENT => 'RATEB-ERP/1.0',
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body !== false && $code >= 200 && $code < 300) {
                return (string) $body;
            }
        }
        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => ['timeout' => 12, 'user_agent' => 'RATEB-ERP/1.0']]);
            $body = @file_get_contents($url, false, $ctx);
            if ($body !== false && $body !== '') {
                return (string) $body;
            }
        }
        return '';
    }
}
