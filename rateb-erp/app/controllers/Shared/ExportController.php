<?php
declare(strict_types=1);

namespace Rateb\App\Controllers\Shared;

use Rateb\App\Core\Controller;
use Rateb\App\Core\TenantContext;
use Rateb\App\Services\ExportService;

final class ExportController extends Controller
{
    /** @param array<int, array<string, mixed>> $rows */
    public static function send(string $module, array $columns, array $rows, string $title = '', string $resource = ''): void
    {
        $format = strtolower(trim((string) ($_GET['format'] ?? 'csv')));
        if (!in_array($format, ['csv', 'excel', 'xls', 'pdf', 'print'], true)) {
            $format = 'csv';
        }
        if (!rateb_can('reports.export') && !TenantContext::isSuperAdmin()) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        if ($resource !== '' && !rateb_can_export_entity($resource) && !TenantContext::isSuperAdmin()) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        (new ExportService())->download($format, $module . '_' . date('Y-m-d'), $columns, $rows, $title !== '' ? $title : $module);
    }
}
