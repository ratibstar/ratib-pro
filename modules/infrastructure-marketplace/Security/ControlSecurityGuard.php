<?php
declare(strict_types=1);

namespace Ratib\InfrastructureMarketplace\Security;

/**
 * Unified control-plane security pipeline: session auth, RBAC tiers, CSRF, rate limits.
 * All infrastructure control APIs should delegate to this class (no scattered checks).
 */
final class ControlSecurityGuard
{
    /** Anonymous / public telemetry — rate limit only. */
    public const TIER_PUBLIC_READ = 1;

    /** Public mutating endpoint (e.g. marketplace order) — strict IP limits, no CSRF. */
    public const TIER_PUBLIC_MUTATOR = 2;

    /** Control session + view-level infra scope. */
    public const TIER_CONTROL_VIEW = 3;

    /** Control session + edit / system-settings parent (operational writes). */
    public const TIER_CONTROL_WRITE = 4;

    /** Runtime overrides, kill switch, provider activation / emergency toggles. */
    public const TIER_CONTROL_SYSTEM = 5;

    /**
     * @param array{json_body?: array|null, require_csrf?: bool} $options
     */
    public static function enforce(string $routeId, int $tier, array $options = []): void
    {
        if ($tier >= self::TIER_CONTROL_VIEW) {
            self::bootstrapControlPanel();
        }
        self::applyRateLimits($routeId, $tier);

        if ($tier === self::TIER_PUBLIC_READ || $tier === self::TIER_PUBLIC_MUTATOR) {
            return;
        }

        if (empty($_SESSION['control_logged_in'])) {
            self::deny(403, 'Control session required');
        }

        if ($tier === self::TIER_CONTROL_VIEW) {
            if (!self::canControlView()) {
                self::deny(403, 'Insufficient permissions for this resource');
            }
            self::ensureInfraCsrfSessionToken();
            return;
        }

        if ($tier === self::TIER_CONTROL_WRITE) {
            if (!self::canControlWrite()) {
                self::deny(403, 'Insufficient permissions for this action');
            }
            self::ensureInfraCsrfSessionToken();
            if (($options['require_csrf'] ?? true) && self::isMutatingHttpMethod()) {
                self::assertCsrfMatches($options['json_body'] ?? null);
            }
            return;
        }

        if ($tier === self::TIER_CONTROL_SYSTEM) {
            if (!self::canControlSystem()) {
                self::deny(403, 'Insufficient permissions for system control operations');
            }
            self::ensureInfraCsrfSessionToken();
            if (($options['require_csrf'] ?? true) && self::isMutatingHttpMethod()) {
                self::assertCsrfMatches($options['json_body'] ?? null);
            }
            return;
        }

        self::deny(500, 'Invalid security tier');
    }

    private static function isMutatingHttpMethod(): bool
    {
        $m = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        return $m === 'POST' || $m === 'PUT' || $m === 'PATCH' || $m === 'DELETE';
    }

    private static function bootstrapControlPanel(): void
    {
        if (defined('CONTROL_CONFIG_LOADED') && function_exists('hasControlPermission')) {
            return;
        }
        // Security → repo root: .../modules/infrastructure-marketplace/Security → 3 levels up.
        $config = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'control-panel' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'config.php';
        if (is_file($config)) {
            require_once $config;
        }
    }

    private static function canControlView(): bool
    {
        $u = strtolower(trim((string) ($_SESSION['control_username'] ?? '')));
        if ($u === 'admin') {
            return true;
        }
        if (!function_exists('hasControlPermission')) {
            return false;
        }
        return hasControlPermission('view_control_system_settings')
            || hasControlPermission('edit_control_system_settings')
            || (defined('CONTROL_PERM_SYSTEM_SETTINGS') && hasControlPermission(CONTROL_PERM_SYSTEM_SETTINGS));
    }

    private static function canControlWrite(): bool
    {
        $u = strtolower(trim((string) ($_SESSION['control_username'] ?? '')));
        if ($u === 'admin') {
            return true;
        }
        if (!function_exists('hasControlPermission')) {
            return false;
        }
        return hasControlPermission('edit_control_system_settings')
            || (defined('CONTROL_PERM_SYSTEM_SETTINGS') && hasControlPermission(CONTROL_PERM_SYSTEM_SETTINGS));
    }

    /**
     * System plane: excludes view-only operators (no edit / no system-settings parent).
     */
    private static function canControlSystem(): bool
    {
        $u = strtolower(trim((string) ($_SESSION['control_username'] ?? '')));
        if ($u === 'admin') {
            return true;
        }
        if (!function_exists('hasControlPermission')) {
            return false;
        }
        return hasControlPermission('edit_control_system_settings')
            || (defined('CONTROL_PERM_SYSTEM_SETTINGS') && hasControlPermission(CONTROL_PERM_SYSTEM_SETTINGS));
    }

