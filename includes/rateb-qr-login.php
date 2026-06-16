<?php
declare(strict_types=1);

/**
 * Enterprise QR login tokens (RATEBLOGIN:…) — validation, issuance, audit, rate limits.
 * Extends existing barcode login; does not replace password/session architecture.
 */
require_once __DIR__ . '/rateb-user-login-barcode.php';
require_once __DIR__ . '/rateb-barcode-login-auth.php'; // legacy + session builder (no circular authenticate)

if (!defined('RATEB_QR_LOGIN_PREFIX')) {
    define('RATEB_QR_LOGIN_PREFIX', 'RATEBLOGIN:');
}

if (!function_exists('rateb_qr_login_country_id_from_slug')) {
    function rateb_qr_login_country_id_from_slug(string $slug): int
    {
        $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($slug)));
        if ($slug === '') {
            return 0;
        }
        $lookup = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
        if (!($lookup instanceof mysqli)) {
            $lookup = $GLOBALS['conn'] ?? null;
        }
        if (!($lookup instanceof mysqli)) {
            return 0;
        }
        $chk = @$lookup->query("SHOW TABLES LIKE 'control_countries'");
        if (!$chk || $chk->num_rows === 0) {
            return 0;
        }
        $stmt = $lookup->prepare('SELECT id FROM control_countries WHERE slug = ? LIMIT 1');
        if (!$stmt) {
            return 0;
        }
        $variants = [$slug];
        $alt1 = str_replace('-', '_', $slug);
        $alt2 = str_replace('_', '-', $slug);
        if ($alt1 !== $slug) {
            $variants[] = $alt1;
        }
        if ($alt2 !== $slug && $alt2 !== $alt1) {
            $variants[] = $alt2;
        }
        foreach ($variants as $trySlug) {
            $stmt->bind_param('s', $trySlug);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
            if ($row) {
                $stmt->close();
                return (int) ($row['id'] ?? 0);
            }
        }
        $stmt->close();

        return 0;
    }
}

if (!function_exists('rateb_qr_login_badge_tenant_context')) {
    /**
     * Tenant ids for badge URLs / validation (session, cookies, GET).
     *
     * @return array{agency_id:int,country_id:int,country_slug:string}
     */
    function rateb_qr_login_badge_tenant_context(): array
    {
        $agencyId = (int) ($_SESSION['agency_id'] ?? $_SESSION['control_agency_id'] ?? 0);
        $countryId = (int) ($_SESSION['country_id'] ?? $_SESSION['control_country_id'] ?? 0);
        if ($agencyId <= 0 && !empty($_COOKIE['rateb_last_agency_id']) && ctype_digit((string) $_COOKIE['rateb_last_agency_id'])) {
            $agencyId = (int) $_COOKIE['rateb_last_agency_id'];
        }
        if ($countryId <= 0 && !empty($_COOKIE['rateb_last_country_id']) && ctype_digit((string) $_COOKIE['rateb_last_country_id'])) {
            $countryId = (int) $_COOKIE['rateb_last_country_id'];
        }
        if (isset($_GET['agency_id']) && ctype_digit((string) $_GET['agency_id'])) {
            $agencyId = (int) $_GET['agency_id'];
        }
        if (isset($_GET['country_id']) && ctype_digit((string) $_GET['country_id'])) {
            $countryId = (int) $_GET['country_id'];
        }
        $slug = '';
        if (!empty($_GET['country_slug'])) {
            $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim((string) $_GET['country_slug'])));
        }
        if ($countryId <= 0 && $slug !== '') {
            $countryId = rateb_qr_login_country_id_from_slug($slug);
        }

        return [
            'agency_id' => $agencyId,
            'country_id' => $countryId,
            'country_slug' => $slug,
        ];
    }
}

