<?php
declare(strict_types=1);

namespace Rateb\App\Services;

use PDOException;

final class DatabaseErrorService
{
    public static function userMessage(\Throwable $e): string
    {
        if ($e instanceof \RuntimeException && !($e->getPrevious() instanceof PDOException)) {
            $msg = trim($e->getMessage());
            if ($msg !== '' && !self::looksLikePdoMessage($msg)) {
                return $msg;
            }
        }

        $raw = $e->getMessage();
        if ($e->getPrevious() instanceof \Throwable) {
            $raw .= ' ' . $e->getPrevious()->getMessage();
        }

        if (self::isMissingColumn($raw)) {
            $detail = self::missingColumnDetail($raw);
            return $detail !== ''
                ? self::t('db_schema_outdated') . ' [' . $detail . ']'
                : self::t('db_schema_outdated');
        }
        if (self::isMissingTable($raw)) {
            $detail = self::missingTableDetail($raw);
            return $detail !== ''
                ? self::t('db_schema_outdated') . ' [' . $detail . ']'
                : self::t('db_schema_outdated');
        }
        if (self::isCompanyFkViolation($raw)) {
            return self::t('company_not_found_ops');
        }
        if (self::isFkViolation($raw)) {
            return self::t('db_fk_violation');
        }
        if (self::isDuplicateEntry($raw)) {
            if (stripos($raw, 'uk_attendance_day') !== false) {
                return self::t('hr_attendance_duplicate_day');
            }
            return self::t('db_duplicate_record');
        }
        if (self::isNotNullViolation($raw)) {
            return self::t('form_required_fields');
        }
        if (self::isDbAccessDenied($raw)) {
            return self::t('db_access_denied');
        }
        if (self::isDataTruncation($raw)) {
            return self::t('db_schema_outdated') . ' [enum]';
        }
        if ($e instanceof PDOException || $e->getPrevious() instanceof PDOException) {
            return self::t('db_operation_failed');
        }
        if (self::looksLikePdoMessage($raw)) {
            return self::t('db_operation_failed');
        }

        return self::t('system_error_generic');
    }

    public static function isSchemaIssue(\Throwable $e): bool
    {
        $raw = self::rawMessage($e);
        if (self::isMissingColumn($raw) || self::isMissingTable($raw) || self::isDataTruncation($raw)) {
            return true;
        }
        $cur = $e;
        while ($cur instanceof \Throwable) {
            $msg = $cur->getMessage();
            if (self::isMissingColumn($msg) || self::isMissingTable($msg) || self::isDataTruncation($msg)) {
                return true;
            }
            $cur = $cur->getPrevious();
        }
        return false;
    }

    public static function isCompanyFkIssue(\Throwable $e): bool
    {
        return self::isCompanyFkViolation(self::rawMessage($e));
    }

    public static function technicalDetail(\Throwable $e): string
    {
        $parts = [];
        $cur = $e;
        while ($cur instanceof \Throwable) {
            $msg = trim($cur->getMessage());
            if ($msg !== '' && (self::looksLikePdoMessage($msg) || !self::looksLikeUserFacingMessage($msg))) {
                $parts[] = $msg;
            }
            $cur = $cur->getPrevious();
        }
        return implode(' | ', array_values(array_unique($parts)));
    }

    public static function toRuntimeException(PDOException $e): \RuntimeException
    {
        return new \RuntimeException(self::userMessage($e), (int) $e->getCode(), $e);
    }

