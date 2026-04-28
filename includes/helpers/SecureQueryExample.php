<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/helpers/SecureQueryExample.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/helpers/SecureQueryExample.php`.
 */
/**
 * Secure Query Examples - Multi-Tenant with country_id filter
 *
 * COPY these patterns into your API/CRUD code. Do not include this file in production.
 *
 * Prerequisites:
 *   1. TenantLoader::init() called in config/bootstrap
 *   2. CountryFilter helper loaded
 *   3. country_id column exists on your tables
 */

// =============================================================================
// EXAMPLE 1: mysqli SELECT with CountryFilter (matches your current setup)
// =============================================================================

function exampleSelectUsers(mysqli $conn): array
{
    require_once __DIR__ . '/CountryFilter.php';

    $where = CountryFilter::where('users', 'status = ?');
    $sql = "SELECT user_id, username, email FROM users WHERE $where";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $status = 'active';
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

// =============================================================================
// EXAMPLE 2: mysqli INSERT with country_id
// =============================================================================

function exampleInsertUser(mysqli $conn, string $username, string $email, string $password): int
{
    require_once __DIR__ . '/CountryFilter.php';

    $params = CountryFilter::insertParams(
        ['username', 'email', 'password', 'role_id', 'status'],
        [$username, $email, password_hash($password, PASSWORD_DEFAULT), 1, 'active'],
        ['s', 's', 's', 'i', 's']
    );

    $sql = "INSERT INTO users (" . implode(',', $params['columns']) . ") VALUES (" . $params['placeholders'] . ")";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param($params['types'], ...$params['values']);
    $stmt->execute();
    $id = (int) $conn->insert_id;
    $stmt->close();
    return $id;
}

// =============================================================================
// EXAMPLE 3: PDO SELECT (for future PDO migration)
// =============================================================================

function exampleSelectUsersPdo(PDO $pdo): array
{
    require_once __DIR__ . '/CountryFilter.php';

    $where = CountryFilter::where('users', 'status = :status');
    $sql = "SELECT user_id, username, email FROM users WHERE $where";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['status' => 'active']);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// =============================================================================
// EXAMPLE 4: JOIN with country filter on main table
// =============================================================================

function exampleSelectUsersWithRoles(mysqli $conn): array
{
    require_once __DIR__ . '/CountryFilter.php';

    $where = CountryFilter::where('users', 'u.status = ?', 'u');
    $sql = "SELECT u.user_id, u.username, r.role_name 
            FROM users u 
            LEFT JOIN roles r ON r.role_id = u.role_id AND r.country_id = u.country_id 
            WHERE $where";
    $stmt = $conn->prepare($sql);
    $status = 'active';
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

// =============================================================================
// EXAMPLE 5: UPDATE with country filter (prevent cross-tenant updates)
// =============================================================================

function exampleUpdateUser(mysqli $conn, int $userId, string $newEmail): bool
{
    require_once __DIR__ . '/CountryFilter.php';

    $where = CountryFilter::where('users', 'user_id = ?');
    $sql = "UPDATE users SET email = ? WHERE $where";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $newEmail, $userId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok && $conn->affected_rows > 0;
}
