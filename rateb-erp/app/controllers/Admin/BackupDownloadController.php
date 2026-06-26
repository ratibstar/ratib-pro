<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Admin;

use Rateb\App\Core\Controller;
use Rateb\App\Services\BackupDownloadService;

final class BackupDownloadController extends Controller
{
    public function download(): void
    {
        $service = new BackupDownloadService();
        $format = (string) ($_GET['format'] ?? 'b64');
        $file = trim((string) ($_GET['file'] ?? ''));
        $fresh = isset($_GET['fresh']) && (string) $_GET['fresh'] !== '0';

        $service->sendBackup($format, $fresh, $file);
    }
}
