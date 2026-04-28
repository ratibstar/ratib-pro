<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/core/EventExporter.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/core/EventExporter.php`.
 */
declare(strict_types=1);

require_once __DIR__ . '/EventRepository.php';

final class EventExporter
{
    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<int, array<string, mixed>>
     */
    public static function asJsonStream(array $events): array
    {
        return $events;
    }

    /**
     * @param array<int, array<string, mixed>> $events
     * @return array<string, mixed>
     */
    public static function asOtlpLike(array $events): array
    {
        $rows = [];
        foreach ($events as $e) {
            $attrs = [
                ['key' => 'event_type', 'value' => ['stringValue' => (string) ($e['event_type'] ?? '')]],
                ['key' => 'level', 'value' => ['stringValue' => (string) ($e['level'] ?? '')]],
                ['key' => 'request_id', 'value' => ['stringValue' => (string) ($e['request_id'] ?? '')]],
                ['key' => 'tenant_id', 'value' => ['intValue' => (int) ($e['tenant_id'] ?? 0)]],
                ['key' => 'source', 'value' => ['stringValue' => (string) ($e['source'] ?? '')]],
            ];
            $rows[] = [
                'timeUnixNano' => (string) ((int) strtotime((string) ($e['created_at'] ?? 'now')) * 1000000000),
                'severityText' => strtoupper((string) ($e['level'] ?? 'INFO')),
                'body' => ['stringValue' => (string) ($e['message'] ?? '')],
                'attributes' => $attrs,
            ];
        }
        return [
            'resourceLogs' => [
                [
                    'resource' => [
                        'attributes' => [
                            ['key' => 'service.name', 'value' => ['stringValue' => 'ratib-control-center']],
                        ],
                    ],
                    'scopeLogs' => [
                        [
                            'scope' => ['name' => 'eventbus.exporter'],
                            'logRecords' => $rows,
                        ],
                    ],
                ],
            ],
        ];
    }
}

