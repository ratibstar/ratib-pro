<?php
/**
 * EN: Handles system administration/observability module behavior in `admin/core/ControlCenterQueryValidator.php`.
 * AR: يدير سلوك وحدة إدارة النظام والمراقبة في `admin/core/ControlCenterQueryValidator.php`.
 */
declare(strict_types=1);

/**
 * Console query safety checks (admin control plane).
 */
final class ControlCenterQueryValidator
{
    public static function isSafe(string $query): bool
    {
        $q = strtolower(trim($query));
        if ($q === '') {
            return false;
        }
        $blocked = [
            'drop database',
            'drop table',
            'truncate ',
            'grant all',
            'revoke ',
            'create user',
            'alter user',
            'shutdown',
            'flush privileges',
            'into outfile',
            'load_file(',
        ];
        foreach ($blocked as $token) {
            if (strpos($q, $token) !== false) {
                return false;
            }
        }
        if (preg_match('/\bdelete\s+from\b/i', $query) === 1) {
            if (!preg_match('/\bwhere\b/i', $query)) {
                return false;
            }
        }
        if (preg_match('/\bupdate\b/i', $query) === 1) {
            if (!preg_match('/\bwhere\b/i', $query)) {
                return false;
            }
        }
        if (preg_match('/^\s*alter\s+/i', $query) === 1) {
            return false;
        }
        return true;
    }

    /**
     * SAFE mode: read-only statements only.
     */
    public static function isReadOnlyStatement(string $query): bool
    {
        $first = strtolower((string) strtok(ltrim($query), " \t\r\n("));
        return in_array($first, ['select', 'show', 'describe', 'explain'], true);
    }
}
