<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrEss360Service;
use Rateb\App\Services\HrEssPhaseCService;

/** Phase P — ESS 360 / letters / decisions adapters. */
final class HrEssPhasePController extends Controller
{
    public function me360(): void
    {
        $result = (new HrEss360Service())->simplified360(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId()
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function letters(): void
    {
        $result = (new HrEssPhaseCService())->listLetters(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId()
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function letterFile(array $params = []): void
    {
        $result = (new HrEssPhaseCService())->downloadLetter(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            (int) ($params['id'] ?? 0)
        );
        if (isset($result['file']) && is_array($result['file'])) {
            $this->sendFile($result['file']);
        }
        Response::json($result['body'] ?? ['success' => false], (int) ($result['status'] ?? 500));
    }

    public function decisions(): void
    {
        $result = (new HrEssPhaseCService())->listDecisions(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId()
        );
        Response::json($result['body'], (int) $result['status']);
    }

    /** @param array{path:string,mime:string,filename:string} $file */
    private function sendFile(array $file): void
    {
        $path = (string) ($file['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            Response::json(['success' => false, 'code' => 'not_found'], 404);
            return;
        }
        http_response_code(200);
        header('Content-Type: ' . (string) ($file['mime'] ?? 'application/pdf'));
        header('Content-Disposition: attachment; filename="' . $this->safeFilename((string) ($file['filename'] ?? 'letter.pdf')) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }

    private function safeFilename(string $name): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $name) ?? 'letter.pdf';

        return $clean !== '' ? $clean : 'letter.pdf';
    }
}