    public static function renderHttpError(\Throwable $e, int $status = 500): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }

        $message = self::userMessage($e);
        $schema = self::isSchemaIssue($e);
        $company = self::isCompanyFkIssue($e);
        $locale = function_exists('rateb_locale') ? rateb_locale() : 'ar';
        $dir = $locale === 'ar' ? 'rtl' : 'ltr';
        $title = self::t('db_error_title');
        if (function_exists('rateb_asset')) {
            $varsCss = rateb_asset('css/variables.css');
            $lightCss = rateb_asset('css/light.css');
        } else {
            $assetBase = defined('RATEB_CP_ASSETS_URL')
                ? (string) RATEB_CP_ASSETS_URL
                : '/rateb-erp/public/assets';
            $varsCss = $assetBase . '/css/variables.css';
            $lightCss = $assetBase . '/css/light.css';
        }
        $homeUrl = function_exists('rateb_url') ? rateb_url('admin') : '/rateb-erp/public/admin';
        $migrateUrl = self::resolveMigrateUrl();
        $agencyMigrateHint = '';
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            $agencyMigrateHint = self::t('agency_erp_migrate_from_platform');
        }
        $companiesUrl = function_exists('rateb_url') ? rateb_url('admin/companies') : '/rateb-erp/public/admin/companies';

        echo '<!DOCTYPE html><html lang="' . htmlspecialchars($locale, ENT_QUOTES, 'UTF-8') . '" dir="' . $dir . '" data-theme="light">';
        echo '<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</title>';
        echo '<link href="' . htmlspecialchars($varsCss, ENT_QUOTES, 'UTF-8') . '" rel="stylesheet">';
        echo '<link href="' . htmlspecialchars($lightCss, ENT_QUOTES, 'UTF-8') . '" rel="stylesheet">';
        echo '<style>body{font-family:Tajawal,system-ui,sans-serif;background:#e8f0fa;color:#1a3354;margin:0;padding:2rem}.rateb-err{max-width:640px;margin:2rem auto;padding:1.5rem;background:#fff;border:1px solid #c5d9f0;border-radius:12px;box-shadow:0 4px 24px rgba(26,51,84,.08)}.rateb-err h1{font-size:1.25rem;margin:0 0 1rem}.rateb-err p{margin:0 0 1rem;line-height:1.6}.rateb-err .actions{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1.25rem}</style>';
        echo '</head><body class="rateb-app"><div class="rateb-err">';
        echo '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        if ($agencyMigrateHint !== '') {
            echo '<p class="small text-muted">' . htmlspecialchars($agencyMigrateHint, ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $tech = self::technicalDetail($e);
        if ($tech !== '' && function_exists('rateb_is_super_admin') && rateb_is_super_admin()) {
            echo '<p style="font-size:.85rem;color:#5a6a7e;margin-top:.75rem"><code style="white-space:pre-wrap">'
                . htmlspecialchars($tech, ENT_QUOTES, 'UTF-8') . '</code></p>';
        }
        if ($schema && function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()
            && class_exists(\Rateb\App\Core\Database::class)) {
            try {
                $activeDb = \Rateb\App\Core\Database::resolvedDatabaseName();
                if ($activeDb !== '') {
                    echo '<p class="small text-muted mb-0">'
                        . htmlspecialchars(self::t('db_error_active_database') . ': ' . $activeDb, ENT_QUOTES, 'UTF-8')
                        . '</p>';
                }
            } catch (\Throwable $ignored) {
            }
        }
        echo '<div class="actions">';
        echo '<a class="btn btn-primary btn-sm" href="' . htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(self::t('dashboard'), ENT_QUOTES, 'UTF-8') . '</a>';
        if ($schema && $migrateUrl !== '') {
            echo '<a class="btn btn-outline-primary btn-sm" href="' . htmlspecialchars($migrateUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(self::t('run_erp_migrations'), ENT_QUOTES, 'UTF-8') . '</a>';
        } elseif (!$schema && $migrateUrl !== '' && ($e instanceof PDOException || $e->getPrevious() instanceof PDOException || self::looksLikePdoMessage(self::rawMessage($e)))) {
            echo '<a class="btn btn-outline-primary btn-sm" href="' . htmlspecialchars($migrateUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(self::t('run_erp_migrations'), ENT_QUOTES, 'UTF-8') . '</a>';
        }
        if ($company) {
            echo '<a class="btn btn-outline-secondary btn-sm" href="' . htmlspecialchars($companiesUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars(self::t('companies'), ENT_QUOTES, 'UTF-8') . '</a>';
        }
        echo '</div></div></body></html>';
    }

    private static function rawMessage(\Throwable $e): string
    {
        $parts = [$e->getMessage()];
        $prev = $e->getPrevious();
        while ($prev instanceof \Throwable) {
            $parts[] = $prev->getMessage();
            $prev = $prev->getPrevious();
        }
        return implode(' ', $parts);
    }

    private static function isMissingColumn(string $raw): bool
    {
        return strpos($raw, '42S22') !== false
            || strpos($raw, '1054') !== false
            || stripos($raw, 'Unknown column') !== false;
    }

    private static function missingColumnDetail(string $raw): string
    {
        if (preg_match("/Unknown column '([^']+)'/i", $raw, $m)) {
            return (string) $m[1];
        }
        if (preg_match('/1054[^\'"]*\'([^\']+)\'/i', $raw, $m)) {
            return (string) $m[1];
        }
        return '';
    }

    private static function missingTableDetail(string $raw): string
    {
        if (preg_match("/Table '([^']+)' doesn't exist/i", $raw, $m)) {
            return (string) $m[1];
        }
        return '';
    }

    private static function isMissingTable(string $raw): bool
    {
        return strpos($raw, '42S02') !== false
            || strpos($raw, '1146') !== false
            || (stripos($raw, "doesn't exist") !== false && stripos($raw, 'Table') !== false);
    }

    private static function isFkViolation(string $raw): bool
    {
        return strpos($raw, '23000') !== false
            || strpos($raw, '1452') !== false
            || stripos($raw, 'foreign key constraint') !== false;
    }

    private static function isDuplicateEntry(string $raw): bool
    {
        return strpos($raw, '1062') !== false
            || stripos($raw, 'Duplicate entry') !== false;
    }

    private static function isNotNullViolation(string $raw): bool
    {
        return strpos($raw, '1048') !== false
            || stripos($raw, 'cannot be null') !== false;
    }

    private static function isCompanyFkViolation(string $raw): bool
    {
        if (!self::isFkViolation($raw)) {
            return false;
        }
        return stripos($raw, 'rateb_companies') !== false
            || stripos($raw, 'fk_pc_company') !== false
            || stripos($raw, 'company_id') !== false;
    }

    private static function isDataTruncation(string $raw): bool
    {
        return strpos($raw, '1265') !== false
            || strpos($raw, '1366') !== false
            || stripos($raw, 'Data truncated') !== false
            || stripos($raw, 'Incorrect enum value') !== false
            || stripos($raw, 'truncated incorrect') !== false;
    }

    private static function looksLikeUserFacingMessage(string $msg): bool
    {
        $generic = self::t('db_operation_failed');
        $schema = self::t('db_schema_outdated');
        return $msg === $generic || $msg === $schema || str_starts_with($msg, $schema);
    }

    private static function isDbAccessDenied(string $raw): bool
    {
        return strpos($raw, '1044') !== false
            || strpos($raw, '1045') !== false
            || strpos($raw, '1049') !== false
            || stripos($raw, 'Access denied') !== false;
    }

    private static function looksLikePdoMessage(string $raw): bool
    {
        return stripos($raw, 'SQLSTATE[') !== false
            || preg_match('/\b(10\d{2}|42\S{3})\b/', $raw) === 1;
    }

    private static function t(string $key): string
    {
        return function_exists('__') ? (string) __($key) : $key;
    }

    private static function resolveMigrateUrl(): string
    {
        if (function_exists('rateb_is_agency_erp_host') && rateb_is_agency_erp_host()) {
            if (function_exists('rateb_platform_oversight_public_url')) {
                return rateb_platform_oversight_public_url('admin/agency-updates');
            }

            return 'https://rateb.sa/rateb-erp/public/admin/agency-updates';
        }
        if (function_exists('control_rateb_erp_migrate_page_url')) {
            return control_rateb_erp_migrate_page_url();
        }
        if (function_exists('rateb_url')) {
            return rateb_url('../control-panel/pages/control/rateb-erp-migrate.php');
        }

        return '';
    }
}
