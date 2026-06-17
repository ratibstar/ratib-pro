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
        $baseDir = dirname(__DIR__) . '/lang';
        $merged = [];
        $main = $baseDir . '/' . $locale . '.php';
        if (is_file($main)) {
            $data = require $main;
            if (is_array($data)) {
                $merged = $data;
            }
        }
        $modules = $baseDir . '/modules/' . $locale . '.php';
        if (is_file($modules)) {
            $mod = require $modules;
            if (is_array($mod)) {
                $merged = array_merge($merged, $mod);
            }
        }
        $extra = $baseDir . '/modules/government.php';
        if ($locale === 'en' && is_file($extra)) {
            $g = require $extra;
            if (is_array($g)) {
                $merged = array_merge($merged, $g);
            }
        }
        $extraAr = $baseDir . '/modules/government-ar.php';
        if ($locale === 'ar' && is_file($extraAr)) {
            $g = require $extraAr;
            if (is_array($g)) {
                $merged = array_merge($merged, $g);
            }
        }
        $hub = $baseDir . '/modules/hub.php';
        if ($locale === 'en' && is_file($hub)) {
            $h = require $hub;
            if (is_array($h)) {
                $merged = array_merge($merged, $h);
            }
        }
        $hubAr = $baseDir . '/modules/hub-ar.php';
        if ($locale === 'ar' && is_file($hubAr)) {
            $h = require $hubAr;
            if (is_array($h)) {
                $merged = array_merge($merged, $h);
            }
        }
        $cache[$locale] = $merged;
        return $cache[$locale];
    }
}

if (!function_exists('cp_phrase_map')) {
    /** @return array<string, string> English phrase => Arabic */
    function cp_phrase_map(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $map = [];
        $phraseFile = dirname(__DIR__) . '/lang/phrases/ar.php';
        if (is_file($phraseFile)) {
            $phrases = require $phraseFile;
            if (is_array($phrases)) {
                $map = $phrases;
            }
        }
        // Also map all English translation values to Arabic keys
        $en = cp_load_translations('en');
        $ar = cp_load_translations('ar');
        foreach ($en as $key => $enVal) {
            if (!is_string($enVal) || $enVal === '') {
                continue;
            }
            $arVal = $ar[$key] ?? null;
            if (is_string($arVal) && $arVal !== '' && $arVal !== $enVal) {
                $map[$enVal] = $arVal;
            }
        }
        // Longest phrases first to avoid partial replacements
        uksort($map, static function (string $a, string $b): int {
            return strlen($b) <=> strlen($a);
        });
        return $map;
    }
}

if (!function_exists('cp_translate_html')) {
    function cp_translate_html(string $html): string
    {
        if (cp_locale() !== 'ar' || $html === '') {
            return $html;
        }
        if (preg_match('/^\s*[\{\[]/', $html)) {
            return $html;
        }
        $map = cp_phrase_map();
        if ($map === []) {
            return $html;
        }
        $placeholders = [];
        $i = 0;
        $protected = preg_replace_callback(
            '/<(script|style|pre|code|textarea|svg)(\s[^>]*)?>.*?<\/\1>/is',
            static function (array $m) use (&$placeholders, &$i): string {
                $key = '%%CP_PROTECT_' . ($i++) . '%%';
                $placeholders[$key] = $m[0];
                return $key;
            },
            $html
        );
        if (!is_string($protected)) {
            return $html;
        }
        // Protect lang=en blocks (dates, amounts)
        $protected = preg_replace_callback(
            '/<[^>]+lang=["\']en["\'][^>]*>.*?<\/[^>]+>/is',
            static function (array $m) use (&$placeholders, &$i): string {
                $key = '%%CP_PROTECT_' . ($i++) . '%%';
                $placeholders[$key] = $m[0];
                return $key;
            },
            $protected
        );
        $protected = preg_replace_callback(
            '/<[^>]+translate=["\']no["\'][^>]*>.*?<\/[^>]+>/is',
            static function (array $m) use (&$placeholders, &$i): string {
                $key = '%%CP_PROTECT_' . ($i++) . '%%';
                $placeholders[$key] = $m[0];
                return $key;
            },
            $protected
        );
        $protected = preg_replace_callback(
            '/<!--CP_MODULE_START-->[\s\S]*?<!--CP_MODULE_END-->/',
            static function (array $m) use (&$placeholders, &$i): string {
                $key = '%%CP_PROTECT_' . ($i++) . '%%';
                $placeholders[$key] = $m[0];
                return $key;
            },
            $protected
        );
        $protected = preg_replace_callback(
            '/<[^>]+data-cp-no-i18n[^>]*>[\s\S]*?<\/div>/i',
            static function (array $m) use (&$placeholders, &$i): string {
                $key = '%%CP_PROTECT_' . ($i++) . '%%';
                $placeholders[$key] = $m[0];
                return $key;
            },
            $protected
        );
        foreach ($map as $en => $ar) {
            if ($en === '' || $en === $ar) {
                continue;
            }
            $protected = str_replace($en, $ar, $protected);
        }
        if ($placeholders !== []) {
            $protected = str_replace(array_keys($placeholders), array_values($placeholders), $protected);
        }
        return $protected;
    }
}

if (!function_exists('cp_translate_output_buffer')) {
    function cp_translate_output_buffer(string $html): string
    {
        return cp_translate_html($html);
    }
}

if (!function_exists('cp_ob_translate_should_run')) {
    function cp_ob_translate_should_run(): bool
    {
        if (cp_locale() !== 'ar') {
            return false;
        }
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        if (strpos($script, '/api/') !== false) {
            return false;
        }
        if (strpos($script, 'cp-ping.php') !== false) {
            return false;
        }
        return true;
    }
}

if (!function_exists('cp_ob_translate_start')) {
    function cp_ob_translate_start(): void
    {
        static $started = false;
        if ($started || !cp_ob_translate_should_run() || headers_sent()) {
            return;
        }
        $started = true;
        ob_start('cp_translate_output_buffer');
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

if (!function_exists('cp_e')) {
    function cp_e(string $key, array $replacements = []): void
    {
        echo htmlspecialchars(cp_t($key, $replacements), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cp_js_translations')) {
    function cp_js_translations(): array
    {
        return cp_load_translations(cp_locale());
    }
}

if (!function_exists('cp_i18n_inline_script')) {
    function cp_i18n_inline_script(): string
    {
        $phraseMap = cp_locale() === 'ar' ? cp_phrase_map() : [];
        $payload = json_encode([
            'locale' => cp_locale(),
            'dir' => cp_html_dir(),
            'strings' => cp_js_translations(),
            'phraseMap' => $phraseMap,
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
