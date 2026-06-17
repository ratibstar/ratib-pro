<?php
declare(strict_types=1);

/**
 * Control Panel i18n — scoped to control-panel/ only (does not affect main site or rateb-erp).
 */

if (!function_exists('cp_supported_locales')) {
    function cp_supported_locales(): array
    {
        return ['en', 'ar'];
    }
}

if (!function_exists('cp_init_locale')) {
    function cp_init_locale(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        if (isset($_GET['lang'])) {
            $lang = strtolower(trim((string) $_GET['lang']));
            if (in_array($lang, cp_supported_locales(), true)) {
                $_SESSION['control_locale'] = $lang;
                $secure = function_exists('control_request_is_https') ? control_request_is_https() : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                if (PHP_VERSION_ID >= 70300) {
                    setcookie('rateb_control_lang', $lang, [
                        'expires' => time() + 86400 * 365,
                        'path' => '/',
                        'secure' => $secure,
                        'httponly' => false,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    setcookie('rateb_control_lang', $lang, time() + 86400 * 365, '/', '', $secure, false);
                }
            }
        } elseif (empty($_SESSION['control_locale']) && !empty($_COOKIE['rateb_control_lang'])) {
            $cookieLang = strtolower(trim((string) $_COOKIE['rateb_control_lang']));
            if (in_array($cookieLang, cp_supported_locales(), true)) {
                $_SESSION['control_locale'] = $cookieLang;
            }
        }

        if (empty($_SESSION['control_locale']) || !in_array((string) $_SESSION['control_locale'], cp_supported_locales(), true)) {
            $_SESSION['control_locale'] = 'en';
        }
    }
}

if (!function_exists('cp_locale')) {
    function cp_locale(): string
    {
        $locale = (string) ($_SESSION['control_locale'] ?? 'en');
        return in_array($locale, cp_supported_locales(), true) ? $locale : 'en';
    }
}

if (!function_exists('cp_is_rtl')) {
    function cp_is_rtl(): bool
    {
        return cp_locale() === 'ar';
    }
}

if (!function_exists('cp_html_lang')) {
    function cp_html_lang(): string
    {
        return cp_locale();
    }
}

if (!function_exists('cp_html_dir')) {
    function cp_html_dir(): string
    {
        return cp_is_rtl() ? 'rtl' : 'ltr';
    }
}

if (!function_exists('cp_load_translations')) {
    function cp_load_translations(string $locale): array
    {
        static $cache = [];
        if (isset($cache[$locale])) {
            return $cache[$locale];
        }
        $file = dirname(__DIR__) . '/lang/' . $locale . '.php';
        if (!is_file($file)) {
            $cache[$locale] = [];
            return [];
        }
        $data = require $file;
        $cache[$locale] = is_array($data) ? $data : [];
        return $cache[$locale];
    }
}

if (!function_exists('cp_t')) {
    /**
     * @param array<string, string|int|float> $replacements
     */
    function cp_t(string $key, array $replacements = []): string
    {
        $locale = cp_locale();
        $strings = cp_load_translations($locale);
        $text = $strings[$key] ?? null;
        if ($text === null && $locale !== 'en') {
            $en = cp_load_translations('en');
            $text = $en[$key] ?? $key;
        } elseif ($text === null) {
            $text = $key;
        }
        foreach ($replacements as $name => $value) {
            $text = str_replace('{' . $name . '}', (string) $value, $text);
        }
        return $text;
    }
}

if (!function_exists('cp_lang_switch_url')) {
    function cp_lang_switch_url(string $locale): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $parts = parse_url($uri);
        $path = $parts['path'] ?? '/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['lang'] = $locale;
        $qs = http_build_query($query);
        return $path . ($qs !== '' ? '?' . $qs : '');
    }
}

if (!function_exists('cp_js_translations')) {
    function cp_js_translations(): array
    {
        $all = cp_load_translations(cp_locale());
        $js = [];
        foreach ($all as $key => $value) {
            if (strpos($key, 'js.') === 0 || strpos($key, 'common.') === 0) {
                $js[$key] = $value;
            }
        }
        return $js;
    }
}

if (!function_exists('cp_i18n_inline_script')) {
    function cp_i18n_inline_script(): string
    {
        $payload = json_encode([
            'locale' => cp_locale(),
            'dir' => cp_html_dir(),
            'strings' => cp_js_translations(),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($payload === false) {
            $payload = '{}';
        }
        return '<script>window.__CP_I18N=' . $payload . ';</script>';
    }
}

if (!function_exists('cp_translate_page_title')) {
    function cp_translate_page_title(string $title): string
    {
        static $map = [
            'Control Panel' => 'meta.control_panel',
            'Select Country' => 'select_country.title',
            'Help center' => 'page.help_center',
            'HR Management' => 'page.hr_management',
            'System Settings' => 'page.system_settings',
            'SOC Dashboard' => 'page.soc_dashboard',
            'Super Admin - All Countries Login' => 'page.super_admin_tenants',
            'Tracking Map' => 'page.tracking_map',
            'Control Admins' => 'page.control_admins',
            'Accounting' => 'page.accounting',
            'Telemetry Health' => 'page.telemetry_health',
            'Worker Mobile Onboarding' => 'page.tracking_onboarding',
            'Country program' => 'page.country_program',
            'Government Control' => 'page.government_control',
            'Public site content — full site' => 'page.public_site_content',
            'Support Chats' => 'page.support_chats',
            'Control Panel Settings' => 'page.panel_settings',
            'Manage Agencies' => 'page.manage_agencies',
            'Country Profiles' => 'page.country_profiles',
            'Control hub' => 'page.control_hub',
            'Country Users' => 'page.country_users',
            'Registration Requests' => 'page.registration_requests',
            'Manage Countries' => 'page.manage_countries',
            'Control Panel Users' => 'page.panel_users',
        ];
        if (isset($map[$title])) {
            return cp_t($map[$title]);
        }
        return $title;
    }
}
