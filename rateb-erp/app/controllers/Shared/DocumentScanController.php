<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Auth;
use Rateb\App\Core\Controller;
use Rateb\App\Services\DocumentBarcodeService;

final class DocumentScanController extends Controller
{
    public function show(array $params): void
    {
        $code = strtoupper(trim((string) ($params['code'] ?? '')));
        if ($code === '') {
            http_response_code(404);
            $this->view('errors/404', ['title' => '404'], 'auth');
            return;
        }

        $svc = new DocumentBarcodeService();
        $doc = $svc->resolvePublic($code);
        if (!$doc) {
            http_response_code(404);
            $this->view('shared/document-scan-not-found', [
                'title' => __('scan_not_found'),
                'code' => $code,
            ], 'auth');
            return;
        }

        $editUrl = $svc->documentEditUrl((string) $doc['type'], (int) $doc['recordId']);
        $loggedIn = Auth::check();

        $this->view('shared/document-scan', [
            'title' => (string) ($doc['title'] ?? __('document_barcode')),
            'doc' => $doc,
            'editUrl' => $editUrl,
            'loggedIn' => $loggedIn,
            'loginUrl' => rateb_url('login?next=' . rawurlencode($editUrl)),
        ], 'auth');
    }
}
