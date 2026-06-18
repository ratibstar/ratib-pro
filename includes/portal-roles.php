<?php
/**
 * Portal roles — mobile app access types (company / worker / agency).
 * Separate from `roles` which controls RATEB Pro web permissions.
 */
declare(strict_types=1);

if (!function_exists('rateb_portal_roles_run_sql')) {
    /**
     * @param mysqli|PDO $conn
     */
    function rateb_portal_roles_run_sql($conn, string $sql): bool
    {
        try {
            if ($conn instanceof mysqli) {
                return (bool) @$conn->query($sql);
            }
            if ($conn instanceof PDO) {
                return $conn->exec($sql) !== false;
            }
        } catch (Throwable $e) {
            error_log('rateb_portal_roles_run_sql: ' . $e->getMessage());
        }
        return false;
    }
}

if (!function_exists('rateb_portal_roles_table_exists')) {
    /**
     * @param mysqli|PDO $conn
     */
    function rateb_portal_roles_table_exists($conn): bool
    {
        try {
            if ($conn instanceof mysqli) {
                $r = @$conn->query("SHOW TABLES LIKE 'portal_roles'");
                return $r && $r->num_rows > 0;
            }
            if ($conn instanceof PDO) {
                $r = $conn->query("SHOW TABLES LIKE 'portal_roles'");
                return $r && $r->rowCount() > 0;
            }
        } catch (Throwable $e) {
            return false;
        }
        return false;
    }
}

if (!function_exists('rateb_portal_roles_column_exists')) {
    /**
     * @param mysqli|PDO $conn
     */
    function rateb_portal_roles_column_exists($conn, string $table, string $column): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') {
            return false;
        }
        try {
            if ($conn instanceof mysqli) {
                $r = @$conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
                return $r && $r->num_rows > 0;
            }
            if ($conn instanceof PDO) {
                $r = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
                return $r && $r->rowCount() > 0;
            }
        } catch (Throwable $e) {
            return false;
        }
        return false;
    }
}

if (!function_exists('rateb_portal_type_from_role_name')) {
    function rateb_portal_type_from_role_name(?string $roleName): string
    {
        $rn = strtolower(trim((string) $roleName));
        if ($rn === '') {
            return 'company';
        }
        if (str_contains($rn, 'agency') || str_contains($rn, 'partner') || str_contains($rn, 'recruit')) {
            return 'agency';
        }
        if (str_contains($rn, 'worker') || str_contains($rn, 'employee') || str_contains($rn, 'labour') || str_contains($rn, 'labor')) {
            return 'worker';
        }
        return 'company';
    }
}