if (!function_exists('rateb_qr_login_enrich_context')) {
    /**
     * Fill agency/country from pairing session, URL, or cookies.
     *
     * @param array<string, mixed> $ctx
     */
    function rateb_qr_login_enrich_context(array $ctx, ?string $pairToken = null): array
    {
        $agencyId = (int) ($ctx['agency_id'] ?? 0);
        $countryId = (int) ($ctx['country_id'] ?? 0);
        $slug = trim((string) ($ctx['country_slug'] ?? ''));

        if (($agencyId <= 0 || $countryId <= 0) && $pairToken !== null && $pairToken !== '' && strlen($pairToken) === 32) {
            if (!function_exists('rateb_barcode_pair_read')) {
                require_once __DIR__ . '/rateb-barcode-login-pair.php';
            }
            $pair = rateb_barcode_pair_read($pairToken);
            $pctx = is_array($pair['context'] ?? null) ? $pair['context'] : [];
            if ($agencyId <= 0) {
                $agencyId = (int) ($pctx['agency_id'] ?? 0);
            }
            if ($countryId <= 0) {
                $countryId = (int) ($pctx['country_id'] ?? 0);
            }
            if ($slug === '' && !empty($pctx['country_slug'])) {
                $slug = (string) $pctx['country_slug'];
            }
        }

        if ($countryId <= 0 && $slug !== '') {
            $countryId = rateb_qr_login_country_id_from_slug($slug);
        }
        if ($agencyId <= 0) {
            $tenant = rateb_qr_login_badge_tenant_context();
            if ($agencyId <= 0) {
                $agencyId = (int) ($tenant['agency_id'] ?? 0);
            }
            if ($countryId <= 0) {
                $countryId = (int) ($tenant['country_id'] ?? 0);
            }
            if ($slug === '' && !empty($tenant['country_slug'])) {
                $slug = (string) $tenant['country_slug'];
            }
        }

        $ctx['agency_id'] = $agencyId;
        $ctx['country_id'] = $countryId;
        if ($slug !== '') {
            $ctx['country_slug'] = $slug;
        }

        return rateb_qr_login_resolve_tenant_names($ctx);
    }
}

if (!function_exists('rateb_qr_login_resolve_tenant_names')) {
    /**
     * Fill country_name / agency_name from control DB when ids are known.
     *
     * @param array<string, mixed> $ctx
     * @return array<string, mixed>
     */
    function rateb_qr_login_resolve_tenant_names(array $ctx): array
    {
        $agencyId = (int) ($ctx['agency_id'] ?? 0);
        $countryId = (int) ($ctx['country_id'] ?? 0);
        $countryName = trim((string) ($ctx['country_name'] ?? ''));
        $agencyName = trim((string) ($ctx['agency_name'] ?? ''));

        if ($agencyName !== '' && $countryName !== '') {
            return $ctx;
        }

        $lookup = function_exists('get_control_lookup_conn') ? get_control_lookup_conn() : null;
        if (!($lookup instanceof mysqli)) {
            $lookup = $GLOBALS['conn'] ?? null;
        }
        if (!($lookup instanceof mysqli)) {
            return $ctx;
        }

        $chk = @$lookup->query("SHOW TABLES LIKE 'control_agencies'");
        if (!$chk || $chk->num_rows === 0) {
            return $ctx;
        }

        if ($agencyId > 0) {
            $susp = function_exists('rateb_control_agency_active_fragment')
                ? rateb_control_agency_active_fragment($lookup, 'a')
                : '1=1';
            $stmt = $lookup->prepare(
                "SELECT a.name AS agency_name, a.country_id, c.name AS country_name
                 FROM control_agencies a
                 LEFT JOIN control_countries c ON c.id = a.country_id
                 WHERE a.id = ? AND a.is_active = 1 AND {$susp} LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('i', $agencyId);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
                $stmt->close();
                if ($row) {
                    if ($agencyName === '') {
                        $agencyName = trim((string) ($row['agency_name'] ?? ''));
                    }
                    if ($countryId <= 0) {
                        $countryId = (int) ($row['country_id'] ?? 0);
                    }
                    if ($countryName === '') {
                        $countryName = trim((string) ($row['country_name'] ?? ''));
                    }
                }
            }
        }

        if ($countryName === '' && $countryId > 0) {
            $chkC = @$lookup->query("SHOW TABLES LIKE 'control_countries'");
            if ($chkC && $chkC->num_rows > 0) {
                $stmtC = $lookup->prepare('SELECT name FROM control_countries WHERE id = ? LIMIT 1');
                if ($stmtC) {
                    $stmtC->bind_param('i', $countryId);
                    $stmtC->execute();
                    $resC = $stmtC->get_result();
                    $rowC = ($resC && $resC->num_rows > 0) ? $resC->fetch_assoc() : null;
                    $stmtC->close();
                    if ($rowC) {
                        $countryName = trim((string) ($rowC['name'] ?? ''));
                    }
                }
            }
        }

        if ($countryName === '' && $countryId <= 0) {
            $slug = trim((string) ($ctx['country_slug'] ?? ''));
            if ($slug !== '') {
                $countryId = rateb_qr_login_country_id_from_slug($slug);
                if ($countryId > 0) {
                    $stmtC = $lookup->prepare('SELECT name FROM control_countries WHERE id = ? LIMIT 1');
                    if ($stmtC) {
                        $stmtC->bind_param('i', $countryId);
                        $stmtC->execute();
                        $resC = $stmtC->get_result();
                        $rowC = ($resC && $resC->num_rows > 0) ? $resC->fetch_assoc() : null;
                        $stmtC->close();
                        if ($rowC) {
                            $countryName = trim((string) ($rowC['name'] ?? ''));
                        }
                    }
                }
            }
        }

        if ($countryName !== '') {
            $ctx['country_name'] = $countryName;
        }
        if ($agencyName !== '') {
            $ctx['agency_name'] = $agencyName;
        }
        if ($countryId > 0) {
            $ctx['country_id'] = $countryId;
        }

        return $ctx;
    }
}

