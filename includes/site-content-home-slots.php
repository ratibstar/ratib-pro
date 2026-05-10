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

if (!function_exists('ratib_site_content_home_program_slots_from_flat')) {
    /**
     * @param array<string, string> $flat
     *
     * @return list<array{caption:string, alt:string, src:string}>
     */
    function ratib_site_content_home_program_slots_from_flat(array $flat): array
    {
        $raw = trim((string) ($flat['home.program.slots_json'] ?? ''));
        if ($raw !== '') {
            $d = json_decode($raw, true);
            if (is_array($d) && count($d) > 0) {
                return ratib_site_content_home_normalize_program_slots($d);
            }
        }

        $out = [];
        for ($i = 1; $i <= 500; $i++) {
            $src = trim((string) ($flat['home.program.img' . $i] ?? ''));
            $cap = trim((string) ($flat['home.program.caption.' . $i] ?? ''));
            $alt = trim((string) ($flat['home.program.alt.' . $i] ?? ''));
            if ($src === '' && $cap === '' && $alt === '') {
                continue;
            }
            $out[] = ['caption' => $cap, 'alt' => $alt, 'src' => $src];
        }
        if (count($out) > 0) {
            return ratib_site_content_home_normalize_program_slots($out);
        }

        $def = json_decode(ratib_site_content_home_default_program_slots_json(), true);

        return is_array($def) ? ratib_site_content_home_normalize_program_slots($def) : [];
    }
}

if (!function_exists('ratib_site_content_home_video_src_strings_from_flat')) {
    /**
     * Ordered list of stored video references (tokens, paths, or URLs).
     *
     * @param array<string, string> $flat
     *
     * @return list<string>
     */
    function ratib_site_content_home_video_src_strings_from_flat(array $flat): array
    {
        $raw = trim((string) ($flat['home.video.slots_json'] ?? ''));
        if ($raw !== '') {
            $d = json_decode($raw, true);
            if (is_array($d) && count($d) > 0) {
                $srcs = [];
                foreach ($d as $row) {
                    $s = is_array($row) ? trim((string) ($row['src'] ?? '')) : trim((string) $row);
                    if ($s !== '') {
                        $srcs[] = $s;
                    }
                }
                if (count($srcs) > 0) {
                    return $srcs;
                }
            }
        }

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
