<?php
declare(strict_types=1);

namespace Rateb\App\Pos\Services;

use Rateb\App\Core\Database;

/** Resolves the authoritative tenant/branch tax rate for online and offline POS pricing. */
final class PosTaxSettingsService
{
    public function resolveRate(int $companyId, int $branchId = 0): float
    {
        if ($companyId < 1 || !Database::tableExists('rateb_pos_settings')) {
            return 0.15;
        }
        $db = Database::connection();
        $rows = [];
        $company = $db->prepare(
            'SELECT settings_json FROM rateb_pos_settings
             WHERE company_id = :cid AND (branch_id IS NULL OR branch_id = 0)
             LIMIT 1'
        );
        $company->execute(['cid' => $companyId]);
        $companyJson = $company->fetchColumn();
        if (is_string($companyJson) && $companyJson !== '') {
            $rows[] = $this->decode($companyJson);
        }
        if ($branchId > 0) {
            $branch = $db->prepare(
                'SELECT settings_json FROM rateb_pos_settings
                 WHERE company_id = :cid AND branch_id = :bid LIMIT 1'
            );
            $branch->execute(['cid' => $companyId, 'bid' => $branchId]);
            $branchJson = $branch->fetchColumn();
            if (is_string($branchJson) && $branchJson !== '') {
                $rows[] = $this->decode($branchJson);
            }
        }

        $rate = 0.15;
        foreach ($rows as $settings) {
            foreach ([
                $settings['tax_rate'] ?? null,
                is_array($settings['tax'] ?? null) ? ($settings['tax']['rate'] ?? null) : null,
                is_array($settings['v2']['tax'] ?? null) ? ($settings['v2']['tax']['rate'] ?? null) : null,
            ] as $candidate) {
                if (is_numeric($candidate)) {
                    $rate = (float) $candidate;
                }
            }
        }

        return max(0.0, min(1.0, $rate));
    }

    /** @return array<string, mixed> */
    private function decode(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