if (!function_exists('rateb_qr_login_badge_url')) {
    /**
     * Public HTTPS URL for badge QR (iPhone Camera opens Safari; in-app scanner also accepts this).
     *
     * @param array<string, mixed>|null $ctx agency_id, country_id, country_slug
     */
    function rateb_qr_login_badge_url(string $qrPayload, ?array $ctx = null): string
    {
        $payload = trim($qrPayload);
        if ($payload === '') {
            return '';
        }
        $base = function_exists('rateb_absolute_public_base') ? rateb_absolute_public_base() : '';
        if ($base === '') {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
                    && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
            $scheme = $https ? 'https' : 'http';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
            $base = $host !== '' ? $scheme . '://' . $host : '';
        }
        if ($base === '') {
            return $payload;
        }

        $tenant = $ctx ?? rateb_qr_login_badge_tenant_context();
        $query = ['d' => $payload];
        $agencyId = (int) ($tenant['agency_id'] ?? 0);
        $countryId = (int) ($tenant['country_id'] ?? 0);
        if ($agencyId > 0) {
            $query['agency_id'] = $agencyId;
        }
        if ($countryId > 0) {
            $query['country_id'] = $countryId;
        }
        $slug = trim((string) ($tenant['country_slug'] ?? ''));
        if ($slug !== '') {
            $query['country_slug'] = $slug;
        }

        return rtrim($base, '/') . '/login/badge?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}

if (!function_exists('rateb_qr_login_token_hash')) {
    function rateb_qr_login_token_hash(string $plainToken): string
    {
        $pepper = defined('DB_PASS') ? (string) DB_PASS : 'rateb';
        return hash('sha256', $pepper . '|' . $plainToken);
    }
}

if (!function_exists('rateb_qr_login_ensure_schema')) {
    function rateb_qr_login_ensure_schema(mysqli $db): void
    {
        static $done = [];
        $key = function_exists('spl_object_id') ? spl_object_id($db) : spl_object_hash($db);
        if (isset($done[$key])) {
            return;
        }
        $cols = [];
        $res = @$db->query('SHOW COLUMNS FROM users');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $cols[] = strtolower((string) ($row['Field'] ?? ''));
            }
        }
        $alters = [];
        if (!in_array('qr_login_token', $cols, true)) {
            $alters[] = 'ADD COLUMN qr_login_token VARCHAR(64) NULL DEFAULT NULL';
        }
        if (!in_array('qr_token_expires_at', $cols, true)) {
            $alters[] = 'ADD COLUMN qr_token_expires_at DATETIME NULL DEFAULT NULL';
        }
        if (!in_array('qr_token_revoked_at', $cols, true)) {
            $alters[] = 'ADD COLUMN qr_token_revoked_at DATETIME NULL DEFAULT NULL';
        }
        if (!in_array('last_qr_scan_at', $cols, true)) {
            $alters[] = 'ADD COLUMN last_qr_scan_at DATETIME NULL DEFAULT NULL';
        }
        if ($alters !== []) {
            @$db->query('ALTER TABLE users ' . implode(', ', $alters));
        }
        @$db->query(
            'CREATE TABLE IF NOT EXISTS qr_login_audit (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NULL,
                event_type VARCHAR(32) NOT NULL,
                outcome VARCHAR(16) NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                pair_token VARCHAR(32) NULL,
                meta_json TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_qr_audit_user (user_id),
                KEY idx_qr_audit_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $done[$key] = true;
    }
}

if (!function_exists('rateb_qr_login_client_meta')) {
    /**
     * @return array{ip:string, ua:string}
     */
    function rateb_qr_login_client_meta(): array
    {
        $ip = (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        return ['ip' => $ip, 'ua' => $ua];
    }
}

if (!function_exists('rateb_qr_login_rate_limit_ok')) {
    function rateb_qr_login_rate_limit_ok(string $bucket, int $maxPerMinute = 30): bool
    {
        $dir = sys_get_temp_dir() . '/rateb_qr_ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $meta = rateb_qr_login_client_meta();
        $key = hash('sha256', $bucket . '|' . $meta['ip']);
        $file = $dir . '/' . $key . '.json';
        $now = time();
        $window = [];
        if (is_file($file)) {
            $raw = @file_get_contents($file);
            $decoded = $raw ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                foreach ($decoded as $ts) {
                    if ($now - (int) $ts < 60) {
                        $window[] = (int) $ts;
                    }
                }
            }
        }
        if (count($window) >= $maxPerMinute) {
            return false;
        }
        $window[] = $now;
        @file_put_contents($file, json_encode($window), LOCK_EX);
        return true;
    }
}

if (!function_exists('rateb_qr_login_audit')) {
    function rateb_qr_login_audit(
        mysqli $db,
        string $eventType,
        string $outcome,
        ?int $userId = null,
        ?string $pairToken = null,
        ?array $meta = null
    ): void {
        rateb_qr_login_ensure_schema($db);
        $m = rateb_qr_login_client_meta();
        $metaJson = $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null;
        $stmt = @$db->prepare(
            'INSERT INTO qr_login_audit (user_id, event_type, outcome, ip_address, user_agent, pair_token, meta_json)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$stmt) {
            return;
        }
        $uidBind = $userId ?? 0;
        $pairTok = $pairToken ?? '';
        $metaStr = $metaJson ?? '';
        $stmt->bind_param('issssss', $uidBind, $eventType, $outcome, $m['ip'], $m['ua'], $pairTok, $metaStr);
        @$stmt->execute();
        $stmt->close();
        error_log(sprintf(
            'QR_LOGIN_AUDIT event=%s outcome=%s user_id=%s ip=%s',
            $eventType,
            $outcome,
            $userId !== null ? (string) $userId : '-',
            $m['ip']
        ));
    }
}

if (!function_exists('rateb_qr_login_normalize_payload')) {
    /**
     * @return array{type:string, value:string}
     */
    function rateb_qr_login_normalize_payload(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['type' => 'empty', 'value' => ''];
        }
        if (stripos($raw, RATEB_QR_LOGIN_PREFIX) === 0) {
            $body = substr($raw, strlen(RATEB_QR_LOGIN_PREFIX));
            if (strpos($body, '.') !== false) {
                [$plainPart, $sigPart] = explode('.', $body, 2);
                $plain = preg_replace('/[^a-f0-9]/', '', strtolower($plainPart));
                $sig = preg_replace('/[^a-f0-9]/', '', strtolower($sigPart));
                if ($plain !== '' && function_exists('rateb_qr_login_verify_signed_plain')) {
                    require_once __DIR__ . '/rateb-qr-workforce-identity.php';
                    if (!rateb_qr_login_verify_signed_plain($plain, $sig)) {
                        return ['type' => 'invalid_sig', 'value' => ''];
                    }
                }
                return ['type' => 'secure', 'value' => $plain];
            }
            $token = preg_replace('/[^a-f0-9]/', '', strtolower($body));
            return ['type' => 'secure', 'value' => $token];
        }
        if (preg_match('#^https?://#i', $raw)) {
            $parts = parse_url($raw);
            if (is_array($parts) && !empty($parts['query'])) {
                parse_str((string) $parts['query'], $qs);
                foreach (['d', 'badge', 'p'] as $qk) {
                    if (!empty($qs[$qk]) && is_string($qs[$qk])) {
                        return rateb_qr_login_normalize_payload((string) $qs[$qk]);
                    }
                }
            }
            $path = (string) ($parts['path'] ?? '');
            if (preg_match('#/login/badge#i', $path)) {
                return ['type' => 'empty', 'value' => ''];
            }
            if (preg_match('#login[-/]scan|login-scan\.php#i', $raw)) {
                return ['type' => 'pairing_url', 'value' => $raw];
            }
            return ['type' => 'unknown_url', 'value' => $raw];
        }
        if (preg_match('#login[-/]scan|login-scan\.php#i', $raw)) {
            return ['type' => 'pairing_url', 'value' => $raw];
        }
        if (preg_match('/^[Rr]\d{5,}[A-Za-z0-9]{0,8}$/', $raw)) {
            return ['type' => 'legacy', 'value' => $raw];
        }
        return ['type' => 'legacy', 'value' => $raw];
    }
}

