<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\Inventory;

/**
 * Shared live-tenant SQL helper for AI domain tool executors.
 * Uses an existing Model query() channel — never invents rows on failure.
 */
final class ErpAiDb
{
    /** @var Inventory|null */
    private static $model = null;

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    public static function query(string $sql, array $params = []): array
    {
        try {
            if (self::$model === null) {
                self::$model = new Inventory();
            }
            $rows = self::$model->query($sql, $params);
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function tableExists(string $table): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        if ($table === '') {
            return false;
        }
        $rows = self::query(
            'SELECT 1 AS ok FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :t LIMIT 1',
            ['t' => $table]
        );
        return $rows !== [];
    }

    /**
     * @return array{success: true, data: mixed, error: null}
     */
    public static function ok(mixed $data): array
    {
        return ['success' => true, 'data' => $data, 'error' => null];
    }

    /**
     * @return array{success: false, data: null, error: string, error_code: string, error_message: string}
     */
    public static function fail(string $code): array
    {
        $key = 'ai_tool_err_' . $code;
        $translated = function_exists('__') ? __($key) : $key;
        $message = (is_string($translated) && $translated !== '' && $translated !== $key) ? $translated : $code;
        return [
            'success' => false,
            'data' => null,
            'error' => $code,
            'error_code' => $code,
            'error_message' => $message,
        ];
    }

    public static function clampLimit(mixed $limit, int $default = 50, int $max = 100): int
    {
        return max(1, min($max, (int) ($limit ?? $default)));
    }
}
