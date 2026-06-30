<?php
/**
 * Purge control_registration_requests within the current admin country scope.
 */
declare(strict_types=1);

if (!function_exists('registration_requests_purge_all')) {
    /**
     * @return array{success:bool,message:string,deleted:int}
     */
    function registration_requests_purge_all(mysqli $ctrl): array
    {
        $chk = $ctrl->query("SHOW TABLES LIKE 'control_registration_requests'");
        if (!$chk || $chk->num_rows === 0) {
            return ['success' => false, 'message' => 'Table not found', 'deleted' => 0];
        }

        $scopeCountryIds = function_exists('getRegistrationRequestScopeCountryIds')
            ? getRegistrationRequestScopeCountryIds($ctrl)
            : null;
        $canViewAll = ($scopeCountryIds === null);
        $hasCountryId = false;
        $colChk = $ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'country_id'");
        if ($colChk && $colChk->num_rows > 0) {
            $hasCountryId = true;
        }

        $where = [];
        if ($scopeCountryIds === []) {
            if (!$canViewAll) {
                return ['success' => false, 'message' => 'Access denied', 'deleted' => 0];
            }
        } elseif (!$canViewAll && $scopeCountryIds !== null && !empty($scopeCountryIds) && $hasCountryId) {
            $idsStr = implode(',', array_map('intval', $scopeCountryIds));
            $namesRes = $ctrl->query("SELECT name FROM control_countries WHERE id IN ($idsStr) AND is_active = 1");
            $countryNames = [];
            if ($namesRes) {
                while ($r = $namesRes->fetch_assoc()) {
                    $countryNames[] = "'" . $ctrl->real_escape_string((string) ($r['name'] ?? '')) . "'";
                }
            }
            $nameMatch = !empty($countryNames)
                ? ' OR (COALESCE(country_id, 0) = 0 AND country_name IN (' . implode(',', $countryNames) . '))'
                : '';
            $where[] = "(country_id IN ($idsStr)$nameMatch)";
        }

        $whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        if (!$ctrl->query('DELETE FROM control_registration_requests' . $whereClause)) {
            return ['success' => false, 'message' => 'Delete failed', 'deleted' => 0];
        }

        return [
            'success' => true,
            'message' => 'All registration requests deleted',
            'deleted' => (int) ($ctrl->affected_rows ?? 0),
        ];
    }
}

if (!function_exists('registration_requests_delete_ids')) {
    /**
     * @param list<int> $ids
     * @return array{success:bool,message:string,deleted:int}
     */
    function registration_requests_delete_ids(mysqli $ctrl, array $ids, ?array $scopeCountryIds = null, ?bool $hasCountryId = null): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static function (int $x): bool {
            return $x > 0;
        }));
        if ($ids === []) {
            return ['success' => false, 'message' => 'Invalid IDs', 'deleted' => 0];
        }

        if ($scopeCountryIds === null && function_exists('getRegistrationRequestScopeCountryIds')) {
            $scopeCountryIds = getRegistrationRequestScopeCountryIds($ctrl);
        }
        if ($hasCountryId === null) {
            $hasCountryId = false;
            $colChk = $ctrl->query("SHOW COLUMNS FROM control_registration_requests LIKE 'country_id'");
            if ($colChk && $colChk->num_rows > 0) {
                $hasCountryId = true;
            }
        }

        $canViewAll = ($scopeCountryIds === null);
        if ($scopeCountryIds === []) {
            if (!$canViewAll) {
                return ['success' => false, 'message' => 'Access denied', 'deleted' => 0];
            }
        } elseif (!$canViewAll && $scopeCountryIds !== null && !empty($scopeCountryIds) && $hasCountryId) {
            $idList = implode(',', $ids);
            $res = $ctrl->query("SELECT id, country_id FROM control_registration_requests WHERE id IN ($idList)");
            while ($res && ($row = $res->fetch_assoc())) {
                if ($row['country_id'] !== null && !in_array((int) $row['country_id'], $scopeCountryIds, true)) {
                    return ['success' => false, 'message' => 'You do not have permission to delete one or more of these requests', 'deleted' => 0];
                }
            }
            if ($res) {
                $res->close();
            }
        }

        $idList = implode(',', $ids);
        if (!$ctrl->query("DELETE FROM control_registration_requests WHERE id IN ($idList)")) {
            return ['success' => false, 'message' => 'Delete failed', 'deleted' => 0];
        }

        return [
            'success' => true,
            'message' => 'Deleted',
            'deleted' => (int) ($ctrl->affected_rows ?? 0),
        ];
    }
}
