<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Controller;
use Rateb\App\Core\LocalQrRenderer;

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
        try {
            $bin = LocalQrRenderer::png($data, $size);
        } catch (\Throwable $e) {
            error_log('Local QR render failed: ' . $e->getMessage());
            $bin = '';
        }
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
}
