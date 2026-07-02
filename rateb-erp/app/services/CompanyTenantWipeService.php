<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDO;
use RuntimeException;

/** Delete one tenant company's business data from an ERP database. */
final class CompanyTenantWipeService
{
    public function wipeCompany(PDO $db, int $companyId, bool $preserveUsers = false): array
    {
        if ($companyId < 1) {
            throw new RuntimeException('Invalid company id');
        }

        $report = [
            'company_id' => $companyId,
            'tables' => [],
            'users_deleted' => 0,
        ];

        $db->exec('SET FOREIGN_KEY_CHECKS=0');

        $stmt = $db->query(
            "SELECT DISTINCT TABLE_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND COLUMN_NAME = 'company_id'
               AND TABLE_NAME LIKE 'rateb\\_%'"
        );
        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $name = (string) ($row['TABLE_NAME'] ?? '');
            if ($name !== '' && $name !== 'rateb_companies') {
                $tables[] = $name;
            }
        }

        foreach ($tables as $table) {
            $safe = str_replace('`', '', $table);
            try {
                $del = $db->prepare('DELETE FROM `' . $safe . '` WHERE company_id = :cid');
                $del->execute(['cid' => $companyId]);
                $report['tables'][$table] = $del->rowCount();
            } catch (\Throwable $e) {
                $report['tables'][$table] = 'error: ' . $e->getMessage();
            }
        }

        if (!$preserveUsers) {
            $userDel = $db->prepare(
                'DELETE FROM rateb_users WHERE company_id = :cid AND (is_super_admin = 0 OR is_super_admin IS NULL)'
            );
            $userDel->execute(['cid' => $companyId]);
            $report['users_deleted'] = $userDel->rowCount();
        }

        try {
            $db->prepare('DELETE FROM rateb_companies WHERE id = :cid')->execute(['cid' => $companyId]);
        } catch (\Throwable $e) {
            $report['company_delete_error'] = $e->getMessage();
        }

        $db->exec('SET FOREIGN_KEY_CHECKS=1');

        return $report;
    }
}
