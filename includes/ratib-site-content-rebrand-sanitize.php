<?php
/**
 * Override stale RATIB-era CMS strings (DB / JSON snapshot) with RATEB defaults at read time.
 */
declare(strict_types=1);

if (!function_exists('ratib_site_content_rebrand_substring_map')) {
    /**
     * @return list<array{0:string,1:string}>
     */
    function ratib_site_content_rebrand_substring_map(): array
    {
        $legal = function_exists('ratib_legal_entity_name')
            ? ratib_legal_entity_name()
            : 'Rateb Software Foundation for Information Technology';
        $expansion = function_exists('ratib_brand_expansion')
            ? ratib_brand_expansion()
            : 'Recruitment Automation & Telemetry Enterprise Base';

        return [
            ['Ratib Software Foundation for Information Technology', $legal],
            ['Ratib Software Foundation', 'Rateb Software Foundation'],
            ['Ratib Company', 'RATEB'],
            ['RECRUITMENT AUTOMATION & TRACKING INTELLIGENCE BASES', strtoupper($expansion)],
            ['RECRUITMENT AUTOMATION & TRACKING INTELLIGENCE BASE', strtoupper($expansion)],
            ['Recruitment Automation & Tracking Intelligence Bases', $expansion],
            ['Recruitment Automation & Tracking Intelligence Base', $expansion],
            ['About <span class="ratib-about-gradient">Ratib Company</span>', 'About <span class="ratib-about-gradient">RATEB</span>'],
            ['About Ratib Company', 'About RATEB'],
        ];
    }
}

if (!function_exists('ratib_site_content_rebrand_apply_substrings')) {
    function ratib_site_content_rebrand_apply_substrings(string $value): string
    {
        foreach (ratib_site_content_rebrand_substring_map() as [$from, $to]) {
            if ($from !== '' && str_contains($value, $from)) {
                $value = str_replace($from, $to, $value);
            }
        }

        return $value;
    }
}

if (!function_exists('ratib_site_content_rebrand_public_default_keys')) {
    /**
     * Public marketing always uses PHP defaults for these keys (DB cannot override).
     *
     * @return list<string>
     */
    function ratib_site_content_rebrand_public_default_keys(): array
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
            'profile.meta.title',
            'profile.company.trade_name',
            'profile.company.legal_name',
            'profile.company.tagline',
            'profile.company.summary',
        ];
    }
}

if (!function_exists('ratib_site_content_rebrand_value_is_stale')) {
    function ratib_site_content_rebrand_value_is_stale(string $key, string $value): bool
    {
        $v = trim($value);
        if ($v === '') {
            return false;
        }
        $low = strtolower($v);

        if (str_contains($low, 'ratib software foundation')
            || str_contains($low, 'ratib company')
            || str_contains($low, 'tracking intelligence')) {
            return true;
        }

        if (preg_match('/\.(title|page_title)$/', $key) && preg_match('/\bRATIB\b/', $v) && !str_contains($v, 'RATEB')) {
            return true;
        }

        if ($key === 'home.brand.name' && preg_match('/ratib/i', $v)) {
            return true;
        }

        if ($key === 'profile.company.trade_name' && preg_match('/^ratib\b/i', $v)) {
            return true;
        }

        if ($key === 'profile.company.trade_name' && strtoupper($v) === 'RATIB') {
            return true;
        }

        if ($key === 'home.meta.page_title' && preg_match('/\bRATIB\b/', $v) && !str_contains($v, 'RATEB')) {
            return true;
        }

        if ($key === 'home.hero.eyebrow' && str_contains($low, 'tracking intelligence')) {
            return true;
        }

        if ($key === 'home.hero.title_gradient' && str_contains($low, 'workforce intelligence')) {
            return true;
        }

        if ($key === 'home.hero.title_before' && str_contains($low, 'recruitment automation')
            && !str_contains($low, 'enterprise workforce')) {
            return true;
        }

        if ($key === 'home.footer.brand' && str_contains($low, 'enterprise recruitment operating system')) {
            return true;
        }

        if ($key === 'home.hero.lead' && str_contains($low, 'production control plane for sending-country agencies')
            && !str_contains($low, 'multi-tenant control plane')) {
            return true;
        }

        if (str_starts_with($key, 'home.hero.') && str_contains($low, 'launch operations workspace')) {
            return true;
        }

        return false;
    }
}

if (!function_exists('ratib_site_content_rebrand_resolve_brand_name')) {
    function ratib_site_content_rebrand_resolve_brand_name(string $raw): string
    {
        $name = trim(ratib_site_content_rebrand_apply_substrings($raw));
        if ($name === '' || ratib_site_content_rebrand_value_is_stale('home.brand.name', $name)) {
            return function_exists('ratib_brand_name') ? ratib_brand_name() : 'RATEB';
        }

        return $name;
    }
}

if (!function_exists('ratib_site_content_rebrand_sanitize_flat')) {
    /**
     * @param array<string, string> $flat
     * @param array<string, string> $defaults
     *
     * @return array<string, string>
     */
    function ratib_site_content_rebrand_sanitize_flat(array $flat, array $defaults): array
    {
        foreach ($flat as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            $value = ratib_site_content_rebrand_apply_substrings($value);
            if (array_key_exists($key, $defaults)
                && ratib_site_content_rebrand_value_is_stale($key, $value)) {
                $value = (string) $defaults[$key];
            }
            $flat[$key] = $value;
        }

        if (isset($flat['home.brand.name'])) {
            $flat['home.brand.name'] = ratib_site_content_rebrand_resolve_brand_name((string) $flat['home.brand.name']);
        }

        foreach (ratib_site_content_rebrand_public_default_keys() as $forceKey) {
            if (array_key_exists($forceKey, $defaults)) {
                $flat[$forceKey] = (string) $defaults[$forceKey];
            }
        }

        return $flat;
    }
}

if (!function_exists('ratib_site_content_rebrand_persist_stale_keys')) {
    /**
     * Write sanitized values back to ratib_site_content (fixes CMS editor + removes stale reads).
     *
     * @param array<string, string> $defaults
     *
     * @return array{updated:int, skipped:int, errors:int}
     */
    function ratib_site_content_rebrand_persist_stale_keys(array $defaults): array
    {
        $stats = ['updated' => 0, 'skipped' => 0, 'errors' => 0];
        if (!function_exists('ratib_site_content_db') || !function_exists('ratib_site_content_home_flat')) {
            return $stats;
        }
        $conn = ratib_site_content_db(true);
        if (!$conn) {
            return $stats;
        }

        $before = ratib_site_content_home_flat(false);
        $after = ratib_site_content_rebrand_sanitize_flat($before, $defaults);

        $stmt = $conn->prepare(
            'INSERT INTO ratib_site_content (content_key, content_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_at = CURRENT_TIMESTAMP'
        );
        if (!$stmt) {
            $stats['errors']++;

            return $stats;
        }

        $forceKeys = array_flip(ratib_site_content_rebrand_public_default_keys());

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
                && !ratib_site_content_rebrand_value_is_stale($key, $oldVal)
                && ratib_site_content_rebrand_apply_substrings($oldVal) === (string) $newVal) {
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

        if (function_exists('ratib_site_content_home_snapshot_db_delete')) {
            ratib_site_content_home_snapshot_db_delete();
        }
        if (function_exists('ratib_site_content_cache_unlink_json_candidates')) {
            ratib_site_content_cache_unlink_json_candidates();
        }

        return $stats;
    }
}