if (!function_exists('rateb_qr_login_issue_token')) {
    /**
     * Issue a new secure QR token (regenerate). Plain payload returned once.
     *
     * @return array{ok:bool, qr_payload?:string, expires_at?:string|null, message?:string, regenerated?:bool}
     */
    function rateb_qr_login_issue_token(mysqli $db, int $userId, int $ttlSeconds = 0, bool $isRegenerate = false): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'message' => 'Invalid user.'];
        }
        if (is_file(__DIR__ . '/rateb-qr-workforce-identity.php')) {
            require_once __DIR__ . '/rateb-qr-workforce-identity.php';
            rateb_qr_workforce_ensure_schema($db);
        } else {
            rateb_qr_login_ensure_schema($db);
        }
        $pk = rateb_users_primary_key_for_barcode($db);
        try {
            $plain = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Could not generate token.'];
        }
        $hash = rateb_qr_login_token_hash($plain);
        $expires = null;
        $expiresDb = '2099-12-31 23:59:59';
        if ($ttlSeconds > 0) {
            $expiresDb = date('Y-m-d H:i:s', time() + max(300, $ttlSeconds));
            $expires = $expiresDb;
        } elseif ($ttlSeconds < 0) {
            $expiresDb = date('Y-m-d H:i:s', time() + 31536000);
            $expires = $expiresDb;
        }
        $stmt = $db->prepare(
            "UPDATE users SET qr_login_token = ?, qr_token_expires_at = ?, qr_token_revoked_at = NULL,
             qr_login_enabled = 1 WHERE `{$pk}` = ? LIMIT 1"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Database error.'];
        }
        $stmt->bind_param('ssi', $hash, $expiresDb, $userId);
        $stmt->execute();
        $stmt->close();
        $payload = function_exists('rateb_qr_login_build_signed_payload')
            ? rateb_qr_login_build_signed_payload($plain)
            : (RATEB_QR_LOGIN_PREFIX . $plain);
        rateb_qr_login_audit($db, $isRegenerate ? 'token_regenerated' : 'token_issued', 'ok', $userId, null, [
            'expires' => $expires,
            'persistent' => $expires === null,
        ]);
        return [
            'ok' => true,
            'qr_payload' => $payload,
            'expires_at' => $expires,
            'regenerated' => $isRegenerate,
        ];
    }
}

