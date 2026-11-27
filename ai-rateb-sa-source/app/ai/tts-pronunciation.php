<?php
declare(strict_types=1);

/**
 * Arabic TTS pronunciation normalization (spoken input only).
 * Does not mutate campaign copy, UI text, or stored approved outputs.
 *
 * Dictionary entries: longest matches first. Latin matches are case-insensitive.
 * Optional extra rules via RATEB_TTS_PRONUNCIATION_JSON (JSON object: match => replace).
 */

/** @return list<array{match:string,replace:string}> */
function rateb_tts_pronunciation_dictionary(): array
{
    $defaults = [
        ['match' => 'RATEB AI', 'replace' => 'رَتِّب إيه آي'],
        ['match' => 'RATEB', 'replace' => 'رَتِّب'],
        ['match' => 'رتب', 'replace' => 'رَتِّب'],
        ['match' => 'AI', 'replace' => 'إيه آي'],
    ];

    $extra = rateb_tts_pronunciation_dictionary_extra();
    if ($extra === []) {
        return $defaults;
    }

    $byMatch = [];
    foreach (array_merge($defaults, $extra) as $row) {
        $match = trim((string) ($row['match'] ?? ''));
        $replace = (string) ($row['replace'] ?? '');
        if ($match === '' || $replace === '') {
            continue;
        }
        $byMatch[$match] = ['match' => $match, 'replace' => $replace];
    }

    return array_values($byMatch);
}

/** @return list<array{match:string,replace:string}> */
function rateb_tts_pronunciation_dictionary_extra(): array
{
    $raw = trim((string) getenv('RATEB_TTS_PRONUNCIATION_JSON'));
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $rows = [];
    foreach ($decoded as $match => $replace) {
        if (!is_string($match) || !is_string($replace)) {
            continue;
        }
        $match = trim($match);
        if ($match === '' || $replace === '') {
            continue;
        }
        $rows[] = ['match' => $match, 'replace' => $replace];
    }
    return $rows;
}

/**
 * Apply pronunciation dictionary to TTS input only.
 *
 * @param list<array{match:string,replace:string}>|null $dictionary
 */
function rateb_tts_normalize_pronunciation(string $text, ?array $dictionary = null): string
{
    if ($text === '') {
        return '';
    }
    $rules = $dictionary ?? rateb_tts_pronunciation_dictionary();
    usort($rules, static function (array $a, array $b): int {
        return mb_strlen((string) $b['match'], 'UTF-8') <=> mb_strlen((string) $a['match'], 'UTF-8');
    });

    foreach ($rules as $rule) {
        $match = (string) ($rule['match'] ?? '');
        $replace = (string) ($rule['replace'] ?? '');
        if ($match === '' || $replace === '') {
            continue;
        }
        $quoted = preg_quote($match, '/');
        // Word-ish boundaries so "AI" does not match inside "MAIL", and punctuation is preserved.
        $hasArabic = preg_match('/\p{Arabic}/u', $match) === 1;
        $flags = $hasArabic ? 'u' : 'iu';
        $pattern = '/(?<![\p{L}\p{N}_])' . $quoted . '(?![\p{L}\p{N}_])/' . $flags;
        $next = preg_replace($pattern, $replace, $text);
        if (is_string($next)) {
            $text = $next;
        }
    }

    return $text;
}