if (!function_exists('rateb_portal_roles_ensure_schema')) {
    /**
     * Create portal_roles table, users.portal_role_id, seed defaults, backfill from roles.role_name.
     *
     * @param mysqli|PDO $conn
     */
    function rateb_portal_roles_ensure_schema($conn): void
    {
        rateb_portal_roles_run_sql($conn, "
            CREATE TABLE IF NOT EXISTS `portal_roles` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) NOT NULL,
                `portal_type` ENUM('company','worker','agency') NOT NULL DEFAULT 'company',
                `description` TEXT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_portal_type` (`portal_type`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if (rateb_portal_roles_column_exists($conn, 'users', 'user_id')
            && !rateb_portal_roles_column_exists($conn, 'users', 'portal_role_id')) {
            rateb_portal_roles_run_sql($conn, "
                ALTER TABLE `users`
                ADD COLUMN `portal_role_id` INT NULL DEFAULT NULL AFTER `role_id`,
                ADD KEY `idx_portal_role_id` (`portal_role_id`)
            ");
        }

        rateb_portal_roles_seed_defaults($conn);
        rateb_portal_roles_backfill_users($conn);
    }
}

if (!function_exists('rateb_portal_roles_seed_defaults')) {
    /**
     * @param mysqli|PDO $conn
     */
    function rateb_portal_roles_seed_defaults($conn): void
    {
        if (!rateb_portal_roles_table_exists($conn)) {
            return;
        }

        $defaults = [
            ['Company Staff', 'company', 'RATEB Pro and company mobile portal'],
            ['Worker', 'worker', 'Mobile worker portal'],
            ['Agency', 'agency', 'Recruitment agency mobile portal'],
        ];

        foreach ($defaults as [$name, $type, $desc]) {
            $exists = false;
            try {
                if ($conn instanceof mysqli) {
                    $stmt = $conn->prepare('SELECT id FROM portal_roles WHERE portal_type = ? LIMIT 1');
                    if ($stmt) {
                        $stmt->bind_param('s', $type);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $exists = $res && $res->num_rows > 0;
                        $stmt->close();
                    }
                } elseif ($conn instanceof PDO) {
                    $stmt = $conn->prepare('SELECT id FROM portal_roles WHERE portal_type = ? LIMIT 1');
                    if ($stmt) {
                        $stmt->execute([$type]);
                        $exists = (bool) $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }
            } catch (Throwable $e) {
                continue;
            }
            if ($exists) {
                continue;
            }

            try {
                if ($conn instanceof mysqli) {
                    $stmt = $conn->prepare(
                        'INSERT INTO portal_roles (name, portal_type, description, status) VALUES (?, ?, ?, ?)'
                    );
                    if ($stmt) {
                        $status = 'active';
                        $stmt->bind_param('ssss', $name, $type, $desc, $status);
                        $stmt->execute();
                        $stmt->close();
                    }
                } elseif ($conn instanceof PDO) {
                    $stmt = $conn->prepare(
                        'INSERT INTO portal_roles (name, portal_type, description, status) VALUES (?, ?, ?, ?)'
                    );
                    if ($stmt) {
                        $stmt->execute([$name, $type, $desc, 'active']);
                    }
                }
            } catch (Throwable $e) {
                error_log('rateb_portal_roles_seed_defaults: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('rateb_portal_roles_backfill_users')) {
    /**
     * @param mysqli|PDO $conn
     */
    function rateb_portal_roles_backfill_users($conn): void
    {
        if (!rateb_portal_roles_table_exists($conn)
            || !rateb_portal_roles_column_exists($conn, 'users', 'portal_role_id')
            || !rateb_portal_roles_column_exists($conn, 'users', 'role_id')) {
            return;
        }

        try {
            if ($conn instanceof mysqli) {
                $sql = "
                    UPDATE users u
                    LEFT JOIN roles r ON u.role_id = r.role_id
                    LEFT JOIN portal_roles pr ON pr.portal_type = (
                        CASE
                            WHEN LOWER(COALESCE(r.role_name, '')) LIKE '%agency%'
                              OR LOWER(COALESCE(r.role_name, '')) LIKE '%partner%'
                              OR LOWER(COALESCE(r.role_name, '')) LIKE '%recruit%' THEN 'agency'
                            WHEN LOWER(COALESCE(r.role_name, '')) LIKE '%worker%'
                              OR LOWER(COALESCE(r.role_name, '')) LIKE '%employee%'
                              OR LOWER(COALESCE(r.role_name, '')) LIKE '%labour%'
                              OR LOWER(COALESCE(r.role_name, '')) LIKE '%labor%' THEN 'worker'
                            ELSE 'company'
                        END
                    ) AND pr.status = 'active'
                    SET u.portal_role_id = pr.id
                    WHERE u.portal_role_id IS NULL AND pr.id IS NOT NULL
                ";
                @$conn->query($sql);
                return;
            }
            if ($conn instanceof PDO) {
                $conn->exec("
                    UPDATE users u
                    LEFT JOIN roles r ON u.role_id = r.role_id
                    LEFT JOIN portal_roles pr ON pr.portal_type = (
                        CASE
                            WHEN LOWER(COALESCE(r.role_name, '')) LIKE '%agency%'
                              OR LOWER(COALESCE(r.role_name, '')) LIKE '%partner%'
                              OR LOWER(COALESCE(r.role_name, '')) LIKE '%recruit%' THEN 'agency'
                            WHEN LOWER(COALESCE(r.role_name, '')) LIKE '%worker%'
                              OR LOWER(COALESCE(r.role_name, '')) LIKE '%employee%'
                              OR LOWER(COALESCE(r.role_name, '')) LIKE '%labour%'
                              OR LOWER(COALESCE(r.role_name, '')) LIKE '%labor%' THEN 'worker'
                            ELSE 'company'
                        END
                    ) AND pr.status = 'active'
                    SET u.portal_role_id = pr.id
                    WHERE u.portal_role_id IS NULL AND pr.id IS NOT NULL
                ");
            }
        } catch (Throwable $e) {
            error_log('rateb_portal_roles_backfill_users: ' . $e->getMessage());
        }
    }
}

if (!function_exists('rateb_resolve_user_portal_type')) {
    /**
     * Resolve mobile portal type for a staff user.
     *
     * @param mysqli|PDO $conn
     * @return 'company'|'worker'|'agency'
     */
    function rateb_resolve_user_portal_type($conn, int $userId, ?string $fallbackRoleName = null): string
    {
        if ($userId <= 0) {
            return rateb_portal_type_from_role_name($fallbackRoleName);
        }

        rateb_portal_roles_ensure_schema($conn);

        try {
            if ($conn instanceof mysqli) {
                $stmt = $conn->prepare(
                    'SELECT pr.portal_type
                     FROM users u
                     LEFT JOIN portal_roles pr ON u.portal_role_id = pr.id
                     WHERE u.user_id = ? LIMIT 1'
                );
                if ($stmt) {
                    $stmt->bind_param('i', $userId);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    $stmt->close();
                    $type = strtolower(trim((string) ($row['portal_type'] ?? '')));
                    if (in_array($type, ['company', 'worker', 'agency'], true)) {
                        return $type;
                    }
                }
            } elseif ($conn instanceof PDO) {
                $stmt = $conn->prepare(
                    'SELECT pr.portal_type
                     FROM users u
                     LEFT JOIN portal_roles pr ON u.portal_role_id = pr.id
                     WHERE u.user_id = ? LIMIT 1'
                );
                if ($stmt) {
                    $stmt->execute([$userId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $type = strtolower(trim((string) ($row['portal_type'] ?? '')));
                    if (in_array($type, ['company', 'worker', 'agency'], true)) {
                        return $type;
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('rateb_resolve_user_portal_type: ' . $e->getMessage());
        }

        return rateb_portal_type_from_role_name($fallbackRoleName);
    }
}
