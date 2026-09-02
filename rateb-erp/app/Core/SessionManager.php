<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class SessionManager
{
    public static function cookiePath(): string
    {
        // Keep ERP session scoped to the app prefix. Site-wide "/" caused duplicate
        // rateb_erp cookies (old /rateb-erp/public + new /) and broke login CSRF.
        if (function_exists('rateb_erp_app_prefix')) {
            $p = rtrim((string) rateb_erp_app_prefix(), '/');
            if ($p !== '') {
                return $p;
            }
        }

        $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
        if (in_array($host, ['rateb.sa', 'www.rateb.sa'], true)) {
            return '/rateb-erp/public';
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (preg_match('#(/rateb-erp/public)(?:/|$)#', $uri, $m)) {
            return $m[1];
        }

        return '/';
    }

    /** @return list<string> */
    public static function cookiePathCandidates(): array
    {
        // Always expire both legacy "/" and app-prefix cookies when clearing.
        return array_values(array_unique(array_filter([
            self::cookiePath(),
            '/rateb-erp/public',
            '/',
        ])));
    }

    /** Drop non-canonical session/CSRF cookies so only one path remains. */
    public static function clearAlternatePathCookies(): void
    {
        if (headers_sent()) {
            return;
        }
        $canonical = self::cookiePath();
        foreach (['rateb_erp', 'rateb_csrf'] as $name) {
            foreach (self::cookiePathCandidates() as $path) {
                if ($path === $canonical) {
                    continue;
                }
                self::expireCookieOnPath($name, $path);
            }
        }
    }

    /**
     * Expire rateb_erp + rateb_csrf on EVERY candidate path (including canonical).
     * Use only on login recovery / logout — never mid-session.
     */
    public static function purgeAllAuthCookies(): void
    {
        if (headers_sent()) {
            return;
        }
        foreach (['rateb_erp', 'rateb_csrf'] as $name) {
            self::expireNamedCookie($name);
        }
        // Also clear common host variants browsers may have stored.
        $host = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
        if ($host !== '' && !headers_sent()) {
            $secure = self::requestIsSecure();
            foreach (['rateb_erp', 'rateb_csrf'] as $name) {
                foreach (self::cookiePathCandidates() as $path) {
                    if (PHP_VERSION_ID >= 70300) {
                        setcookie($name, '', [
                            'expires' => time() - 42000,
                            'path' => $path,
                            'domain' => $host,
                            'secure' => $secure,
                            'httponly' => true,
                            'samesite' => 'Lax',
                        ]);
                        if (str_starts_with($host, 'www.')) {
                            setcookie($name, '', [
                                'expires' => time() - 42000,
                                'path' => $path,
                                'domain' => substr($host, 4),
                                'secure' => $secure,
                                'httponly' => true,
                                'samesite' => 'Lax',
                            ]);
                        }
                    }
                }
            }
        }
    }

    private static function expireCookieOnPath(string $name, string $path, string $domain = ''): void
    {
        if (headers_sent()) {
            return;
        }
        $secure = self::requestIsSecure();
        if (PHP_VERSION_ID >= 70300) {
            setcookie($name, '', [
                'expires' => time() - 42000,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            setcookie($name, '', time() - 42000, $path, $domain, $secure, true);
        }
    }

    private static function expireNamedCookie(string $name): void
    {
        foreach (self::cookiePathCandidates() as $path) {
            self::expireCookieOnPath($name, $path, '');
        }
    }

    public static function start(): void
    {
        if (defined('RATEB_ENV_NO_SESSION') && RATEB_ENV_NO_SESSION) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (session_name() === 'rateb_erp') {
                return;
            }
            session_write_close();
        }

        session_name('rateb_erp');
        self::ensureSavePath();
        $secure = self::requestIsSecure();
        $cookiePath = self::cookiePath();
        // CRITICAL: never clearAlternatePathCookies() here.
        // 39bcc4aa still cleared them on every authenticated request — that expired the
        // live path=/ session cookie before the browser reliably adopted Path=/rateb-erp/public,
        // so the next POST (Import) arrived with no session → login?err=session.
        // Clear duplicates ONLY on login success, login recovery (err=csrf|session), and destroy().

        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => $cookiePath,
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(0, $cookiePath, '', $secure, true);
        }

        session_start();

        // PHP keeps only one rateb_erp when path=/ and path=/rateb-erp/public both exist.
        // If the empty/stale id won, adopt any sibling cookie that still has a logged-in user.
        if (empty($_SESSION['rateb_user_id'])) {
            self::adoptAuthenticatedDuplicateSession();
        }

        if (empty($_SESSION['_rateb_init'])) {
            // Regenerating an *empty* session mints Set-Cookie rateb_erp=newEmpty and can
            // clobber a still-valid sibling cookie during soft-nav/auth bounce. Only rotate
            // when authenticated (login path uses regenerate() explicitly).
            if (!empty($_SESSION['rateb_user_id'])) {
                session_regenerate_id(true);
            }
            $_SESSION['_rateb_init'] = time();
        }

        // Soft pin only: re-send canonical cookie once per session. Do NOT expire path=/.
        if (!empty($_SESSION['rateb_user_id'])
            && empty($_SESSION['_rateb_cookie_pinned'])
            && !headers_sent()
        ) {
            self::reissueCanonicalSessionCookie();
            $_SESSION['_rateb_cookie_pinned'] = 1;
        }
    }

    /**
     * When duplicate rateb_erp cookies are sent, try each id until one has rateb_user_id.
     */
    private static function adoptAuthenticatedDuplicateSession(): void
    {
        $candidates = self::rawCookieValues('rateb_erp');
        if (count($candidates) < 2) {
            return;
        }
        $current = session_id();
        foreach ($candidates as $sid) {
            if ($sid === '' || $sid === $current || !preg_match('/^[a-zA-Z0-9,-]{16,128}$/', $sid)) {
                continue;
            }
            session_write_close();
            session_id($sid);
            session_start();
            if (!empty($_SESSION['rateb_user_id'])) {
                if (!headers_sent()) {
                    self::reissueCanonicalSessionCookie();
                }

                return;
            }
        }
        // Restore original empty session if none authenticated.
        if (session_id() !== $current && $current !== '') {
            session_write_close();
            session_id($current);
            session_start();
        }
    }

    /** @return list<string> */
    private static function rawCookieValues(string $name): array
    {
        $values = [];
        $raw = (string) ($_SERVER['HTTP_COOKIE'] ?? '');
        if ($raw !== '') {
            foreach (explode(';', $raw) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $eq = strpos($part, '=');
                if ($eq === false) {
                    continue;
                }
                if (trim(substr($part, 0, $eq)) !== $name) {
                    continue;
                }
                $values[] = rawurldecode(trim(substr($part, $eq + 1)));
            }
        }
        if (isset($_COOKIE[$name])) {
            $values[] = (string) $_COOKIE[$name];
        }

        return array_values(array_unique(array_filter($values, static fn ($v) => $v !== '')));
    }

    /** Re-send session cookie on the canonical app path (migration from legacy path=/). */
    public static function reissueCanonicalSessionCookie(): void
    {
        if (headers_sent() || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = self::requestIsSecure();
        $path = self::cookiePath();
        $params = [
            'expires' => 0,
            'path' => $path,
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if (PHP_VERSION_ID >= 70300) {
            setcookie(session_name(), session_id(), $params);
        } else {
            setcookie(session_name(), session_id(), 0, $path, '', $secure, true);
        }
    }

    private static function ensureActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE || session_name() !== 'rateb_erp') {
            self::start();
        }
    }

    private static function requestIsSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    }

    private static function ensureSavePath(): void
    {
        foreach (self::sessionSavePathCandidates() as $dir) {
            $resolved = realpath($dir);
            if ($resolved !== false && is_dir($resolved) && is_writable($resolved)) {
                session_save_path($resolved);

                return;
            }
        }

        if (!defined('RATEB_ROOT')) {
            return;
        }

        $dir = rtrim(str_replace('\\', '/', (string) RATEB_ROOT), '/') . '/storage/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
    }

    /**
     * @return list<string>
     */
    private static function sessionSavePathCandidates(): array
    {
        $candidates = [];

        $fromEnv = getenv('RATEB_ERP_SESSION_SAVE_PATH');
        if (is_string($fromEnv) && $fromEnv !== '') {
            $candidates[] = $fromEnv;
        }

        if (defined('RATEB_ERP_SESSION_SAVE_PATH') && (string) RATEB_ERP_SESSION_SAVE_PATH !== '') {
            $candidates[] = (string) RATEB_ERP_SESSION_SAVE_PATH;
        }

        if (defined('RATEB_ROOT')) {
            $candidates[] = rtrim(str_replace('\\', '/', (string) RATEB_ROOT), '/') . '/storage/sessions';
        }

        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? (string) $_SERVER['DOCUMENT_ROOT'] : '';
        if ($docRoot !== '') {
            $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
            $candidates[] = $docRoot . '/rateb-erp/storage/sessions';
            $candidates[] = dirname($docRoot) . '/rateb-erp/storage/sessions';
        }

        return array_values(array_unique($candidates));
    }

    public static function get(string $key, $default = null)
    {
        self::ensureActive();
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        self::ensureActive();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::ensureActive();
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, $value = null)
    {
        self::ensureActive();
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $val = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $val;
    }

    /**
     * Drop orphan «access_denied» left by aborted soft-nav / concurrent deny.
     * Call only after the current request has already passed its own auth guards.
     */
    public static function discardStaleAccessDeniedFlash(): void
    {
        self::ensureActive();
        $err = $_SESSION['_flash']['error'] ?? null;
        if (!is_string($err) || $err === '') {
            return;
        }
        $denied = function_exists('__') ? (string) __('access_denied') : '';
        if (($denied !== '' && $err === $denied)
            || str_contains($err, 'ليس لديك صلاحية')
            || stripos($err, 'do not have permission') !== false) {
            unset($_SESSION['_flash']['error']);
        }
    }

    /**
     * Drop leftover «module not in plan» flashes (wrong-module banner after prefetch).
     * Call on add-on checkout/status pages that already explain the locked state.
     */
    public static function discardStaleModuleNotInPlanFlash(): void
    {
        self::ensureActive();
        $err = $_SESSION['_flash']['error'] ?? null;
        if (!is_string($err) || $err === '') {
            return;
        }
        if (str_contains($err, 'غير مشمولة')
            || stripos($err, 'not included in your current plan') !== false
            || str_contains($err, 'module_not_in_plan')) {
            unset($_SESSION['_flash']['error']);
        }
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            self::expireNamedCookie(session_name());
            session_destroy();
        } else {
            self::expireNamedCookie('rateb_erp');
        }
        self::clearAlternatePathCookies();
        if (class_exists(Csrf::class)) {
            Csrf::clearCookie();
        }
        session_name('rateb_erp');
        self::start();
        session_regenerate_id(true);
        $_SESSION = ['_rateb_init' => time()];
    }
}
