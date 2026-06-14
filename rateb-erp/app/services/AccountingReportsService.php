<?php
declare(strict_types=1);

namespace Rateb\App\Services;

final class AccountingReportsService
{
    /** @return array<int, array{id: string, label: string, items: array<int, array<string, mixed>>}> */
    public function catalogForUser(): array
    {
        $raw = $this->rawCatalog();
        $out = [];
        foreach ($raw as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                $entity = (string) ($item['entity'] ?? '');
                if ($entity !== '' && function_exists('rateb_can_view_entity') && !rateb_can_view_entity($entity)) {
                    continue;
                }
                $items[] = [
                    'entity' => $entity,
                    'route' => (string) ($item['route'] ?? ''),
                    'label' => __((string) ($item['label'] ?? '')),
                    'desc' => __((string) ($item['desc'] ?? '')),
                    'icon' => (string) ($item['icon'] ?? 'fa-file-lines'),
                    'export' => isset($item['export']) ? (string) $item['export'] : '',
                    'url' => rateb_app_url((string) ($item['route'] ?? '')),
                    'exportUrl' => isset($item['export']) && (string) $item['export'] !== ''
                        ? rateb_app_url((string) $item['export'])
                        : '',
                    'canExport' => function_exists('rateb_can_export_entity') && rateb_can_export_entity('accounting'),
                ];
            }
            if ($items === []) {
                continue;
            }
            $out[] = [
                'id' => (string) ($group['id'] ?? ''),
                'label' => __((string) ($group['label'] ?? '')),
                'items' => $items,
            ];
        }
        return $out;
    }

    public function reportCountForUser(): int
    {
        $n = 0;
        foreach ($this->catalogForUser() as $group) {
            $n += count($group['items']);
        }
        return $n;
    }

    /** @return array<int, array<string, mixed>> */
    private function rawCatalog(): array
    {
        $file = (defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__, 2)) . '/config/accounting-reports.php';
        if (!is_file($file)) {
            return [];
        }
        $data = require $file;
        return is_array($data) ? $data : [];
    }
}
