<?php
/**
 * DirectAdmin MySQL naming — admin_rateb, admin_bangladesh, admin_rateb-erp, etc.
 * Override any value via .env (DB_*, CONTROL_PANEL_DB_NAME, RATEB_ERP_DB_NAME, RATIB_DB_PREFIX).
 */
declare(strict_types=1);

if (!function_exists('ratib_db_prefix')) {
    function ratib_db_prefix(): string
    {
        $p = getenv('RATIB_DB_PREFIX');
        return ($p !== false && $p !== '') ? (string) $p : 'admin';
    }
}

if (!function_exists('ratib_default_mysql_user')) {
    function ratib_default_mysql_user(): string
    {
        return 'admin';
    }
}

if (!function_exists('ratib_main_pro_database')) {
    function ratib_main_pro_database(): string
    {
        $fromEnv = getenv('RATIB_PRO_DB_NAME');
        if ($fromEnv !== false && $fromEnv !== '') {
            return (string) $fromEnv;
        }
        $db = getenv('DB_NAME');
        if ($db !== false && $db !== '') {
            return (string) $db;
        }
        return ratib_db_prefix() . '_rateb';
    }
}

if (!function_exists('ratib_control_panel_database')) {
    function ratib_control_panel_database(): string
    {
        $fromEnv = getenv('CONTROL_PANEL_DB_NAME');
        if ($fromEnv !== false && $fromEnv !== '') {
            return (string) $fromEnv;
        }
        return ratib_main_pro_database();
    }
}

if (!function_exists('ratib_erp_database_name')) {
    function ratib_erp_database_name(): string
    {
        $fromEnv = getenv('RATEB_ERP_DB_NAME');
        if ($fromEnv !== false && $fromEnv !== '') {
            return (string) $fromEnv;
        }
        return ratib_db_prefix() . '_rateb-erp';
    }
}

if (!function_exists('ratib_country_database_candidates')) {
    /**
     * @return list<string>
     */
    function ratib_country_database_candidates(string $slug): array
    {
        $slug = strtolower(trim(str_replace([' ', '-'], '_', $slug)));
        $prefix = ratib_db_prefix();
        $out = [];
        $add = static function (string $name) use (&$out): void {
            if ($name !== '' && !in_array($name, $out, true)) {
                $out[] = $name;
            }
        };

        if ($slug === 'bangladesh' || $slug === 'bangladish') {
            $add($prefix . '_bangladesh');
            $add($prefix . '_bangladish');
            $add('outratib_bangladesh');
            $add('outratib_bangladish');
            return $out;
        }
        if ($slug === 'sri_lanka' || $slug === 'sri lanka') {
            $add($prefix . '_sri_lanka');
            $add('outratib_sri_lanka');
            $add('outratib_sri Lanka');
            return $out;
        }
        if ($slug === 'thailand' || $slug === 'thalland') {
            $add($prefix . '_thailand');
            $add($prefix . '_thalland');
            $add('outratib_thailand');
            $add('outratib_thalland');
            return $out;
        }

        if ($slug !== '') {
            $add($prefix . '_' . $slug);
            $add('outratib_' . $slug);
        }
        return $out;
    }
}

if (!function_exists('ratib_all_country_database_names')) {
    /**
     * @return list<string>
     */
    function ratib_all_country_database_names(): array
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
            foreach (ratib_country_database_candidates($country) as $name) {
                if (!in_array($name, $out, true)) {
                    $out[] = $name;
                }
            }
        }
        return $out;
    }
}
