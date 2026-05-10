<?php
/**
 * Unlimited homepage program images + videos via JSON slots (home.program.slots_json, home.video.slots_json).
 * Legacy numbered keys (home.program.imgN, home.video.fileN) are still read when JSON is empty.
 */

if (!function_exists('ratib_site_content_home_normalize_program_slots')) {
    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array{caption:string, alt:string, src:string}>
     */
    function ratib_site_content_home_normalize_program_slots(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $out[] = [
                'caption' => trim((string) ($r['caption'] ?? '')),
                'alt' => trim((string) ($r['alt'] ?? '')),
                'src' => trim((string) ($r['src'] ?? '')),
            ];
        }

        return $out;
    }
}

if (!function_exists('ratib_site_content_home_default_program_slots_json')) {
    function ratib_site_content_home_default_program_slots_json(): string
    {
        $rows = [
            ['caption' => 'Pipeline board', 'alt' => 'RATIB pipeline board with stages, SLA, and worker rows', 'src' => ''],
            ['caption' => 'Workers registry', 'alt' => 'RATIB workers registry with stages, owners, and GPS context', 'src' => ''],
            ['caption' => 'Finance & ledger', 'alt' => 'RATIB finance view with invoices, throughput, and connector latency', 'src' => ''],
        ];

        return json_encode($rows, JSON_UNESCAPED_UNICODE);
    }
}

if (!function_exists('ratib_site_content_home_legacy_program_row_at')) {
    /**
     * Legacy slot N (1-based): home.program.imgN, caption.N, alt.N
     *
     * @param array<string, string> $flat
     *
     * @return array{caption:string, alt:string, src:string}
     */
    function ratib_site_content_home_legacy_program_row_at(array $flat, int $n): array
    {
        return [
            'caption' => trim((string) ($flat['home.program.caption.' . $n] ?? '')),
            'alt' => trim((string) ($flat['home.program.alt.' . $n] ?? '')),
            'src' => trim((string) ($flat['home.program.img' . $n] ?? '')),
        ];
    }
}

if (!function_exists('ratib_site_content_home_legacy_program_max_slot')) {
    /**
     * Highest legacy slot index (1..500) that has any value.
     */
    function ratib_site_content_home_legacy_program_max_slot(array $flat): int
    {
        for ($n = 500; $n >= 1; $n--) {
            $r = ratib_site_content_home_legacy_program_row_at($flat, $n);
            if ($r['src'] !== '' || $r['caption'] !== '' || $r['alt'] !== '') {
                return $n;
            }
        }

        return 0;
    }
}

if (!function_exists('ratib_site_content_home_program_slots_from_flat')) {
    /**
     * @param array<string, string> $flat
     *
     * @return list<array{caption:string, alt:string, src:string}>
     */
    function ratib_site_content_home_program_slots_from_flat(array $flat): array
    {
        $raw = trim((string) ($flat['home.program.slots_json'] ?? ''));

        $legacyOnlyCompact = static function (array $flatIn): array {
            $out = [];
            for ($i = 1; $i <= 500; $i++) {
                $r = ratib_site_content_home_legacy_program_row_at($flatIn, $i);
                if ($r['src'] === '' && $r['caption'] === '' && $r['alt'] === '') {
                    continue;
                }
                $out[] = $r;
            }

            return ratib_site_content_home_normalize_program_slots($out);
        };

        if ($raw === '') {
            $compact = $legacyOnlyCompact($flat);
            if (count($compact) > 0) {
                return $compact;
            }
            $def = json_decode(ratib_site_content_home_default_program_slots_json(), true);

            return is_array($def) ? ratib_site_content_home_normalize_program_slots($def) : [];
        }

        $d = json_decode($raw, true);
        if (!is_array($d) || count($d) === 0) {
            $compact = $legacyOnlyCompact($flat);
            if (count($compact) > 0) {
                return $compact;
            }
            $def = json_decode(ratib_site_content_home_default_program_slots_json(), true);

            return is_array($def) ? ratib_site_content_home_normalize_program_slots($def) : [];
        }

        $rows = ratib_site_content_home_normalize_program_slots($d);
        $legacyMax = ratib_site_content_home_legacy_program_max_slot($flat);
        $max = max(count($rows), $legacyMax);
        $merged = [];
        for ($i = 0; $i < $max; $i++) {
            $slotNum = $i + 1;
            $jsonRow = $rows[$i] ?? ['caption' => '', 'alt' => '', 'src' => ''];
            $legRow = ratib_site_content_home_legacy_program_row_at($flat, $slotNum);
            $src = trim((string) ($jsonRow['src'] ?? '')) !== '' ? trim((string) $jsonRow['src']) : $legRow['src'];
            $cap = trim((string) ($jsonRow['caption'] ?? '')) !== '' ? trim((string) $jsonRow['caption']) : $legRow['caption'];
            $alt = trim((string) ($jsonRow['alt'] ?? '')) !== '' ? trim((string) $jsonRow['alt']) : $legRow['alt'];
            if ($src === '' && $cap === '' && $alt === '') {
                continue;
            }
            $merged[] = ['caption' => $cap, 'alt' => $alt, 'src' => $src];
        }

        return ratib_site_content_home_normalize_program_slots($merged);
    }
}

