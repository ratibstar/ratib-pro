<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrEssPayslipDocumentService;

/**
 * Thin ESS documents adapter — metadata + employee-scoped file stream.
 */
final class HrEssDocumentController extends Controller
{
    public function index(): void
    {
        $category = $this->input('category', null);
        $categoryStr = is_string($category) ? $category : null;
        $result = (new HrEssPayslipDocumentService())->listDocuments(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            $categoryStr
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function show(array $params = []): void
    {
        $result = (new HrEssPayslipDocumentService())->getDocument(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            (string) ($params['id'] ?? '')
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function file(array $params = []): void
    {
        $result = (new HrEssPayslipDocumentService())->downloadDocument(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            (string) ($params['id'] ?? '')
        );
        if (isset($result['file']) && is_array($result['file'])) {
            $this->sendFile($result['file']);
        }
        Response::json($result['body'] ?? ['success' => false], (int) ($result['status'] ?? 500));
    }

    /** @param array{path:string,mime:string,filename:string} $file */
    private function sendFile(array $file): void
    {
        $path = (string) ($file['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            Response::json(['success' => false, 'code' => 'not_found', 'message' => 'Document file missing'], 404);
        }
        $filename = $this->safeFilename((string) ($file['filename'] ?? 'document'));
        $mime = (string) ($file['mime'] ?? 'application/octet-stream');
        http_response_code(200);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: no-store');
        readfile($path);
        exit;
    }

    private function safeFilename(string $name): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $name) ?? 'document';

        return $clean !== '' ? $clean : 'document';
    }
}
