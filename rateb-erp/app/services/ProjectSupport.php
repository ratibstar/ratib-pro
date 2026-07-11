<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Core\SessionManager;
use Rateb\App\Core\TenantContext;
use Rateb\App\Models\Project;
use Rateb\App\Models\ProjectTask;

/**
 * Shared helpers for Phase 18A Projects domain services.
 * Future Offline (18B) must call domain services — never duplicate these helpers offline.
 */
final class ProjectSupport
{
    public static function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function requireCompanyId(): int
    {
        $cid = (int) (TenantContext::companyId() ?? 0);
        if ($cid < 1) {
            throw new \RuntimeException('company_required');
        }

        return $cid;
    }

    public static function branchId(): ?int
    {
        $bid = (int) (SessionManager::get('rateb_branch_id') ?? SessionManager::get('branch_id') ?? 0);

        return $bid > 0 ? $bid : null;
    }

    public static function userId(): ?int
    {
        $uid = (int) (SessionManager::get('rateb_user_id') ?? 0);

        return $uid > 0 ? $uid : null;
    }

    /** @return array<string, mixed> */
    public static function actorFields(bool $creating = true): array
    {
        $uid = self::userId();
        $out = ['updated_by' => $uid];
        if ($creating) {
            $out['created_by'] = $uid;
        }

        return $out;
    }

    public static function nextProjectNo(int $companyId): string
    {
        $row = (new Project())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_projects WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return 'PRJ-' . date('Y') . '-' . str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    public static function nextTaskNo(int $projectId): string
    {
        $row = (new ProjectTask())->queryOne(
            'SELECT COUNT(*) AS c FROM rateb_project_tasks WHERE project_id = :pid',
            ['pid' => $projectId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return 'T-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public static function nextChildNo(string $table, string $prefix, int $projectId): string
    {
        $allowed = [
            'rateb_project_issues',
            'rateb_project_risks',
        ];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('invalid_code_table');
        }
        $row = (new Project())->queryOne(
            'SELECT COUNT(*) AS c FROM ' . $table . ' WHERE project_id = :pid',
            ['pid' => $projectId]
        );
        $n = (int) ($row['c'] ?? 0) + 1;

        return $prefix . '-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /** @return array<string, mixed>|null */
    public static function findProject(int $projectId, int $companyId): ?array
    {
        if ($projectId < 1 || $companyId < 1) {
            return null;
        }
        $row = (new Project())->queryOne(
            'SELECT * FROM rateb_projects WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $projectId, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertProject(int $projectId, int $companyId): array
    {
        $row = self::findProject($projectId, $companyId);
        if ($row === null) {
            throw new \RuntimeException('project_not_found');
        }

        return $row;
    }

    /** @return array<string, mixed>|null */
    public static function findTask(int $taskId, int $companyId): ?array
    {
        if ($taskId < 1 || $companyId < 1) {
            return null;
        }
        $row = (new ProjectTask())->queryOne(
            'SELECT * FROM rateb_project_tasks WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $taskId, 'cid' => $companyId]
        );

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed> */
    public static function assertTask(int $taskId, int $companyId): array
    {
        $row = self::findTask($taskId, $companyId);
        if ($row === null) {
            throw new \RuntimeException('task_not_found');
        }

        return $row;
    }

    public static function nullIfEmpty(mixed $v): mixed
    {
        if ($v === null) {
            return null;
        }
        if (is_string($v) && trim($v) === '') {
            return null;
        }

        return $v;
    }

    public static function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }

    public static function floatOrNull(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }

        return (float) $v;
    }
}
