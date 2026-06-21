<?php
declare(strict_types=1);

namespace Ratib\ContactCenter\App\Core;

use PDO;

/**
 * Read-only bridge to RATEB ERP for tenant routing decisions (no ERP writes).
 */
final class ErpBridge
{
    private static ?PDO $erpPdo = null;

    public static function connection(): ?PDO
    {
        if (self::$erpPdo instanceof PDO) {
            return self::$erpPdo;
        }

        $host = getenv('RATEB_ERP_DB_HOST') ?: getenv('DB_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('RATEB_ERP_DB_PORT') ?: getenv('DB_PORT') ?: 3306);
        $name = getenv('RATEB_ERP_DB_NAME') ?: 'admin_rateb-erp';
        $user = getenv('RATEB_ERP_DB_USER') ?: getenv('DB_USER') ?: 'root';
        $pass = getenv('RATEB_ERP_DB_PASS');
        if ($pass === false) {
            $pass = getenv('DB_PASS') ?: '';
        }

        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
            self::$erpPdo = new PDO($dsn, (string) $user, (string) $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return self::$erpPdo;
        } catch (\PDOException $e) {
            error_log('RCC ErpBridge unavailable: ' . $e->getMessage());
            return null;
        }
    }

    /** @return array<string, mixed>|null */
    public static function companyById(int $erpCompanyId): ?array
    {
        $pdo = self::connection();
        if ($pdo === null || $erpCompanyId < 1) {
            return null;
        }
        $stmt = $pdo->prepare(
            'SELECT id, name, slug, email, phone, locale, settings
             FROM rateb_companies WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $erpCompanyId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public static function resolveLocaleForCompany(int $erpCompanyId, string $fallback = 'ar'): string
    {
        $company = self::companyById($erpCompanyId);
        if ($company === null) {
            return $fallback;
        }
        if (!empty($company['locale']) && in_array($company['locale'], ['en', 'ar'], true)) {
            return (string) $company['locale'];
        }
        $settings = $company['settings'] ?? null;
        if (is_string($settings)) {
            $decoded = json_decode($settings, true);
            if (is_array($decoded) && !empty($decoded['locale'])) {
                return in_array($decoded['locale'], ['en', 'ar'], true) ? (string) $decoded['locale'] : $fallback;
            }
        }
        return $fallback;
    }
}
