<?php
declare(strict_types=1);

namespace Rateb\App\Lib\HrLetterPdf;

/**
 * Minimal Arabic letter reshaper for GD certificate rendering.
 */
final class ArabicText
{
    /** @var array<string, array{0:string,1:string,2:string,3:string}|string> */
    private const MAP = [
        'ا' => ['ا', 'ا', 'ا', 'ا'], 'أ' => ['أ', 'أ', 'أ', 'أ'], 'إ' => ['إ', 'إ', 'إ', 'إ'], 'آ' => ['آ', 'آ', 'آ', 'آ'],
        'ب' => ['ب', 'ب', 'ﺑ', 'ﺒ'], 'ت' => ['ت', 'ت', 'ﺗ', 'ﺘ'], 'ث' => ['ث', 'ث', 'ﺛ', 'ﺜ'],
        'ج' => ['ج', 'ج', 'ﺟ', 'ﺠ'], 'ح' => ['ح', 'ح', 'ﺣ', 'ﺤ'], 'خ' => ['خ', 'خ', 'ﺧ', 'ﺨ'],
        'د' => ['د', 'د', 'د', 'د'], 'ذ' => ['ذ', 'ذ', 'ذ', 'ذ'], 'ر' => ['ر', 'ر', 'ر', 'ر'], 'ز' => ['ز', 'ز', 'ز', 'ز'],
        'س' => ['س', 'س', 'ﺳ', 'ﺴ'], 'ش' => ['ش', 'ش', 'ﺷ', 'ﺸ'], 'ص' => ['ص', 'ص', 'ﺻ', 'ﺼ'],
        'ض' => ['ض', 'ض', 'ﺿ', 'ﻀ'], 'ط' => ['ط', 'ط', 'ﻃ', 'ﻄ'], 'ظ' => ['ظ', 'ظ', 'ﻇ', 'ﻈ'],
        'ع' => ['ع', 'ع', 'ﻋ', 'ﻌ'], 'غ' => ['غ', 'غ', 'ﻏ', 'ﻐ'], 'ف' => ['ف', 'ف', 'ﻓ', 'ﻔ'],
        'ق' => ['ق', 'ق', 'ﻗ', 'ﻘ'], 'ك' => ['ك', 'ك', 'ﻛ', 'ﻜ'], 'ل' => ['ل', 'ل', 'ﻟ', 'ﻠ'],
        'م' => ['م', 'م', 'ﻣ', 'ﻤ'], 'ن' => ['ن', 'ن', 'ﻧ', 'ﻨ'], 'ه' => ['ه', 'ه', 'ﻫ', 'ﻬ'],
        'و' => ['و', 'و', 'و', 'و'], 'ى' => ['ى', 'ى', 'ى', 'ى'], 'ي' => ['ي', 'ي', 'ﻳ', 'ﻴ'],
        'ة' => ['ة', 'ة', 'ة', 'ة'], 'ء' => 'ء', 'ؤ' => ['ؤ', 'ؤ', 'ؤ', 'ؤ'], 'ئ' => ['ئ', 'ئ', 'ﺋ', 'ﺌ'],
    ];

    private const NON_CONNECT = 'اأإآدذرزوؤءة';

    /** Reshape Arabic runs; keep Latin/digits LTR; reverse segments for RTL line. */
    public static function prepare(string $text): string
    {
        $text = trim($text);
        if ($text === '' || !preg_match('/\p{Arabic}/u', $text)) {
            return $text;
        }
        if (!preg_match_all('/\p{Arabic}+|[^\p{Arabic}]+/u', $text, $m)) {
            return $text;
        }
        $segments = [];
        foreach ($m[0] as $seg) {
            if (preg_match('/\p{Arabic}/u', $seg)) {
                $segments[] = self::reshapeRun($seg);
            } else {
                $segments[] = $seg;
            }
        }
        return implode('', array_reverse($segments));
    }

    private static function reshapeRun(string $text): string
    {
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        $n = count($chars);
        for ($i = 0; $i < $n; $i++) {
            $ch = $chars[$i];
            if ($ch === 'ل' && isset($chars[$i + 1]) && in_array($chars[$i + 1], ['ا', 'أ', 'إ', 'آ'], true)) {
                $out[] = 'لا';
                $i++;
                continue;
            }
            if (!isset(self::MAP[$ch]) || !is_array(self::MAP[$ch])) {
                $out[] = is_string(self::MAP[$ch] ?? null) ? (string) self::MAP[$ch] : $ch;
                continue;
            }
            $prev = $i > 0 ? $chars[$i - 1] : '';
            $next = $i + 1 < $n ? $chars[$i + 1] : '';
            $connectPrev = $prev !== '' && isset(self::MAP[$prev]) && is_array(self::MAP[$prev])
                && mb_strpos(self::NON_CONNECT, $prev) === false;
            $connectNext = $next !== '' && isset(self::MAP[$next]) && is_array(self::MAP[$next]);
            if ($connectPrev && $connectNext) {
                $out[] = self::MAP[$ch][3];
            } elseif ($connectPrev) {
                $out[] = self::MAP[$ch][1];
            } elseif ($connectNext) {
                $out[] = self::MAP[$ch][2];
            } else {
                $out[] = self::MAP[$ch][0];
            }
        }
        return implode('', array_reverse($out));
    }
}
