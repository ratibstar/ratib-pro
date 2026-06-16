<?php
/**
 * Override stale RATEB-era CMS strings (DB / JSON snapshot) with RATEB defaults at read time.
 */
declare(strict_types=1);

if (!function_exists('rateb_site_content_rebrand_substring_map')) {
    /**
     * @return list<array{0:string,1:string}>
     */
    function rateb_site_content_rebrand_substring_map(): array
    {
        $expansion = function_exists('rateb_brand_expansion')
            ? rateb_brand_expansion()
            : 'Recruitment Automation & Telemetry Enterprise Base';
        $platform = function_exists('rateb_brand_platform_name')
            ? rateb_brand_platform_name()
            : 'RATEB Platform';
        $company = function_exists('rateb_brand_company_name')
            ? rateb_brand_company_name()
            : 'RATEB Company';

        return [
            ['RATEB Software Foundation for Information Technology', $platform],
            ['Rateb Software Foundation for Information Technology', $platform],
            ['RATEB Software Foundation', $platform],
            ['Rateb Software Foundation', $platform],
            ['RATEB Company', 'RATEB'],
            ['RATEB — Recruitment Automation & Tracking Intelligence Base', 'RATEB — ' . $expansion],
            ['RATEB - Recruitment Automation & Tracking Intelligence Base', 'RATEB — ' . $expansion],
            ['RECRUITMENT AUTOMATION & TRACKING INTELLIGENCE BASES', strtoupper($expansion)],
            ['RECRUITMENT AUTOMATION & TRACKING INTELLIGENCE BASE', strtoupper($expansion)],
            ['Recruitment Automation & Tracking Intelligence Bases', $expansion],
            ['Recruitment Automation & Tracking Intelligence Base', $expansion],
            ['Recruitment Automation & Tracking Intelligence', $expansion],
            ['About <span class="rateb-about-gradient">RATEB Company</span>', 'About <span class="rateb-about-gradient">RATEB</span>'],
            ['About RATEB Company', 'About RATEB'],
            ['RATEB — Rateb Software Foundation for Information Technology', 'RATEB — ' . $expansion],
            ['RATEB — RATEB Software Foundation for Information Technology', 'RATEB — ' . $expansion],
            ['Legal identity, platform scope, corridors, and operational capabilities of Rateb Software Foundation for Information Technology.', 'Enterprise workforce program infrastructure — platform scope, corridors, and operational capabilities of ' . $platform . '.'],
            ['Legal identity, platform scope, corridors, and operational capabilities of RATEB Software Foundation for Information Technology.', 'Enterprise workforce program infrastructure — platform scope, corridors, and operational capabilities of ' . $platform . '.'],
            ['Rateb Software Foundation for Information Technology develops and operates RATEB:', $company . ' operates the RATEB platform:'],
            ['RATEB Software Foundation for Information Technology develops and operates RATEB:', $company . ' operates the RATEB platform:'],
        ];
    }
}

if (!function_exists('rateb_site_content_rebrand_apply_substrings')) {
    function rateb_site_content_rebrand_apply_substrings(string $value): string
    {
        foreach (rateb_site_content_rebrand_substring_map() as [$from, $to]) {
            if ($from !== '' && str_contains($value, $from)) {
                $value = str_replace($from, $to, $value);
            }
        }
        if (preg_match('/\bRATEB\b/', $value) && !str_contains($value, 'RATEB')) {
            $value = (string) preg_replace('/\bRATEB\b/', 'RATEB', $value);
        }

        return $value;
    }
}

if (!function_exists('rateb_site_content_rebrand_public_default_keys')) {
    /**
     * Public marketing always uses PHP defaults for these keys (DB cannot override).
     *
     * @return list<string>
     */
    function rateb_site_content_rebrand_public_default_keys(): array
    {
        return [
            'home.brand.name',
            'home.meta.page_title',
            'home.hero.eyebrow',
            'home.hero.lead',
            'home.hero.title_before',
            'home.hero.title_gradient',
            'home.footer.brand',
            'home.footer.copyright_suffix',
            'home.topbar.ops_line',
            'home.topbar.nodes_count',
            'home.analytics.sub',
            'home.analytics.sample_tag',
            'home.footer.strip.1',
            'profile.meta.title',
            'profile.meta.description',
            'profile.company.trade_name',
            'profile.company.legal_name',
            'profile.company.tagline',
            'profile.company.summary',
            'proc.identity.legal_name',
        ];
    }
}

