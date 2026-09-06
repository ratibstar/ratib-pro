<?php
declare(strict_types=1);

/**
 * Pinned ERP tenant branding (dedicated hosts).
 * Shown on login + Admin shell without requiring a DB upload.
 */

if (!function_exists('rateb_erp_brand_catalog')) {
    /**
     * @return array<string, array{hosts:list<string>,db_names:list<string>,name:string,name_ar:string,logo:string,primary:string,accent:string}>
     */
    function rateb_erp_brand_catalog(): array
    {
        return [
            'alarfaj' => [
                'hosts' => ['alarfaj.rateb.sa'],
                'db_names' => ['admin_rateb_erp_alarfaj'],
                'name' => 'AL-ARFAJ MEDICAL SERVICES',
                'name_ar' => 'العرفج للخدمات الطبية',
                'logo' => 'branding/alarfaj-logo.png',
                'primary' => '#1A2B44',
                'accent' => '#C41E24',
            ],
        ];
    }
}

if (!function_exists('rateb_erp_brand_request_host')) {
    function rateb_erp_brand_request_host(): string
    {
        $host = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return $host;
    }
}

if (!function_exists('rateb_erp_brand_hydrate')) {
    /**
     * @param array{hosts:list<string>,db_names:list<string>,name:string,name_ar:string,logo:string,primary:string,accent:string} $row
     * @return array{key:string,name:string,name_ar:string,logo_url:string,logo_path:string,primary:string,accent:string}|null
     */
    function rateb_erp_brand_hydrate(string $key, array $row): ?array
    {
        $rel = ltrim((string) ($row['logo'] ?? ''), '/');
        if ($rel === '') {
            return null;
        }
        $disk = (defined('RATEB_ROOT') ? RATEB_ROOT : dirname(__DIR__)) . '/public/assets/' . $rel;
        if (!is_file($disk)) {
            return null;
        }

        return [
            'key' => $key,
            'name' => (string) ($row['name'] ?? ''),
            'name_ar' => (string) ($row['name_ar'] ?? ''),
            'logo_url' => function_exists('rateb_asset') ? rateb_asset($rel) : ('/assets/' . $rel),
            'logo_path' => 'assets/' . $rel,
            'primary' => (string) ($row['primary'] ?? ''),
            'accent' => (string) ($row['accent'] ?? ''),
        ];
    }
}

if (!function_exists('rateb_erp_brand_for_context')) {
    /**
     * @return array{key:string,name:string,name_ar:string,logo_url:string,logo_path:string,primary:string,accent:string}|null
     */
    function rateb_erp_brand_for_context(?string $host = null, ?string $dbName = null, ?string $companyName = null, ?string $siteUrl = null): ?array
    {
        $host = strtolower(trim((string) ($host ?? rateb_erp_brand_request_host())));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;
        $dbName = strtolower(trim((string) ($dbName ?? (defined('RATEB_ERP_DB_NAME') ? (string) RATEB_ERP_DB_NAME : ''))));
        $companyName = trim((string) ($companyName ?? ''));
        $siteUrl = strtolower(trim((string) ($siteUrl ?? (defined('SITE_URL') ? (string) SITE_URL : ''))));

        foreach (rateb_erp_brand_catalog() as $key => $row) {
            $hit = false;
            foreach ($row['hosts'] as $pinnedHost) {
                $pinnedHost = strtolower(trim((string) $pinnedHost));
                if ($pinnedHost === '') {
                    continue;
                }
                $sub = (string) (explode('.', $pinnedHost, 2)[0] ?? '');
                if ($host === $pinnedHost
                    || ($pinnedHost !== '' && str_ends_with($host, '.' . $pinnedHost))
                    || ($sub !== '' && str_starts_with($host, $sub . '.'))
                ) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit && $dbName !== '') {
                foreach ($row['db_names'] as $pinnedDb) {
                    if ($dbName === strtolower(trim((string) $pinnedDb))) {
                        $hit = true;
                        break;
                    }
                }
            }
            if (!$hit && $siteUrl !== '') {
                foreach ($row['hosts'] as $pinnedHost) {
                    if (str_contains($siteUrl, strtolower((string) $pinnedHost))) {
                        $hit = true;
                        break;
                    }
                }
            }
            if (!$hit && $companyName !== '' && preg_match('/arfaj|عرفج/iu', $companyName) === 1 && $key === 'alarfaj') {
                $hit = true;
            }
            if (!$hit) {
                continue;
            }
            $brand = rateb_erp_brand_hydrate($key, $row);
            if ($brand !== null) {
                return $brand;
            }
        }

        return null;
    }
}

if (!function_exists('rateb_erp_pinned_brand')) {
    /**
     * @return array{key:string,name:string,name_ar:string,logo_url:string,logo_path:string,primary:string,accent:string}|null
     */
    function rateb_erp_pinned_brand(): ?array
    {
        static $resolved = false;
        static $brand = null;
        if ($resolved) {
            return $brand;
        }
        $resolved = true;
        $brand = rateb_erp_brand_for_context();

        return $brand;
    }
}

if (!function_exists('rateb_erp_brand_display_name')) {
    function rateb_erp_brand_display_name(): string
    {
        $brand = rateb_erp_pinned_brand();
        if (!is_array($brand)) {
            return function_exists('__') ? __('rateb_erp') : 'RATEB ERP';
        }
        $ar = trim((string) ($brand['name_ar'] ?? ''));
        $en = trim((string) ($brand['name'] ?? ''));
        if (function_exists('rateb_locale') && rateb_locale() === 'ar' && $ar !== '') {
            return $ar;
        }

        return $en !== '' ? $en : (function_exists('__') ? __('rateb_erp') : 'RATEB ERP');
    }
}

if (!function_exists('rateb_erp_brand_logo_url')) {
    function rateb_erp_brand_logo_url(): string
    {
        $brand = rateb_erp_pinned_brand();

        return is_array($brand) ? trim((string) ($brand['logo_url'] ?? '')) : '';
    }
}

if (!function_exists('rateb_erp_brand_favicon_url')) {
    function rateb_erp_brand_favicon_url(): string
    {
        $logo = rateb_erp_brand_logo_url();
        if ($logo !== '') {
            return $logo;
        }

        return function_exists('rateb_public_url') ? rateb_public_url('favicon.ico') : '/favicon.ico';
    }
}