if (!function_exists('ratib_site_content_home_legacy_video_src_list')) {
    /**
     * @param array<string, string> $flat
     *
     * @return list<string>
     */
    function ratib_site_content_home_legacy_video_src_list(array $flat): array
    {
        $keys = ['home.video.file'];
        for ($i = 2; $i <= 99; $i++) {
            $keys[] = 'home.video.file' . $i;
        }
        $srcs = [];
        foreach ($keys as $lk) {
            $v = trim((string) ($flat[$lk] ?? ''));
            if ($v !== '') {
                $srcs[] = $v;
            }
        }

        return $srcs;
    }
}

if (!function_exists('ratib_site_content_home_video_src_strings_from_flat')) {
    /**
     * Ordered list of stored video references (tokens, paths, or URLs).
     * Merges JSON slots with legacy home.video.file* so empty JSON src cells still pick up old uploads.
     *
     * @param array<string, string> $flat
     *
     * @return list<string>
     */
    function ratib_site_content_home_video_src_strings_from_flat(array $flat): array
    {
        $legacyList = ratib_site_content_home_legacy_video_src_list($flat);
        $raw = trim((string) ($flat['home.video.slots_json'] ?? ''));
        if ($raw === '') {
            return $legacyList;
        }

        $d = json_decode($raw, true);
        if (!is_array($d) || count($d) === 0) {
            return $legacyList;
        }

        $jsonSrcs = [];
        foreach ($d as $row) {
            $jsonSrcs[] = is_array($row) ? trim((string) ($row['src'] ?? '')) : trim((string) $row);
        }

        $max = max(count($jsonSrcs), count($legacyList));
        $merged = [];
        for ($i = 0; $i < $max; $i++) {
            $j = $jsonSrcs[$i] ?? '';
            $l = $legacyList[$i] ?? '';
            $pick = ($j !== '') ? $j : $l;
            if ($pick !== '') {
                $merged[] = $pick;
            }
        }

        return $merged;
    }
}

if (!function_exists('ratib_site_content_home_resolve_video_display_url')) {
    /**
     * Build a browser-ready URL/path for <video src="…"> from CMS stored value.
     *
     * @param string $stored Path, scmedia: token, or absolute URL
     */
    function ratib_site_content_home_resolve_video_display_url(string $stored, string $baseUrl): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $stored)) {
            return $stored;
        }
        if (function_exists('ratib_site_content_media_public_url') && ratib_site_content_media_public_url($baseUrl, $stored) !== '') {
            return ratib_site_content_media_public_url($baseUrl, $stored);
        }
        $rel = ltrim(str_replace('\\', '/', $stored), '/');
        $fs = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        $v = is_file($fs) ? (int) filemtime($fs) : time();

        return rtrim($baseUrl, '/') . '/' . $rel . '?v=' . $v;
    }
}

if (!function_exists('ratib_site_content_home_legacy_media_db_keys')) {
    /**
     * Pre–slots_json keys still stored in ratib_site_content but omitted from defaults (not loaded by batch SELECT).
     *
     * @return list<string>
     */
    function ratib_site_content_home_legacy_media_db_keys(): array
    {
        $keys = [];
        for ($i = 1; $i <= 500; $i++) {
            $keys[] = 'home.program.img' . $i;
            $keys[] = 'home.program.caption.' . $i;
            $keys[] = 'home.program.alt.' . $i;
        }
        $keys[] = 'home.video.file';
        for ($i = 2; $i <= 99; $i++) {
            $keys[] = 'home.video.file' . $i;
        }

        return $keys;
    }
}

if (!function_exists('ratib_site_content_home_merge_legacy_media_into_values')) {
    /**
     * Overlay legacy flat keys so JSON merge + CMS editors see uploads saved under home.program.imgN / home.video.fileN.
     *
     * @param array<string, string> $values
     *
     * @return array<string, string>
     */
    function ratib_site_content_home_merge_legacy_media_into_values(array $values): array
    {
        if (!function_exists('ratib_site_content_fetch_key_values')) {
            return $values;
        }
        $extra = ratib_site_content_fetch_key_values(ratib_site_content_home_legacy_media_db_keys());
        foreach ($extra as $k => $v) {
            $values[$k] = $v;
        }

        return $values;
    }
}