    public static function ensureInfraCsrfSessionToken(): void
    {
        if (empty($_SESSION['infra_control_csrf_token']) || !is_string($_SESSION['infra_control_csrf_token'])) {
            try {
                $_SESSION['infra_control_csrf_token'] = bin2hex(random_bytes(32));
            } catch (\Throwable $e) {
                $_SESSION['infra_control_csrf_token'] = sha1((string) microtime(true) . (string) mt_rand());
            }
        }
    }

    /**
     * @param array<string, mixed>|null $jsonBody Decoded JSON body for POST APIs (csrf_token key).
     */
    private static function assertCsrfMatches(?array $jsonBody): void
    {
        self::ensureInfraCsrfSessionToken();
        $sessionCsrf = (string) ($_SESSION['infra_control_csrf_token'] ?? '');
        if ($sessionCsrf === '') {
            self::deny(419, 'Missing CSRF session token');
        }

        $bodyCsrf = trim((string) ($_POST['csrf_token'] ?? ''));
        if ($bodyCsrf === '' && is_array($jsonBody) && isset($jsonBody['csrf_token'])) {
            $bodyCsrf = trim((string) $jsonBody['csrf_token']);
        }
        $headerCsrf = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        $candidate = $bodyCsrf !== '' ? $bodyCsrf : $headerCsrf;
        if ($candidate === '' || !hash_equals($sessionCsrf, $candidate)) {
            self::deny(419, 'Invalid CSRF token');
        }
    }

    private static function applyRateLimits(string $routeId, int $tier): void
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $userKey = '';
        if ($tier >= self::TIER_CONTROL_VIEW && !empty($_SESSION['control_logged_in'])) {
            $userKey = strtolower(trim((string) ($_SESSION['control_username'] ?? '')));
        }

        [$ipCap, $userCap, $window] = self::rateLimitParams($tier);

        if (!ControlPlaneRateLimiter::allow('infra_rl:' . $routeId . ':ip:' . $ip, $ipCap, $window)) {
            self::deny(429, 'Rate limit exceeded');
        }
        if ($userKey !== '' && !ControlPlaneRateLimiter::allow('infra_rl:' . $routeId . ':u:' . $userKey, $userCap, $window)) {
            self::deny(429, 'Rate limit exceeded');
        }
    }

    /**
     * @return array{0:int,1:int,2:int} ip cap, user cap, window seconds
     */
    private static function rateLimitParams(int $tier): array
    {
        $w = self::envInt('RATIB_INFRA_RL_WINDOW_SECONDS', 60);
        $w = $w > 0 ? $w : 60;

        switch ($tier) {
            case self::TIER_PUBLIC_READ:
                return [
                    self::envInt('RATIB_INFRA_RL_PUBLIC_READ_IP', 240),
                    self::envInt('RATIB_INFRA_RL_PUBLIC_READ_USER', 600),
                    $w,
                ];
            case self::TIER_PUBLIC_MUTATOR:
                return [
                    self::envInt('RATIB_INFRA_RL_PUBLIC_MUTATOR_IP', 40),
                    self::envInt('RATIB_INFRA_RL_PUBLIC_MUTATOR_USER', 120),
                    $w,
                ];
            case self::TIER_CONTROL_VIEW:
                return [
                    self::envInt('RATIB_INFRA_RL_CONTROL_VIEW_IP', 120),
                    self::envInt('RATIB_INFRA_RL_CONTROL_VIEW_USER', 180),
                    $w,
                ];
            case self::TIER_CONTROL_WRITE:
                return [
                    self::envInt('RATIB_INFRA_RL_CONTROL_WRITE_IP', 60),
                    self::envInt('RATIB_INFRA_RL_CONTROL_WRITE_USER', 90),
                    $w,
                ];
            case self::TIER_CONTROL_SYSTEM:
                return [
                    self::envInt('RATIB_INFRA_RL_CONTROL_SYSTEM_IP', 40),
                    self::envInt('RATIB_INFRA_RL_CONTROL_SYSTEM_USER', 60),
                    $w,
                ];
            default:
                return [120, 180, $w];
        }
    }

    private static function envInt(string $key, int $default): int
    {
        $v = getenv($key);
        if ($v === false || $v === '') {
            return $default;
        }
        $n = (int) $v;
        return $n > 0 ? $n : $default;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private static function deny(int $code, string $message, ?array $payload = null): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
        }
        http_response_code($code);
        $out = array_merge(['ok' => false, 'message' => $message], $payload ?? []);
        echo json_encode($out, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
