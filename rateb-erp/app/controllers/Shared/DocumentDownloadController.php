<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Controller;
use Rateb\App\Services\DocumentService;

final class DocumentDownloadController extends Controller
{
    public function download(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        (new DocumentService())->sendDownload($id);
    }
}
