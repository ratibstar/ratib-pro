<?php
/**
 * EN: Provisions RATEB Pro (legacy workforce) database access + default admin for an agency.
 * AR: يجهّز قاعدة RATEB Pro والمستخدم الافتراضي للوكالة.
 */
declare(strict_types=1);

require_once __DIR__ . '/../../api/control/agency-db-helper.php';

final class ProProvisioningService
{
    public const DEFAULT_LOGIN = 'admin';
    public const DEFAULT_PASSWORD = '123456';

    /**
     * @param array<string, mixed> $agency
     * @return array<string, mixed>
     */
    public static function provision(mysqli $controlConn, array $agency): array
    {
        $agencyId = (int) ($agency['id'] ?? 0);
        if ($agencyId < 1) {
            throw new InvalidArgumentException('agency id is required');
        }

        $dbName = trim((string) ($agency['db_name'] ?? ''));
        if ($dbName === '') {
            throw new RuntimeException('Agency DB Name is empty — set db_name on the agency row first.');
        }

        $countryId = (int) ($agency['country_id'] ?? 0);
        $dbc = getAgencyDbConnection($agency, $countryId);
        if (!$dbc || !($dbc['conn'] instanceof mysqli)) {
            $err = function_exists('getAgencyDbConnectionLastError') ? getAgencyDbConnectionLastError() : 'Connection failed';
            throw new RuntimeException('Cannot connect to Pro database: ' . $err);
        }

        /** @var mysqli $conn */
        $conn = $dbc['conn'];
        rateb_ensure_minimal_rateb_pro_schema($conn);
        self::ensureAdminUser($conn, $agencyId, $countryId);

        $tenantId = self::ensureTenantLink($controlConn, $agency, $agencyId);

        return [
            'agency_id' => $agencyId,
            'db_name' => (string) ($dbc['db_name'] ?? $dbName),
            'tenant_id' => $tenantId,
            'admin_username' => self::DEFAULT_LOGIN,
            'admin_password' => self::DEFAULT_PASSWORD,
            'pro_status' => 'ready',
        ];
    }

    private static function ensureAdminUser(mysqli $conn, int $agencyId, int $countryId): void
    {
        $username = self::DEFAULT_LOGIN;
        $hash = password_hash(self::DEFAULT_PASSWORD, PASSWORD_BCRYPT, ['cost' => 10]);

        $res = @$conn->query("SHOW TABLES LIKE 'users'");
        if (!$res || $res->num_rows === 0) {
            throw new RuntimeException('Pro users table is missing after schema bootstrap.');
        }

        $cols = [];
        $cRes = $conn->query('SHOW COLUMNS FROM users');
        while ($cRes && ($c = $cRes->fetch_assoc())) {
            $cols[] = (string) ($c['Field'] ?? '');
        }
        if (!in_array('username', $cols, true) || !in_array('password', $cols, true)) {
            throw new RuntimeException('Pro users table is missing username/password columns.');
        }

        $pk = in_array('user_id', $cols, true) ? 'user_id' : (in_array('id', $cols, true) ? 'id' : 'user_id');
        $hasEmail = in_array('email', $cols, true);
        $hasRoleId = in_array('role_id', $cols, true);
        $hasStatus = in_array('status', $cols, true);
        $hasIsActive = in_array('is_active', $cols, true);
        $hasAgencyId = in_array('agency_id', $cols, true);
        $hasCountryId = in_array('country_id', $cols, true);

        $stmt = $conn->prepare("SELECT `{$pk}` FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('Failed to query Pro admin user: ' . $conn->error);
        }
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $r = $stmt->get_result();
        $row = $r ? $r->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            $uid = (int) ($row[$pk] ?? 0);
            $sets = ['password = ?'];
            $types = 's';
            $vals = [$hash];
            if ($hasStatus) {
                $sets[] = "status = 'active'";
            }
            if ($hasIsActive) {
                $sets[] = 'is_active = 1';
            }
            if ($hasAgencyId && $agencyId > 0) {
                $sets[] = 'agency_id = ?';
                $types .= 'i';
                $vals[] = $agencyId;
            }
            if ($hasCountryId && $countryId > 0) {
                $sets[] = 'country_id = ?';
                $types .= 'i';
                $vals[] = $countryId;
            }
            $types .= 'i';
            $vals[] = $uid;
            $sql = 'UPDATE users SET ' . implode(', ', $sets) . " WHERE `{$pk}` = ?";
            $up = $conn->prepare($sql);
            if (!$up) {
                throw new RuntimeException('Failed to update Pro admin user: ' . $conn->error);
            }
            $up->bind_param($types, ...$vals);
            if (!$up->execute()) {
                throw new RuntimeException('Failed to update Pro admin password: ' . $up->error);
            }
            $up->close();

            return;
        }

