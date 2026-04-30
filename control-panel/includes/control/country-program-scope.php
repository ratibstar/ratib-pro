<?php
/**
 * Per-country program isolation for control-panel operators.
 * Full access: permission control_select_country (see getAllowedCountrySlugs() === null).
 * Scoped access: permissions country_{slug} — operator only sees/manages those countries.
 */
if (!defined('CONTROL_COUNTRY_PROGRAM_SCOPE_LOADED')) {
    define('CONTROL_COUNTRY_PROGRAM_SCOPE_LOADED', true);
}

/**
 * True when the user may switch context across all countries (no slug restriction).
 */
function control_country_program_can_operate_all_countries(): bool
{
    if (empty($_SESSION['control_logged_in'])) {
        return false;
    }
    return hasControlPermission(CONTROL_PERM_SELECT_COUNTRY);
}

/**
 * Country IDs the current operator may access; null = unrestricted (global operator).
 *
 * @return list<int>|null
 */
function control_country_program_allowed_country_ids(?mysqli $ctrl): ?array
{
    if (control_country_program_can_operate_all_countries()) {
        return null;
    }
    if (!$ctrl instanceof mysqli) {
        return [];
    }
    $slugs = getAllowedCountrySlugs();
    if (!is_array($slugs) || $slugs === []) {
        return [];
    }

    return getAllowedCountryIds($ctrl);
}

/**
 * Country IDs for worker tracking / government data: no cross-country mixing when a workspace is set.
 *
 * - Full-access (`control_select_country`): null = all countries only when no `control_country_id` in session;
 *   when session has a valid active country, returns that single id.
 * - Slug-scoped: same as `control_country_program_allowed_country_ids` with optional session pin to one country.
 *
 * @return list<int>|null
 */
function control_country_program_effective_scope_country_ids(?mysqli $ctrl): ?array
{
    if (empty($_SESSION['control_logged_in'])) {
        return [];
    }
    $sess = isset($_SESSION['control_country_id']) ? (int) $_SESSION['control_country_id'] : 0;

    if (control_country_program_can_operate_all_countries()) {
        if ($sess <= 0 || !($ctrl instanceof mysqli)) {
            return null;
        }
        $st = $ctrl->prepare('SELECT id FROM control_countries WHERE id = ? AND is_active = 1 LIMIT 1');
        if ($st) {
            $st->bind_param('i', $sess);
            $st->execute();
            $res = $st->get_result();
            $ok = $res && $res->num_rows > 0;
            $st->close();
            if ($ok) {
                return [$sess];
            }
        }

        return null;
    }

    $base = control_country_program_allowed_country_ids($ctrl);
    if (!is_array($base)) {
        return [];
    }
    if ($base === []) {
        return [];
    }
    if ($sess > 0 && in_array($sess, $base, true)) {
        return [$sess];
    }

    return $base;
}

/**
 * Allowed profile slugs for the current operator; null = all registry + templates.
 *
 * @return list<string>|null
 */
function control_country_program_allowed_slugs(?mysqli $ctrl): ?array
{
    if (control_country_program_can_operate_all_countries()) {
        return null;
    }
    $slugs = getAllowedCountrySlugs();
    return is_array($slugs) ? $slugs : [];
}

/**
 * Resolve active country row for session (for hub banner).
 *
 * @return array{id: int, name: string, slug: string}|null
 */
function control_country_program_session_country_row(?mysqli $ctrl): ?array
{
    if (!$ctrl instanceof mysqli) {
        return null;
    }
    $cid = isset($_SESSION['control_country_id']) ? (int) $_SESSION['control_country_id'] : 0;
    if ($cid <= 0) {
        return null;
    }
    $st = $ctrl->prepare('SELECT id, name, slug FROM control_countries WHERE id = ? LIMIT 1');
    if (!$st) {
        return null;
    }
    $st->bind_param('i', $cid);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
    if (!is_array($row)) {
        return null;
    }
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        return null;
    }

    return [
        'id' => $id,
        'name' => (string) ($row['name'] ?? ''),
        'slug' => strtolower(trim((string) ($row['slug'] ?? ''))),
    ];
}

/**
 * Country Profiles API/page: full system-settings operators, or country-scoped government operators (their slugs only).
 */
function control_country_profiles_can_edit(?mysqli $ctrl): bool
{
    if (empty($_SESSION['control_logged_in'])) {
        return false;
    }
    if (hasControlPermission(CONTROL_PERM_SYSTEM_SETTINGS)
        || hasControlPermission('view_control_system_settings')
        || hasControlPermission('edit_control_system_settings')
        || hasControlPermission('manage_control_roles')) {
        return true;
    }
    if (!$ctrl instanceof mysqli) {
        return false;
    }
    $slugs = control_country_program_allowed_slugs($ctrl);
    if ($slugs === null || $slugs === []) {
        return false;
    }

    return hasControlPermission(CONTROL_PERM_GOVERNMENT)
        || hasControlPermission('view_control_government')
        || hasControlPermission('manage_control_government')
        || hasControlPermission('gov_admin');
}
