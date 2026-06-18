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
                `role_id` INT NULL DEFAULT NULL COMMENT 'FK roles.role_id — RATEB Pro permission profile',
                `permissions` JSON NULL DEFAULT NULL COMMENT 'Manual permission override for this portal role',
                `description` TEXT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'active',
                `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_portal_type` (`portal_type`),
                KEY `idx_role_id` (`role_id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        if (rateb_portal_roles_table_exists($conn)
            && !rateb_portal_roles_column_exists($conn, 'portal_roles', 'role_id')) {
            rateb_portal_roles_run_sql($conn, "
                ALTER TABLE `portal_roles`
                ADD COLUMN `role_id` INT NULL DEFAULT NULL COMMENT 'FK roles.role_id' AFTER `portal_type`,
                ADD KEY `idx_role_id` (`role_id`)
            ");
        }

        if (rateb_portal_roles_table_exists($conn)
            && !rateb_portal_roles_column_exists($conn, 'portal_roles', 'permissions')) {
            rateb_portal_roles_run_sql($conn, "
                ALTER TABLE `portal_roles`
                ADD COLUMN `permissions` JSON NULL DEFAULT NULL COMMENT 'Manual permission override' AFTER `role_id`
            ");
        }

        if (rateb_portal_roles_column_exists($conn, 'users', 'user_id')
            && !rateb_portal_roles_column_exists($conn, 'users', 'portal_role_id')) {
            rateb_portal_roles_run_sql($conn, "
                ALTER TABLE `users`
                ADD COLUMN `portal_role_id` INT NULL DEFAULT NULL AFTER `role_id`,
                ADD KEY `idx_portal_role_id` (`portal_role_id`)
            ");
        }

        rateb_portal_roles_seed_defaults($conn);
        rateb_portal_roles_link_permission_roles($conn);
        rateb_portal_roles_backfill_users($conn);
        rateb_portal_roles_sync_user_permissions($conn);
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

if (!function_exists('rateb_portal_roles_find_role_id_by_hint')) {
    /**
     * @param mysqli|PDO $conn
     * @param list<string> $nameHints
     */
    function rateb_portal_roles_find_role_id_by_hint($conn, array $nameHints): ?int
    {
        foreach ($nameHints as $hint) {
            $hint = trim((string) $hint);
            if ($hint === '') {
                continue;
            }
            try {
                if ($conn instanceof mysqli) {
                    $stmt = $conn->prepare(
                        'SELECT role_id FROM roles WHERE LOWER(role_name) = LOWER(?) ORDER BY role_id LIMIT 1'
                    );
                    if ($stmt) {
                        $stmt->bind_param('s', $hint);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $row = $res ? $res->fetch_assoc() : null;
                        $stmt->close();
                        if (!empty($row['role_id'])) {
                            return (int) $row['role_id'];
                        }
                    }
                    $like = '%' . $hint . '%';
                    $stmt = $conn->prepare(
                        'SELECT role_id FROM roles WHERE LOWER(role_name) LIKE LOWER(?) ORDER BY role_id LIMIT 1'
                    );
                    if ($stmt) {
                        $stmt->bind_param('s', $like);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $row = $res ? $res->fetch_assoc() : null;
                        $stmt->close();
                        if (!empty($row['role_id'])) {
                            return (int) $row['role_id'];
                        }
                    }
                } elseif ($conn instanceof PDO) {
                    $stmt = $conn->prepare(
                        'SELECT role_id FROM roles WHERE LOWER(role_name) = LOWER(?) ORDER BY role_id LIMIT 1'
                    );
                    if ($stmt) {
                        $stmt->execute([$hint]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!empty($row['role_id'])) {
                            return (int) $row['role_id'];
                        }
                    }
                    $stmt = $conn->prepare(
                        'SELECT role_id FROM roles WHERE LOWER(role_name) LIKE LOWER(?) ORDER BY role_id LIMIT 1'
                    );
                    if ($stmt) {
                        $stmt->execute(['%' . $hint . '%']);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if (!empty($row['role_id'])) {
                            return (int) $row['role_id'];
                        }
                    }
                }
            } catch (Throwable $e) {
                continue;
            }
        }
        return null;
    }
}

if (!function_exists('rateb_portal_roles_link_permission_roles')) {
    /**
     * @param mysqli|PDO $conn
     */
    function rateb_portal_roles_link_permission_roles($conn): void
    {
        if (!rateb_portal_roles_table_exists($conn)
            || !rateb_portal_roles_column_exists($conn, 'portal_roles', 'role_id')) {
            return;
        }

        $map = [
            'company' => ['Admin', 'Company', 'Staff', 'Administrator'],
            'worker' => ['Worker', 'Employee', 'Labour', 'Labor'],
            'agency' => ['Agency', 'Partner', 'Recruitment'],
        ];

        foreach ($map as $portalType => $hints) {
            $roleId = rateb_portal_roles_find_role_id_by_hint($conn, $hints);
            if ($roleId === null) {
                continue;
            }
            try {
                if ($conn instanceof mysqli) {
                    $stmt = $conn->prepare(
                        'UPDATE portal_roles SET role_id = ? WHERE portal_type = ? AND (role_id IS NULL OR role_id = 0)'
                    );
                    if ($stmt) {
                        $stmt->bind_param('is', $roleId, $portalType);
                        $stmt->execute();
                        $stmt->close();
                    }
                } elseif ($conn instanceof PDO) {
                    $stmt = $conn->prepare(
                        'UPDATE portal_roles SET role_id = ? WHERE portal_type = ? AND (role_id IS NULL OR role_id = 0)'
                    );
                    if ($stmt) {
                        $stmt->execute([$roleId, $portalType]);
                    }
                }
            } catch (Throwable $e) {
                error_log('rateb_portal_roles_link_permission_roles: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('rateb_portal_role_permission_payload')) {
    /**
     * @param mysqli|PDO $conn
     * @return array{role_id:int,permissions:?string}|null
     */
    function rateb_portal_role_permission_payload($conn, int $portalRoleId): ?array
    {
        if ($portalRoleId <= 0) {
            return null;
        }

        rateb_portal_roles_ensure_schema($conn);

        try {
            if ($conn instanceof mysqli) {
                $stmt = $conn->prepare(
                    'SELECT pr.role_id, pr.permissions AS portal_permissions, r.permissions AS role_permissions
                     FROM portal_roles pr
                     LEFT JOIN roles r ON pr.role_id = r.role_id
                     WHERE pr.id = ? AND pr.status = \'active\'
                     LIMIT 1'
                );
                if (!$stmt) {
                    return null;
                }
                $stmt->bind_param('i', $portalRoleId);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stmt->close();
            } elseif ($conn instanceof PDO) {
                $stmt = $conn->prepare(
                    'SELECT pr.role_id, pr.permissions AS portal_permissions, r.permissions AS role_permissions
                     FROM portal_roles pr
                     LEFT JOIN roles r ON pr.role_id = r.role_id
                     WHERE pr.id = ? AND pr.status = \'active\'
                     LIMIT 1'
                );
                if (!$stmt) {
                    return null;
                }
                $stmt->execute([$portalRoleId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                return null;
            }

            if (!$row) {
                return null;
            }

            $roleId = (int) ($row['role_id'] ?? 0);
            $permsJson = null;

            if ($row['portal_permissions'] !== null && trim((string) $row['portal_permissions']) !== '') {
                $manual = json_decode((string) $row['portal_permissions'], true);
                if (is_array($manual)) {
                    $permsJson = json_encode($manual, JSON_UNESCAPED_UNICODE);
                }
            }

            if ($permsJson === null) {
                if ($roleId <= 0) {
                    return null;
                }
                $rolePerms = $row['role_permissions'] ?? null;
                if ($rolePerms !== null && is_string($rolePerms)) {
                    $decoded = json_decode($rolePerms, true);
                    $permsJson = json_encode(is_array($decoded) ? $decoded : []);
                } else {
                    $permsJson = json_encode([]);
                }
            }

            if ($roleId <= 0) {
                return [
                    'role_id' => 0,
                    'permissions' => $permsJson,
                ];
            }

            return [
                'role_id' => $roleId,
                'permissions' => $permsJson,
            ];
        } catch (Throwable $e) {
            error_log('rateb_portal_role_permission_payload: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('rateb_portal_role_effective_permissions')) {
    /**
     * @param mysqli|PDO $conn
     * @return array{permissions:array<int,string>,has_manual:bool,role_id:int}
     */
    function rateb_portal_role_effective_permissions($conn, int $portalRoleId): array
    {
        $empty = ['permissions' => [], 'has_manual' => false, 'role_id' => 0];
        if ($portalRoleId <= 0) {
            return $empty;
        }

        rateb_portal_roles_ensure_schema($conn);

        try {
            if ($conn instanceof mysqli) {
                $stmt = $conn->prepare(
                    'SELECT pr.role_id, pr.permissions AS portal_permissions, r.permissions AS role_permissions
                     FROM portal_roles pr
                     LEFT JOIN roles r ON pr.role_id = r.role_id
                     WHERE pr.id = ? LIMIT 1'
                );
                if (!$stmt) {
                    return $empty;
                }
                $stmt->bind_param('i', $portalRoleId);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res ? $res->fetch_assoc() : null;
                $stmt->close();
            } elseif ($conn instanceof PDO) {
                $stmt = $conn->prepare(
                    'SELECT pr.role_id, pr.permissions AS portal_permissions, r.permissions AS role_permissions
                     FROM portal_roles pr
                     LEFT JOIN roles r ON pr.role_id = r.role_id
                     WHERE pr.id = ? LIMIT 1'
                );
                if (!$stmt) {
                    return $empty;
                }
                $stmt->execute([$portalRoleId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                return $empty;
            }

            if (!$row) {
                return $empty;
            }

            $roleId = (int) ($row['role_id'] ?? 0);
            $hasManual = $row['portal_permissions'] !== null && trim((string) $row['portal_permissions']) !== '';
            $source = $hasManual ? $row['portal_permissions'] : ($row['role_permissions'] ?? '[]');
            $decoded = json_decode((string) $source, true);

            return [
                'permissions' => is_array($decoded) ? array_map('strval', $decoded) : [],
                'has_manual' => $hasManual,
                'role_id' => $roleId,
            ];
        } catch (Throwable $e) {
            return $empty;
        }
    }
}

if (!function_exists('rateb_apply_portal_role_to_user_fields')) {
    /**
     * @param mysqli|PDO $conn
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    function rateb_apply_portal_role_to_user_fields($conn, array $fields): array
    {
        $portalRoleId = (int) ($fields['portal_role_id'] ?? 0);
        if ($portalRoleId <= 0) {
            return $fields;
        }

        $payload = rateb_portal_role_permission_payload($conn, $portalRoleId);
        if ($payload === null) {
            return $fields;
        }

        $fields['role_id'] = $payload['role_id'];
        if ($payload['permissions'] !== null) {
            $fields['permissions'] = $payload['permissions'];
        }
        if ((int) ($payload['role_id'] ?? 0) <= 0) {
            unset($fields['role_id']);
        }

        return $fields;
    }
}

if (!function_exists('rateb_portal_roles_sync_users_for_portal_role')) {
    /**
     * @param mysqli|PDO $conn
     */
    function rateb_portal_roles_sync_users_for_portal_role($conn, int $portalRoleId): void
    {
        $payload = rateb_portal_role_permission_payload($conn, $portalRoleId);
        if ($payload === null || $portalRoleId <= 0) {
            return;
        }

        try {
            if ($conn instanceof mysqli) {
                if ((int) ($payload['role_id'] ?? 0) > 0) {
                    $stmt = $conn->prepare(
                        'UPDATE users SET role_id = ?, permissions = ? WHERE portal_role_id = ?'
                    );
                    if ($stmt) {
                        $stmt->bind_param('isi', $payload['role_id'], $payload['permissions'], $portalRoleId);
                        $stmt->execute();
                        $stmt->close();
                    }
                } else {
                    $stmt = $conn->prepare(
                        'UPDATE users SET permissions = ? WHERE portal_role_id = ?'
                    );
                    if ($stmt) {
                        $stmt->bind_param('si', $payload['permissions'], $portalRoleId);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
            } elseif ($conn instanceof PDO) {
                if ((int) ($payload['role_id'] ?? 0) > 0) {
                    $stmt = $conn->prepare(
                        'UPDATE users SET role_id = ?, permissions = ? WHERE portal_role_id = ?'
                    );
                    if ($stmt) {
                        $stmt->execute([$payload['role_id'], $payload['permissions'], $portalRoleId]);
                    }
                } else {
                    $stmt = $conn->prepare(
                        'UPDATE users SET permissions = ? WHERE portal_role_id = ?'
                    );
                    if ($stmt) {
                        $stmt->execute([$payload['permissions'], $portalRoleId]);
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('rateb_portal_roles_sync_users_for_portal_role: ' . $e->getMessage());
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

if (!function_exists('rateb_portal_roles_sync_user_permissions')) {
    /**
     * Apply linked permission roles to users who have portal_role_id set.
     *
     * @param mysqli|PDO $conn
     */
    function rateb_portal_roles_sync_user_permissions($conn): void
    {
        if (!rateb_portal_roles_table_exists($conn)
            || !rateb_portal_roles_column_exists($conn, 'users', 'portal_role_id')
            || !rateb_portal_roles_column_exists($conn, 'portal_roles', 'role_id')) {
            return;
        }

        $sql = "
            UPDATE users u
            INNER JOIN portal_roles pr ON u.portal_role_id = pr.id AND pr.status = 'active'
            LEFT JOIN roles r ON pr.role_id = r.role_id
            SET u.role_id = CASE WHEN pr.role_id IS NOT NULL AND pr.role_id > 0 THEN pr.role_id ELSE u.role_id END,
                u.permissions = COALESCE(pr.permissions, r.permissions, '[]')
            WHERE pr.role_id IS NOT NULL OR pr.permissions IS NOT NULL
        ";

        try {
            if ($conn instanceof mysqli) {
                @$conn->query($sql);
            } elseif ($conn instanceof PDO) {
                $conn->exec($sql);
            }
        } catch (Throwable $e) {
            error_log('rateb_portal_roles_sync_user_permissions: ' . $e->getMessage());
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