if (!function_exists('rateb_site_content_rebrand_value_is_stale')) {
    function rateb_site_content_rebrand_value_is_stale(string $key, string $value): bool
    {
        $v = trim($value);
        if ($v === '') {
            return false;
        }
        $low = strtolower($v);

        if (str_contains($low, 'software foundation')
            || str_contains($low, 'rateb company')
            || str_contains($low, 'tracking intelligence')) {
            return true;
        }

        if (preg_match('/\.(title|page_title|description)$/', $key) && preg_match('/\bRATEB\b/', $v) && !str_contains($v, 'RATEB')) {
            return true;
        }

        if ($key === 'home.brand.name' && preg_match('/rateb/i', $v)) {
            return true;
        }

        if ($key === 'profile.company.trade_name' && preg_match('/^rateb\b/i', $v)) {
            return true;
        }

        if ($key === 'profile.company.trade_name' && strtoupper($v) === 'RATEB') {
            return true;
        }

        if ($key === 'profile.company.legal_name' && str_contains($low, 'foundation')) {
            return true;
        }

        if ($key === 'home.meta.page_title' && preg_match('/\bRATEB\b/', $v) && !str_contains($v, 'RATEB')) {
            return true;
        }

        if ($key === 'home.hero.eyebrow' && str_contains($low, 'tracking intelligence')) {
            return true;
        }

        if ($key === 'home.hero.title_gradient' && str_contains($low, 'workforce intelligence')) {
            return true;
        }

        if ($key === 'home.hero.title_before' && str_contains($low, 'recruitment automation')
            && !str_contains($low, 'rateb')) {
            return true;
        }

        if ($key === 'home.topbar.nodes_count' && ($v === '247' || preg_match('/^\d{2,}$/', $v))) {
            return true;
        }

        if ($key === 'home.footer.strip.1' && str_contains($low, 'synthetic checks')) {
            return true;
        }

        if ($key === 'home.analytics.sub' && !str_contains($low, 'sample') && preg_match('/\d+\.?\d*%|\+?\d+%|\d\.\dk/i', $v)) {
            return true;
        }

        if ($key === 'home.hero.title_before' && str_contains($low, 'enterprise workforce')) {
            return true;
        }

        if ($key === 'home.footer.brand' && str_contains($low, 'enterprise recruitment operating system')) {
            return true;
        }

        if ($key === 'home.hero.lead' && str_contains($low, 'production control plane for sending-country agencies')
            && !str_contains($low, 'cross-border workforce')) {
            return true;
        }

        if (str_starts_with($key, 'home.hero.') && str_contains($low, 'launch operations workspace')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('rateb_site_content_rebrand_resolve_brand_name')) {
    function rateb_site_content_rebrand_resolve_brand_name(string $raw): string
    {
        $name = trim(rateb_site_content_rebrand_apply_substrings($raw));
        if ($name === '' || rateb_site_content_rebrand_value_is_stale('home.brand.name', $name)) {
            return function_exists('rateb_brand_name') ? rateb_brand_name() : 'RATEB';
        }

        return $name;
    }
}

if (!function_exists('rateb_site_content_rebrand_sanitize_flat')) {
    /**
     * @param array<string, string> $flat
     * @param array<string, string> $defaults
     *
     * @return array<string, string>
     */
    function rateb_site_content_rebrand_sanitize_flat(array $flat, array $defaults): array
    {
        foreach ($flat as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            $value = rateb_site_content_rebrand_apply_substrings($value);
            if (array_key_exists($key, $defaults)
                && rateb_site_content_rebrand_value_is_stale($key, $value)) {
                $value = (string) $defaults[$key];
            }
            $flat[$key] = $value;
        }

        if (isset($flat['home.brand.name'])) {
            $flat['home.brand.name'] = rateb_site_content_rebrand_resolve_brand_name((string) $flat['home.brand.name']);
        }

        foreach (rateb_site_content_rebrand_public_default_keys() as $forceKey) {
            if (array_key_exists($forceKey, $defaults)) {
                $flat[$forceKey] = (string) $defaults[$forceKey];
            }
        }

        return $flat;
    }
}

if (!function_exists('rateb_site_content_rebrand_persist_stale_keys')) {
    /**
     * Write sanitized values back to rateb_site_content (fixes CMS editor + removes stale reads).
     *
     * @param array<string, string> $defaults
     *
     * @return array{updated:int, skipped:int, errors:int}
     */
    function rateb_site_content_rebrand_persist_stale_keys(array $defaults): array
    {
        $stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0];
        if (!function_exists('rateb_site_content_db') || !function_exists('rateb_site_content_home_flat')) {
            return $stats;
        }
        $conn = rateb_site_content_db(true);
        if (!$conn) {
            return $stats;
        }

        $before = rateb_site_content_home_flat(false);
        $after = rateb_site_content_rebrand_sanitize_flat($before, $defaults);

        $stmt = $conn->prepare(
            'INSERT INTO rateb_site_content (content_key, content_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_at = CURRENT_TIMESTAMP'
        );
        if (!$stmt) {
            $stats['errors']++;

            return $stats;
        }

        $forceKeys = array_flip(rateb_site_content_rebrand_public_default_keys());

        foreach ($after as $key => $newVal) {
            if (!array_key_exists($key, $before)) {
                continue;
            }
            $oldVal = (string) $before[$key];
            if ($oldVal === (string) $newVal) {
                $stats['skipped']++;

                continue;
            }
            if (!isset($forceKeys[$key])
                && !rateb_site_content_rebrand_value_is_stale($key, $oldVal)
                && rateb_site_content_rebrand_apply_substrings($oldVal) === (string) $newVal) {
                $stats['skipped']++;

                continue;
            }
            $k = (string) $key;
            $v = (string) $newVal;
            $stmt->bind_param('ss', $k, $v);
            if ($stmt->execute()) {
                $stats['updated']++;
            } else {
                $stats['errors']++;
            }
        }
        $stmt->close();

        if (function_exists('rateb_site_content_home_snapshot_db_delete')) {
            rateb_site_content_home_snapshot_db_delete();
        }
        if (function_exists('rateb_site_content_cache_unlink_json_candidates')) {
            rateb_site_content_cache_unlink_json_candidates();
        }

        return $stats;
    }
}
