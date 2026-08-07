<?php

declare(strict_types=1);

namespace Rateb\App\Services;

use Rateb\App\Models\CrmOwnershipRule;
use Rateb\App\Models\CrmSalesTeam;
use Rateb\App\Models\CrmSalesTeamMember;
use Rateb\App\Models\CrmTerritory;

/** Phase 5 — Sales teams, members, territories, ownership rules. */
final class CrmSalesTeamService
{
    /** @return list<array<string, mixed>> */
    public function listTeams(): array
    {
        $rows = (new CrmSalesTeam())->query(
            "SELECT t.*,
                    (SELECT COUNT(*) FROM rateb_crm_sales_team_members m
                     WHERE m.team_id = t.id AND m.deleted_at IS NULL) AS member_count
             FROM rateb_crm_sales_teams t
             WHERE t.company_id = :cid AND t.deleted_at IS NULL
             ORDER BY t.name ASC",
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listTerritories(): array
    {
        $rows = (new CrmTerritory())->query(
            "SELECT * FROM rateb_crm_territories
             WHERE company_id = :cid AND deleted_at IS NULL
             ORDER BY name ASC",
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function listOwnershipRules(): array
    {
        $rows = (new CrmOwnershipRule())->query(
            "SELECT * FROM rateb_crm_ownership_rules
             WHERE company_id = :cid AND deleted_at IS NULL
             ORDER BY name ASC",
            ['cid' => CrmSupport::requireCompanyId()]
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return list<array<string, mixed>> */
    public function membersFor(int $teamId): array
    {
        $rows = (new CrmSalesTeamMember())->query(
            "SELECT * FROM rateb_crm_sales_team_members
             WHERE company_id = :cid AND team_id = :tid AND deleted_at IS NULL
             ORDER BY is_primary DESC, id ASC",
            ['cid' => CrmSupport::requireCompanyId(), 'tid' => $teamId]
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function createTeam(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = CrmSupport::nextCode('rateb_crm_sales_teams', 'ST', $companyId);
        }
        $id = (new CrmSalesTeam())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => CrmSupport::nullIfEmpty($input['name_ar'] ?? null),
            'manager_user_id' => CrmSupport::intOrNull($input['manager_user_id'] ?? null),
            'territory_id' => CrmSupport::intOrNull($input['territory_id'] ?? null),
            'status' => 'active',
        ], CrmSupport::actorFields(true)));

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.teams.create', 'crm_sales_team', (int) $id, [
                'code' => $code,
                'name' => $name,
            ]);
        }

        return ['id' => (int) $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function addMember(int $teamId, array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $team = (new CrmSalesTeam())->queryOne(
            'SELECT id FROM rateb_crm_sales_teams WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['id' => $teamId, 'cid' => $companyId]
        );
        if ($team === null) {
            throw new \RuntimeException('team_not_found');
        }
        $userId = (int) ($input['user_id'] ?? 0);
        if ($userId < 1) {
            throw new \InvalidArgumentException('user_id_required');
        }
        $id = (new CrmSalesTeamMember())->create([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'team_id' => $teamId,
            'user_id' => $userId,
            'role_code' => substr(trim((string) ($input['role_code'] ?? 'member')), 0, 40) ?: 'member',
            'is_primary' => !empty($input['is_primary']) ? 1 : 0,
            'created_by' => CrmSupport::userId(),
        ]);
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.teams.member_add', 'crm_sales_team', $teamId, [
                'user_id' => $userId,
                'member_id' => (int) $id,
            ]);
        }

        return ['id' => (int) $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function createTerritory(array $input): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('name_required');
        }
        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $code = CrmSupport::nextCode('rateb_crm_territories', 'TR', $companyId);
        }
        $id = (new CrmTerritory())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
            'code' => substr($code, 0, 40),
            'name' => substr($name, 0, 160),
            'name_ar' => CrmSupport::nullIfEmpty($input['name_ar'] ?? null),
            'region' => CrmSupport::nullIfEmpty($input['region'] ?? null),
            'owner_user_id' => CrmSupport::intOrNull($input['owner_user_id'] ?? null),
            'status' => 'active',
        ], CrmSupport::actorFields(true)));

        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.territory.create', 'crm_territory', (int) $id, [
                'code' => $code,
            ]);
        }

        return ['id' => (int) $id];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{id: int}
     */
    public function saveOwnershipRule(array $input, ?int $ruleId = null): array
    {
        $companyId = CrmSupport::requireCompanyId();
        $name = trim((string) ($input['name'] ?? ''));
        $key = trim((string) ($input['rule_key'] ?? ''));
        if ($name === '' || $key === '') {
            throw new \InvalidArgumentException('rule_fields_required');
        }
        $payload = array_merge([
            'rule_key' => substr($key, 0, 60),
            'name' => substr($name, 0, 160),
            'entity_type' => substr(trim((string) ($input['entity_type'] ?? 'customer')), 0, 40),
            'assign_mode' => substr(trim((string) ($input['assign_mode'] ?? 'owner')), 0, 40),
            'team_id' => CrmSupport::intOrNull($input['team_id'] ?? null),
            'territory_id' => CrmSupport::intOrNull($input['territory_id'] ?? null),
            'owner_user_id' => CrmSupport::intOrNull($input['owner_user_id'] ?? null),
            'is_enabled' => !isset($input['is_enabled']) || !empty($input['is_enabled']) ? 1 : 0,
            'config_json' => isset($input['config_json'])
                ? (is_string($input['config_json']) ? $input['config_json'] : json_encode($input['config_json'], JSON_UNESCAPED_UNICODE))
                : null,
        ], CrmSupport::actorFields($ruleId === null));

        if ($ruleId !== null && $ruleId > 0) {
            $existing = (new CrmOwnershipRule())->queryOne(
                'SELECT id FROM rateb_crm_ownership_rules WHERE id = :id AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
                ['id' => $ruleId, 'cid' => $companyId]
            );
            if ($existing === null) {
                throw new \RuntimeException('ownership_rule_not_found');
            }
            (new CrmOwnershipRule())->update($ruleId, $payload);
            if (class_exists(AuditService::class)) {
                (new AuditService())->log('crm.ownership_rule.update', 'crm_ownership_rule', $ruleId, $payload);
            }

            return ['id' => $ruleId];
        }

        $id = (new CrmOwnershipRule())->create(array_merge([
            'public_uuid' => CrmSupport::uuidV4(),
            'company_id' => $companyId,
        ], $payload));
        if (class_exists(AuditService::class)) {
            (new AuditService())->log('crm.ownership_rule.create', 'crm_ownership_rule', (int) $id, $payload);
        }

        return ['id' => (int) $id];
    }

    /**
     * Apply first matching enabled ownership rule to a customer (best-effort).
     *
     * @return array{applied: bool, rule_id?: int}
     */
    public function applyOwnershipRules(int $customerId, string $entityType = 'customer'): array
    {
        $rules = (new CrmOwnershipRule())->query(
            "SELECT * FROM rateb_crm_ownership_rules
             WHERE company_id = :cid AND deleted_at IS NULL AND is_enabled = 1
               AND entity_type = :etype
             ORDER BY id ASC LIMIT 20",
            ['cid' => CrmSupport::requireCompanyId(), 'etype' => $entityType]
        );
        foreach (is_array($rules) ? $rules : [] as $rule) {
            $patch = [];
            if (!empty($rule['owner_user_id'])) {
                $patch['owner_user_id'] = (int) $rule['owner_user_id'];
            }
            if (!empty($rule['team_id'])) {
                $patch['team_id'] = (int) $rule['team_id'];
            }
            if (!empty($rule['territory_id'])) {
                $patch['territory_id'] = (int) $rule['territory_id'];
            }
            if ($patch === []) {
                continue;
            }
            (new CrmLifecycleService())->assignOwnership($customerId, $patch);
            if (class_exists(AuditService::class)) {
                (new AuditService())->log('crm.ownership_rule.apply', 'customer', $customerId, [
                    'rule_id' => (int) $rule['id'],
                ]);
            }

            return ['applied' => true, 'rule_id' => (int) $rule['id']];
        }

        return ['applied' => false];
    }
}