        $email = 'admin@rateb.sa';
        $roleId = 1;
        $status = 'active';
        if ($hasEmail && $hasRoleId && $hasStatus && $hasAgencyId && $hasCountryId) {
            $ins = $conn->prepare(
                'INSERT INTO users (username, password, email, role_id, status, agency_id, country_id) VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->bind_param('sssissi', $username, $hash, $email, $roleId, $status, $agencyId, $countryId);
        } elseif ($hasEmail && $hasRoleId && $hasStatus) {
            $ins = $conn->prepare('INSERT INTO users (username, password, email, role_id, status) VALUES (?, ?, ?, ?, ?)');
            $ins->bind_param('sssis', $username, $hash, $email, $roleId, $status);
        } elseif ($hasEmail && $hasRoleId) {
            $ins = $conn->prepare('INSERT INTO users (username, password, email, role_id) VALUES (?, ?, ?, ?)');
            $ins->bind_param('sssi', $username, $hash, $email, $roleId);
        } elseif ($hasRoleId) {
            $ins = $conn->prepare('INSERT INTO users (username, password, role_id) VALUES (?, ?, ?)');
            $ins->bind_param('ssi', $username, $hash, $roleId);
        } else {
            $ins = $conn->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
            $ins->bind_param('ss', $username, $hash);
        }
        if (!$ins) {
            throw new RuntimeException('Failed to create Pro admin user: ' . $conn->error);
        }
        if (!$ins->execute()) {
            throw new RuntimeException('Failed to insert Pro admin user: ' . $ins->error);
        }
        $ins->close();
    }

    /** @param array<string, mixed> $agency */
    private static function ensureTenantLink(mysqli $controlConn, array $agency, int $agencyId): int
    {
        $tenantId = (int) ($agency['tenant_id'] ?? 0);
        if ($tenantId > 0) {
            return $tenantId;
        }

        $name = trim((string) ($agency['name'] ?? ('Agency #' . $agencyId)));
        $slug = strtolower(trim((string) preg_replace('/[_\s]+/', '-', (string) ($agency['slug'] ?? $name))));
        $slug = trim((string) preg_replace('/[^a-z0-9-]+/', '-', $slug), '-');
        if ($slug === '') {
            $slug = 'agency-' . $agencyId;
        }
        $domain = $slug . '.agency.local';
        $dbName = trim((string) ($agency['db_name'] ?? ''));
        $dbHost = trim((string) ($agency['db_host'] ?? ''));
        $dbUser = trim((string) ($agency['db_user'] ?? ''));
        $dbPass = (string) ($agency['db_pass'] ?? '');

        $provisioningPath = dirname(__DIR__, 3) . '/admin/core/ProvisioningService.php';
        if (is_file($provisioningPath)) {
            require_once $provisioningPath;
            $eventBusPath = dirname(__DIR__, 3) . '/admin/core/EventBus.php';
            if (is_file($eventBusPath)) {
                require_once $eventBusPath;
            }
            if (function_exists('getControlDB')) {
                try {
                    $created = ProvisioningService::createTenant(
                        getControlDB(),
                        $name,
                        $domain,
                        [
                            'database_name' => $dbName,
                            'db_host' => $dbHost,
                            'db_user' => $dbUser,
                            'db_password' => $dbPass,
                            'status' => 'active',
                        ]
                    );
                    $tenantId = (int) ($created['tenant_id'] ?? 0);
                    if ($tenantId > 0) {
                        $stmt = $controlConn->prepare('UPDATE control_agencies SET tenant_id = ? WHERE id = ?');
                        if ($stmt) {
                            $stmt->bind_param('ii', $tenantId, $agencyId);
                            $stmt->execute();
                            $stmt->close();
                        }

                        return $tenantId;
                    }
                } catch (Throwable $e) {
                    error_log('ProProvisioningService tenant create: ' . $e->getMessage());
                }
            }
        }

        $attemptedDomain = $domain;
        for ($i = 0; $i < 2; $i++) {
            if ($i === 1) {
                $attemptedDomain = 'agency-' . $agencyId . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '.agency.local';
            }
            $stmt = $controlConn->prepare(
                'INSERT INTO tenants (name, domain, database_name, db_host, db_user, db_password, status, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            if (!$stmt) {
                continue;
            }
            $hostVal = $dbHost !== '' ? $dbHost : null;
            $status = 'active';
            $stmt->bind_param('sssssss', $name, $attemptedDomain, $dbName, $hostVal, $dbUser, $dbPass, $status);
            if ($stmt->execute()) {
                $tenantId = (int) $controlConn->insert_id;
                $stmt->close();
                if ($tenantId > 0) {
                    $upd = $controlConn->prepare('UPDATE control_agencies SET tenant_id = ? WHERE id = ?');
                    if ($upd) {
                        $upd->bind_param('ii', $tenantId, $agencyId);
                        $upd->execute();
                        $upd->close();
                    }

                    return $tenantId;
                }
            }
            $stmt->close();
        }

        return 0;
    }
}
