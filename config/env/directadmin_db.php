<?php
/**
 * DirectAdmin MySQL naming — admin_rateb, admin_bangladesh, admin_rateb-erp, etc.
 * Override via .env: DB_*, RATEB_DB_PREFIX, RATEB_PRO_DB_NAME, RATEB_ERP_DB_NAME.
 */
declare(strict_types=1);

if (!function_exists('rateb_env')) {
    function rateb_env(string $key): string
    {
        $p = getenv($key);
        return ($p !== false && $p !== '') ? (string) $p : '';
    }
}

if (!function_exists('rateb_db_prefix')) {
    function rateb_db_prefix(): string
    {
        $p = rateb_env('RATEB_DB_PREFIX');
        return $p !== '' ? $p : 'admin';
    }
}

if (!function_exists('rateb_default_mysql_user')) {
    function rateb_default_mysql_user(): string
    {
        // DirectAdmin: dedicated MySQL user admin_rateb (not Linux account admin).
        return rateb_db_prefix() . '_rateb';
    }
}

if (!function_exists('rateb_main_pro_database')) {
    function rateb_main_pro_database(): string
    {
        $fromEnv = rateb_env('RATEB_PRO_DB_NAME');
        if ($fromEnv !== '') {
            return $fromEnv;
        }
        $db = getenv('DB_NAME');
        if ($db !== false && $db !== '') {
            return (string) $db;
        }
        return rateb_db_prefix() . '_rateb';
    }
}

if (!function_exists('rateb_control_panel_database')) {
    /**
     * Master control DB: admins, countries, agencies, CMS (rateb_site_content).
     * Separate from RATEB Pro orders DB (admin_rateb) and per-country tenant DBs (admin_bangladesh, …).
     */
    function rateb_control_panel_database(): string
    {
        $fromEnv = getenv('CONTROL_PANEL_DB_NAME');
        if ($fromEnv !== false && $fromEnv !== '') {
            return (string) $fromEnv;
        }
        return rateb_db_prefix() . '_control_panel_db';
    }
}

if (!function_exists('rateb_erp_database_name')) {
    function rateb_erp_database_name(): string
    {
        $fromEnv = getenv('RATEB_ERP_DB_NAME');
        if ($fromEnv !== false && $fromEnv !== '') {
            return (string) $fromEnv;
        }
        return rateb_db_prefix() . '_rateb-erp';
    }
}

if (!function_exists('rateb_country_database_candidates')) {
    /**
     * @return list<string>
     */
    function rateb_country_database_candidates(string $slug): array
    {
        $slug = strtolower(trim(str_replace([' ', '-'], '_', $slug)));
        $prefix = rateb_db_prefix();
        $out = [];
        $add = static function (string $name) use (&$out): void {
            if ($name !== '' && !in_array($name, $out, true)) {
                $out[] = $name;
            }
        };

        if ($slug === 'bangladesh' || $slug === 'bangladish') {
            $add($prefix . '_bangladesh');
            $add($prefix . '_bangladish');
            return $out;
        }
        if ($slug === 'sri_lanka' || $slug === 'sri lanka') {
            $add($prefix . '_sri_lanka');
            return $out;
        }
        if ($slug === 'thailand' || $slug === 'thalland') {
            $add($prefix . '_thailand');
            $add($prefix . '_thalland');
            return $out;
        }

        if ($slug !== '') {
            $add($prefix . '_' . $slug);
        }
        return $out;
    }
}

if (!function_exists('rateb_all_country_database_names')) {
    /**
     * @return list<string>
     */
    function rateb_all_country_database_names(): array
    {
        $countries = [
            'bangladesh',
            'ethiopia',
            'indonesia',
            'kenya',
            'nepal',
            'nigeria',
            'philippines',
            'rwanda',
            'sri_lanka',
            'thailand',
            'uganda',
        ];
        $out = [];
        foreach ($countries as $country) {
            foreach (rateb_country_database_candidates($country) as $name) {
                if (!in_array($name, $out, true)) {
                    $out[] = $name;
                }
            }
        }
        return $out;
    }
}
