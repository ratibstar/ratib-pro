<?php
/**
 * Tenant rollout feature-flag resolver.
 * Resolution order: tenant override -> country override -> global default.
 */

if (!function_exists('trf_resolve_effective_flag')) {
    /**
     * @return array{flag_key:string,enabled:bool,value:int,source:string,tenant_id:int,country_id:int}
     */
    function trf_resolve_effective_flag(mysqli $ctrl, string $flagKey, int $tenantId = 0, int $countryId = 0): array
    {
        $flagKey = strtolower(trim($flagKey));
        if ($flagKey === '') {
            return [
                'flag_key' => '',
                'enabled' => false,
                'value' => 0,
                'source' => 'invalid',
                'tenant_id' => $tenantId,
                'country_id' => $countryId,
            ];
        }

        $flagId = 0;
        $defaultValue = 0;
        $stFlag = $ctrl->prepare(
            "SELECT id, default_value
             FROM control_rollout_feature_flags
             WHERE flag_key = ? AND is_active = 1
             LIMIT 1"
        );
        if ($stFlag) {
            $stFlag->bind_param('s', $flagKey);
            $stFlag->execute();
            $row = $stFlag->get_result()->fetch_assoc();
            $stFlag->close();
            if ($row) {
                $flagId = (int) ($row['id'] ?? 0);
                $defaultValue = ((int) ($row['default_value'] ?? 0) > 0) ? 1 : 0;
            }
        }

        if ($flagId <= 0) {
            return [
                'flag_key' => $flagKey,
                'enabled' => false,
                'value' => 0,
                'source' => 'flag_not_found',
                'tenant_id' => $tenantId,
                'country_id' => $countryId,
            ];
        }

        if ($tenantId > 0) {
            $stTenant = $ctrl->prepare(
                "SELECT override_value
                 FROM control_rollout_flag_overrides
                 WHERE flag_id = ?
                   AND scope_type = 'tenant'
                   AND tenant_id = ?
                   AND is_active = 1
                 ORDER BY id DESC
                 LIMIT 1"
            );
            if ($stTenant) {
                $stTenant->bind_param('ii', $flagId, $tenantId);
                $stTenant->execute();
                $tenantRow = $stTenant->get_result()->fetch_assoc();
                $stTenant->close();
                if ($tenantRow) {
                    $v = ((int) ($tenantRow['override_value'] ?? 0) > 0) ? 1 : 0;
                    return [
                        'flag_key' => $flagKey,
                        'enabled' => $v === 1,
                        'value' => $v,
                        'source' => 'tenant_override',
                        'tenant_id' => $tenantId,
                        'country_id' => $countryId,
                    ];
                }
            }
        }

        if ($countryId > 0) {
            $stCountry = $ctrl->prepare(
                "SELECT override_value
                 FROM control_rollout_flag_overrides
                 WHERE flag_id = ?
                   AND scope_type = 'country'
                   AND country_id = ?
                   AND is_active = 1
                 ORDER BY id DESC
                 LIMIT 1"
            );
            if ($stCountry) {
                $stCountry->bind_param('ii', $flagId, $countryId);
                $stCountry->execute();
                $countryRow = $stCountry->get_result()->fetch_assoc();
                $stCountry->close();
                if ($countryRow) {
                    $v = ((int) ($countryRow['override_value'] ?? 0) > 0) ? 1 : 0;
                    return [
                        'flag_key' => $flagKey,
                        'enabled' => $v === 1,
                        'value' => $v,
                        'source' => 'country_override',
                        'tenant_id' => $tenantId,
                        'country_id' => $countryId,
                    ];
                }
            }
        }

        return [
            'flag_key' => $flagKey,
            'enabled' => $defaultValue === 1,
            'value' => $defaultValue,
            'source' => 'global_default',
            'tenant_id' => $tenantId,
            'country_id' => $countryId,
        ];
    }
}
