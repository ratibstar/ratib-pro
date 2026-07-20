<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Api;

use Rateb\App\Core\Controller;
use Rateb\App\Core\Response;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\HrEssPayslipDocumentService;

/**
 * Thin ESS payslip adapter — identity via HrEssEmployeeResolverService only.
 */
final class HrEssPayslipController extends Controller
{
    public function index(): void
    {
        $result = (new HrEssPayslipDocumentService())->listPayslips(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId()
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function show(array $params = []): void
    {
        $result = (new HrEssPayslipDocumentService())->getPayslip(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            (string) ($params['id'] ?? '')
        );
        Response::json($result['body'], (int) $result['status']);
    }

    public function file(array $params = []): void
    {
        $result = (new HrEssPayslipDocumentService())->downloadPayslip(
            (int) TenantContext::apiUserId(),
            (int) TenantContext::companyId(),
            (string) ($params['id'] ?? '')
        );
        if (isset($result['stream']) && is_array($result['stream'])) {
            $this->sendStream($result['stream']);
        }
        Response::json($result['body'] ?? ['success' => false], (int) ($result['status'] ?? 500));
    }

    /** @param array{filename:string,mime:string,content:string} $stream */
    private function sendStream(array $stream): void
    {
        http_response_code(200);
        header('Content-Type: ' . (string) ($stream['mime'] ?? 'text/plain; charset=UTF-8'));
        header('Content-Disposition: attachment; filename="' . $this->safeFilename((string) ($stream['filename'] ?? 'payslip.txt')) . '"');
        header('Cache-Control: no-store');
        echo (string) ($stream['content'] ?? '');
        exit;
    }

    private function safeFilename(string $name): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $name) ?? 'payslip.txt';

        return $clean !== '' ? $clean : 'payslip.txt';
    }
}
