<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Payment\PaymentWebhookService;

final class PaymentWebhookController extends Controller
{
    public function moyasar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            return;
        }

        $rawBody = (string) file_get_contents('php://input');
        $headers = $this->normalizeHeaders();
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;

        $result = (new PaymentWebhookService())->handleMoyasar($rawBody, $headers, $ip);
        http_response_code((int) ($result['http'] ?? 200));
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => (bool) ($result['ok'] ?? false),
            'duplicate' => (bool) ($result['duplicate'] ?? false),
        ], JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, string> */
    private function normalizeHeaders(): array
    {
        $out = [];
        foreach ($_SERVER as $k => $v) {
            if (!is_string($v) || !str_starts_with($k, 'HTTP_')) {
                continue;
            }
            $name = str_replace('_', '-', substr($k, 5));
            $out[$name] = $v;
            $out[strtolower($name)] = $v;
        }

        return $out;
    }
}
