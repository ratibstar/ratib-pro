<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

use PDO;
use Rateb\App\Core\Database;

/** Global EPC reference writes. No company_id and no inventory writes. */
final class AutomotiveReferenceService
{
    private const ROLES = [
        'vehicle_manufacturer',
        'parts_manufacturer',
        'brand_owner',
        'distributor',
        'authorized_dealer',
        'branch',
    ];

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function db(): PDO
    {
        return $this->pdo ?? Database::connection();
    }

    /** @return list<array<string, mixed>> */
    public function markets(): array
    {
        return $this->db()->query(
            'SELECT id, code, name_en, name_ar, parent_market_id FROM rateb_auto_ref_markets ORDER BY code'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createMarket(string $code, string $nameEn, string $nameAr, ?int $parentId): int
    {
        $code = strtoupper(trim($code));
        $nameEn = trim($nameEn);
        $nameAr = trim($nameAr);
        if ($code === '' || $nameEn === '' || $nameAr === '') {
            throw new \InvalidArgumentException('Market code and both names are required.');
        }
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_ref_markets (code, name_en, name_ar, parent_market_id) VALUES (:code, :en, :ar, :parent)'
        );
        $stmt->execute(['code' => $code, 'en' => $nameEn, 'ar' => $nameAr, 'parent' => $parentId]);
        return (int) $this->db()->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function organizations(): array
    {
        $stmt = $this->db()->query(
            'SELECT o.id, o.org_role, o.name_en, o.name_ar, o.status, m.code AS market_code
             FROM rateb_auto_ref_orgs o
             INNER JOIN rateb_auto_ref_markets m ON m.id = o.home_market_id
             ORDER BY o.name_en'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createOrganization(
        string $role,
        int $marketId,
        string $nameEn,
        string $nameAr,
        ?int $parentId
    ): int {
        if (!in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException('Organization role is not allowed.');
        }
        $nameEn = trim($nameEn);
        $nameAr = trim($nameAr);
        if ($marketId < 1 || $nameEn === '' || $nameAr === '') {
            throw new \InvalidArgumentException('Organization market and both names are required.');
        }
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_ref_orgs (org_role, parent_org_id, home_market_id, name_en, name_ar, normalized_name)
             VALUES (:role, :parent, :market, :en, :ar, :norm)'
        );
        $stmt->execute([
            'role' => $role,
            'parent' => $parentId,
            'market' => $marketId,
            'en' => $nameEn,
            'ar' => $nameAr,
            'norm' => $this->normalize($nameEn),
        ]);
        return (int) $this->db()->lastInsertId();
    }

    public function createAuthorization(
        int $orgId,
        int $marketId,
        string $sourceNote,
        string $verifiedOn,
        string $effectiveOn,
        string $expiresOn
    ): int {
        $sourceNote = trim($sourceNote);
        if ($orgId < 1 || $marketId < 1 || $sourceNote === '') {
            throw new \InvalidArgumentException('Authorization organization, market, and source are required.');
        }
        if ($expiresOn <= $effectiveOn) {
            throw new \InvalidArgumentException('Authorization expiry must be after the effective date.');
        }
        $db = $this->db();
        $db->beginTransaction();
        try {
            $lock = $db->prepare('SELECT id FROM rateb_auto_ref_orgs WHERE id = :id FOR UPDATE');
            $lock->execute(['id' => $orgId]);
            if (!$lock->fetch()) {
                throw new \InvalidArgumentException('Organization was not found.');
            }
            $overlap = $db->prepare(
                'SELECT id FROM rateb_auto_ref_authorizations
                 WHERE org_id = :org AND territory_market_id = :market
                   AND effective_on < :exp AND expires_on > :eff LIMIT 1'
            );
            $overlap->execute(['org' => $orgId, 'market' => $marketId, 'exp' => $expiresOn, 'eff' => $effectiveOn]);
            if ($overlap->fetch()) {
                throw new \InvalidArgumentException('overlap_rejected');
            }
            $stmt = $db->prepare(
                'INSERT INTO rateb_auto_ref_authorizations
                 (org_id, territory_market_id, source_note, verified_on, effective_on, expires_on)
                 VALUES (:org, :market, :note, :verified, :eff, :exp)'
            );
            $stmt->execute([
                'org' => $orgId,
                'market' => $marketId,
                'note' => $sourceNote,
                'verified' => $verifiedOn,
                'eff' => $effectiveOn,
                'exp' => $expiresOn,
            ]);
            $id = (int) $db->lastInsertId();
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function vehicleBrands(): array
    {
        $stmt = $this->db()->query(
            'SELECT b.id, b.name_en, b.name_ar, o.name_en AS manufacturer_en
             FROM rateb_auto_ref_vehicle_brands b
             INNER JOIN rateb_auto_ref_orgs o ON o.id = b.vehicle_mfr_org_id
             ORDER BY b.name_en'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createVehicleBrand(int $manufacturerOrgId, string $nameEn, string $nameAr): int
    {
        $nameEn = trim($nameEn);
        $nameAr = trim($nameAr);
        if ($manufacturerOrgId < 1 || $nameEn === '' || $nameAr === '') {
            throw new \InvalidArgumentException('Vehicle brand requires a manufacturer and both names.');
        }
        $role = $this->db()->prepare('SELECT org_role FROM rateb_auto_ref_orgs WHERE id = :id');
        $role->execute(['id' => $manufacturerOrgId]);
        $found = $role->fetchColumn();
        if ($found !== 'vehicle_manufacturer') {
            throw new \InvalidArgumentException('Vehicle brands belong only to a vehicle manufacturer.');
        }
        $stmt = $this->db()->prepare(
            'INSERT INTO rateb_auto_ref_vehicle_brands (vehicle_mfr_org_id, name_en, name_ar, normalized_name)
             VALUES (:org, :en, :ar, :norm)'
        );
        $stmt->execute([
            'org' => $manufacturerOrgId,
            'en' => $nameEn,
            'ar' => $nameAr,
            'norm' => $this->normalize($nameEn),
        ]);
        return (int) $this->db()->lastInsertId();
    }

    private function normalize(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return $value;
    }
}
