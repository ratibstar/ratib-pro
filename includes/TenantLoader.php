<?php
/**
 * EN: Handles shared bootstrap/helpers/layout partial behavior in `includes/TenantLoader.php`.
 * AR: يدير سلوك الملفات المشتركة للإعدادات والمساعدات وأجزاء التخطيط في `includes/TenantLoader.php`.
 */
/**
 * TenantLoader - Multi-Tenant Country Detection
 *
 * Detects country from subdomain (e.g. sa.domain.com → Saudi Arabia)
 * and defines COUNTRY_ID constant. Must be loaded AFTER config.php (DB connection).
 *
 * Usage: require_once __DIR__ . '/TenantLoader.php';
 *        TenantLoader::init();
 *
 * Set MULTI_TENANT_ENABLED = true in config to activate.
 */
class TenantLoader
{
    /** @var string|null Subdomain/code extracted from host */
    private static $subdomain = null;

    /** @var array|null Cached country row */
    private static $country = null;

    /**
     * Initialize tenant detection. Call once after DB connection is ready.
     * Stops execution with 404 if country not found (when MULTI_TENANT_ENABLED).
     */
    public static function init(): void
    {
        if (defined('COUNTRY_ID')) {
            return; // Already loaded
        }

        if (!(defined('MULTI_TENANT_ENABLED') && MULTI_TENANT_ENABLED)) {
            define('COUNTRY_ID', 0);
            define('COUNTRY_CODE', '');
            define('COUNTRY_NAME', '');
            return;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (empty($host)) {
            self::failTenant('Invalid host');
        }

        // out.ratib.sa (main site) and localhost: use first active country
        $hostLower = strtolower($host);
        $useFallback = in_array($hostLower, ['localhost', '127.0.0.1', 'out.ratib.sa'], true)
            || strpos($hostLower, '.local') !== false;
        if ($useFallback) {
            $country = self::resolveCountryFallback();
            if ($country) {
                self::$country = $country;
                define('COUNTRY_ID', (int) $country['id']);
                define('COUNTRY_CODE', $country['code']);
                define('COUNTRY_NAME', $country['name']);
                if (session_status() === PHP_SESSION_ACTIVE) {
                    $_SESSION['country_id'] = COUNTRY_ID;
                    $_SESSION['country_code'] = COUNTRY_CODE;
                    $_SESSION['country_name'] = COUNTRY_NAME;
                }
                return;
            }
        }

        // Try: 1) Match full domain, 2) Match subdomain as code
        $country = self::resolveCountry($host);
        if (!$country) {
            self::failTenant('Country not found for host: ' . $host);
        }

        self::$country = $country;
        define('COUNTRY_ID', (int) $country['id']);
        define('COUNTRY_CODE', $country['code']);
        define('COUNTRY_NAME', $country['name']);

        // Store in session for validation on every request
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['country_id'] = COUNTRY_ID;
            $_SESSION['country_code'] = COUNTRY_CODE;
            $_SESSION['country_name'] = COUNTRY_NAME;
        }
    }

    /**
     * Resolve country from host. Tries domain match first, then subdomain = code.
     */
    private static function resolveCountry(string $host): ?array
    {
        $conn = $GLOBALS['conn'] ?? null;
        if (!$conn || !($conn instanceof mysqli)) {
            error_log('TenantLoader: No database connection');
            return null;
        }

        $host = strtolower(trim($host));
        $stmt = $conn->prepare(
            "SELECT id, name, code, domain, status FROM countries 
             WHERE status = 'active' 
             AND (domain = ? OR code = ?) 
             LIMIT 1"
        );
        if (!$stmt) {
            error_log('TenantLoader: Prepare failed - is countries table created?');
            return null;
        }

        // Try full domain match
        $stmt->bind_param('ss', $host, $host);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $stmt->close();
            return $row;
        }
        $stmt->close();

        // Try subdomain as code: sa.ratib.sa → code 'sa'
        $subdomain = self::extractSubdomain($host);
        if ($subdomain !== '') {
            $stmt = $conn->prepare(
                "SELECT id, name, code, domain, status FROM countries 
                 WHERE status = 'active' AND code = ? LIMIT 1"
            );
            if ($stmt) {
                $stmt->bind_param('s', $subdomain);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res && $row = $res->fetch_assoc()) {
                    $stmt->close();
                    return $row;
                }
                $stmt->close();
            }
        }

        return null;
    }

    /**
     * Fallback for localhost: return first active country.
     */
    private static function resolveCountryFallback(): ?array
    {
        $conn = $GLOBALS['conn'] ?? null;
        if (!$conn || !($conn instanceof mysqli)) {
            return null;
        }
        $res = @$conn->query("SELECT id, name, code, domain, status FROM countries WHERE status = 'active' ORDER BY sort_order ASC LIMIT 1");
        return ($res && $row = $res->fetch_assoc()) ? $row : null;
    }

    /**
     * Extract subdomain from host. sa.ratib.sa → sa, ae.domain.com → ae
     */
    private static function extractSubdomain(string $host): string
    {
        $parts = explode('.', $host);
        if (count($parts) >= 2) {
            $first = strtolower($parts[0]);
            if ($first !== 'www' && $first !== 'api') {
                return $first;
            }
            return count($parts) >= 3 ? strtolower($parts[1]) : '';
        }
        return '';
    }

    /**
     * Stop execution when tenant not found.
     */
    private static function failTenant(string $message): void
    {
        error_log('TenantLoader: ' . $message);
        http_response_code(404);
        if (php_sapi_name() === 'cli') {
            die('Tenant not found: ' . $message);
        }
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Not Found</title></head>';
        echo '<body><h1>404 - Country Not Found</h1><p>This subdomain is not configured.</p></body></html>';
        exit;
    }

    /**
     * Validate that session country matches current request. Call on each request.
     * Prevents manual country switching via session tampering.
     */
    public static function validateSession(): bool
    {
        if (!defined('COUNTRY_ID') || COUNTRY_ID === 0) {
            return true;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true;
        }
        $sessionCountry = (int) ($_SESSION['country_id'] ?? 0);
        if ($sessionCountry !== COUNTRY_ID) {
            $_SESSION['country_id'] = COUNTRY_ID;
            $_SESSION['country_code'] = defined('COUNTRY_CODE') ? COUNTRY_CODE : '';
            $_SESSION['country_name'] = defined('COUNTRY_NAME') ? COUNTRY_NAME : '';
        }
        return true;
    }

    /**
     * Get current country row (after init).
     */
    public static function getCountry(): ?array
    {
        return self::$country;
    }
}