if (!function_exists('rateb_qr_login_revoke_token')) {
    function rateb_qr_login_revoke_token(mysqli $db, int $userId): bool
    {
        rateb_qr_login_ensure_schema($db);
        $pk = rateb_users_primary_key_for_barcode($db);
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare(
            "UPDATE users SET qr_token_revoked_at = ?, qr_login_token = NULL WHERE `{$pk}` = ? LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $now, $userId);
        $ok = $stmt->execute();
        $stmt->close();
        if ($ok) {
            rateb_qr_login_audit($db, 'token_revoked', 'ok', $userId);
        }
        return $ok;
    }
}

if (!function_exists('rateb_qr_login_find_user_by_secure_token')) {
    /**
     * @return array{ok:bool, user?:array<string,mixed>, message?:string, code?:string}
     */
    function rateb_qr_login_find_user_by_secure_token(mysqli $db, string $plainHex): array
    {
        rateb_qr_login_ensure_schema($db);
        $plainHex = preg_replace('/[^a-f0-9]/', '', strtolower($plainHex));
        if (strlen($plainHex) < 32) {
            return ['ok' => false, 'message' => 'Invalid QR code.', 'code' => 'invalid'];
        }
        $hash = rateb_qr_login_token_hash($plainHex);
        $pk = rateb_users_primary_key_for_barcode($db);
        $stmt = $db->prepare(
            "SELECT * FROM users WHERE qr_login_token = ? LIMIT 1"
        );
        if (!$stmt) {
            return ['ok' => false, 'message' => 'Database error.', 'code' => 'error'];
        }
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $res = $stmt->get_result();
        $user = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
        $stmt->close();
        if (!$user) {
            return [
                'ok' => false,
                'message' => 'QR not recognized for this office. Regenerate the badge in Users → Access, then use Copy badge link.',
                'code' => 'invalid',
            ];
        }
        if (isset($user['qr_login_enabled']) && (int) $user['qr_login_enabled'] === 0) {
            return ['ok' => false, 'message' => 'Workforce QR access is disabled.', 'code' => 'disabled'];
        }
        if (!empty($user['qr_token_revoked_at'])) {
            return ['ok' => false, 'message' => 'This badge has been revoked.', 'code' => 'revoked'];
        }
        $expires = strtotime((string) ($user['qr_token_expires_at'] ?? ''));
        if ($expires > 0 && time() > $expires) {
            return ['ok' => false, 'message' => 'This QR code has expired.', 'code' => 'expired'];
        }
        $uid = (int) ($user[$pk] ?? $user['user_id'] ?? $user['id'] ?? 0);
        if ($uid > 0) {
            $last = strtotime((string) ($user['last_qr_scan_at'] ?? ''));
            if ($last > 0 && (time() - $last) < 2) {
                return ['ok' => false, 'message' => 'Please wait before scanning again.', 'code' => 'replay'];
            }
        }
        if (!isset($user['user_id'])) {
            $user['user_id'] = $uid;
        }
        return ['ok' => true, 'user' => $user];
    }
}

if (!function_exists('rateb_qr_login_mark_scan')) {
    function rateb_qr_login_mark_scan(mysqli $db, int $userId): void
    {
        $pk = rateb_users_primary_key_for_barcode($db);
        $now = date('Y-m-d H:i:s');
        if (is_file(__DIR__ . '/rateb-qr-workforce-identity.php')) {
            require_once __DIR__ . '/rateb-qr-workforce-identity.php';
            rateb_qr_workforce_ensure_schema($db);
            $stmt = $db->prepare(
                "UPDATE users SET last_qr_scan_at = ?, qr_last_used_at = ? WHERE `{$pk}` = ? LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('ssi', $now, $now, $userId);
                $stmt->execute();
                $stmt->close();
                return;
            }
        }
        $stmt = $db->prepare("UPDATE users SET last_qr_scan_at = ? WHERE `{$pk}` = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('si', $now, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

if (!function_exists('rateb_qr_login_authenticate_payload')) {
    /**
     * Validate scanned QR/barcode and return session payload (same shape as password login).
     *
     * @param array<string, mixed> $ctx country_id, agency_id, …
     * @return array{ok:bool, session?:array<string,mixed>, message?:string, code?:string}
     */
    function rateb_qr_login_authenticate_payload(string $payload, array $ctx, ?string $pairToken = null): array
    {
        $ctx = rateb_qr_login_enrich_context($ctx, $pairToken);
        if (!rateb_qr_login_rate_limit_ok('validate', 40)) {
            return ['ok' => false, 'message' => 'Too many attempts. Try again shortly.', 'code' => 'rate_limit'];
        }
        $loginConn = rateb_barcode_login_resolve_connection($ctx);
        if (!($loginConn instanceof mysqli)) {
            return ['ok' => false, 'message' => 'Database unavailable.', 'code' => 'error'];
        }
        $parsed = rateb_qr_login_normalize_payload($payload);
        if ($parsed['type'] === 'empty' || $parsed['type'] === 'invalid_sig') {
            return ['ok' => false, 'message' => $parsed['type'] === 'invalid_sig' ? 'Invalid badge signature.' : 'Empty scan.', 'code' => 'invalid'];
        }
        if ($parsed['type'] === 'pairing_url') {
            return [
                'ok' => false,
                'message' => 'That is the computer pairing QR. Scan the employee badge from Users → Barcode instead.',
                'code' => 'pairing_qr',
            ];
        }
        $user = null;
        $skipPin = !empty($ctx['skip_pin']);
        $trustDevice = !empty($ctx['trust_device']);
        if ($parsed['type'] === 'secure') {
            $found = rateb_qr_login_find_user_by_secure_token($loginConn, $parsed['value']);
            if (empty($found['ok'])) {
                rateb_qr_login_audit($loginConn, 'scan_validate', 'fail', null, $pairToken, [
                    'code' => $found['code'] ?? 'invalid',
                ]);
                return [
                    'ok' => false,
                    'message' => $found['message'] ?? 'QR not recognized.',
                    'code' => $found['code'] ?? 'invalid',
                ];
            }
            $user = $found['user'];
        } else {
            $legacy = rateb_barcode_login_authenticate_legacy($parsed['value'], $ctx);
            if (empty($legacy['ok'])) {
                rateb_qr_login_audit($loginConn, 'scan_validate', 'fail', null, $pairToken, ['legacy' => true]);
                return [
                    'ok' => false,
                    'message' => $legacy['message'] ?? 'Not recognized.',
                    'code' => 'invalid',
                ];
            }
            return $legacy;
        }
        $uid = (int) ($user['user_id'] ?? 0);
        if ($uid > 0 && is_file(__DIR__ . '/rateb-qr-workforce-identity.php')) {
            require_once __DIR__ . '/rateb-qr-workforce-identity.php';
            if (!$skipPin && function_exists('rateb_qr_pin_required_for_user') && rateb_qr_pin_required_for_user($user)) {
                $challenge = rateb_qr_challenge_create($loginConn, $uid, $pairToken, $ctx);
                if ($challenge === null) {
                    return ['ok' => false, 'message' => 'Could not start PIN challenge.', 'code' => 'error'];
                }
                rateb_qr_login_audit($loginConn, 'pin_challenge', 'ok', $uid, $pairToken);
                return [
                    'ok' => false,
                    'needs_pin' => true,
                    'challenge_token' => $challenge,
                    'message' => 'Enter your 4-digit PIN.',
                    'code' => 'needs_pin',
                ];
            }
        }
        $session = rateb_barcode_login_build_session($loginConn, $user, $ctx);
        if ($session === null) {
            rateb_qr_login_audit($loginConn, 'scan_validate', 'fail', $uid, $pairToken, ['reason' => 'inactive']);
            return ['ok' => false, 'message' => 'Account inactive or not allowed.', 'code' => 'inactive'];
        }
        rateb_qr_login_mark_scan($loginConn, $uid);
        rateb_qr_login_audit($loginConn, 'scan_validate', 'ok', $uid, $pairToken, ['secure' => true]);
        if ($trustDevice && is_file(__DIR__ . '/rateb-qr-workforce-identity.php')) {
            require_once __DIR__ . '/rateb-qr-workforce-identity.php';
            try {
                $devTok = bin2hex(random_bytes(32));
                if (rateb_qr_trusted_device_register($loginConn, $uid, $devTok, (string) ($ctx['device_label'] ?? 'Mobile'))) {
                    $session['_trusted_device_token'] = $devTok;
                }
            } catch (Throwable $e) {
                /* ignore */
            }
        }
        return ['ok' => true, 'session' => $session];
    }
}

if (!function_exists('rateb_qr_login_complete_with_pin')) {
    /**
     * @param array<string, mixed> $ctx
     * @return array{ok:bool, session?:array, message?:string, code?:string}
     */
    function rateb_qr_login_complete_with_pin(string $challengeToken, string $pin, array $ctx): array
    {
        if (!rateb_qr_login_rate_limit_ok('pin_validate', 30)) {
            return ['ok' => false, 'message' => 'Too many attempts.', 'code' => 'rate_limit'];
        }
        if (!is_file(__DIR__ . '/rateb-qr-workforce-identity.php')) {
            return ['ok' => false, 'message' => 'PIN not available.', 'code' => 'error'];
        }
        require_once __DIR__ . '/rateb-qr-workforce-identity.php';
        $loginConn = rateb_barcode_login_resolve_connection($ctx);
        if (!($loginConn instanceof mysqli)) {
            return ['ok' => false, 'message' => 'Database unavailable.', 'code' => 'error'];
        }
        $ch = rateb_qr_challenge_consume($loginConn, $challengeToken);
        if (empty($ch['ok'])) {
            return ['ok' => false, 'message' => $ch['message'] ?? 'Invalid challenge.', 'code' => 'invalid'];
        }
        $uid = (int) ($ch['user_id'] ?? 0);
        if (!rateb_qr_pin_verify($loginConn, $uid, $pin)) {
            return ['ok' => false, 'message' => 'Incorrect PIN.', 'code' => 'pin_invalid'];
        }
        $row = rateb_qr_workforce_user_row($loginConn, $uid);
        if (!$row) {
            return ['ok' => false, 'message' => 'User not found.', 'code' => 'invalid'];
        }
        if (!isset($row['user_id'])) {
            $pk = rateb_users_primary_key_for_barcode($loginConn);
            $row['user_id'] = (int) ($row[$pk] ?? $uid);
        }
        $mergedCtx = array_merge($ch['context'] ?? [], $ctx, ['skip_pin' => true]);
        $session = rateb_barcode_login_build_session($loginConn, $row, $mergedCtx);
        if ($session === null) {
            return ['ok' => false, 'message' => 'Account inactive.', 'code' => 'inactive'];
        }
        rateb_qr_login_mark_scan($loginConn, $uid);
        rateb_qr_login_audit($loginConn, 'pin_login', 'ok', $uid, $ch['pair_token'] ?? null);
        $pairTok = trim((string) ($ch['pair_token'] ?? ''));
        if (!empty($ctx['trust_device'])) {
            try {
                $devTok = bin2hex(random_bytes(32));
                if (rateb_qr_trusted_device_register($loginConn, $uid, $devTok, (string) ($ctx['device_label'] ?? 'Mobile'))) {
                    $session['_trusted_device_token'] = $devTok;
                }
            } catch (Throwable $e) {
                /* ignore */
            }
        }
        $result = ['ok' => true, 'session' => $session];
        if ($pairTok !== '' && strlen($pairTok) === 32) {
            $result['pair_token'] = $pairTok;
        }
        return $result;
    }
}
