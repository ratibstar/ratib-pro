<?php
declare(strict_types=1);

namespace Rateb\App\Lib\HrLetterPdf;

/**
 * Minimal TrueType cmap (format 4) Unicode → glyphId lookup for PDF Identity-H text.
 */
final class TtfCmap
{
    /** @var array<int, int> */
    private array $map = [];

    public function __construct(string $ttfBytes)
    {
        $this->map = $this->parse($ttfBytes);
    }

    public function glyphId(int $codepoint): int
    {
        return $this->map[$codepoint] ?? 0;
    }

    /**
     * @return array<int, int>
     */
    private function parse(string $data): array
    {
        $out = [];
        if (strlen($data) < 12) {
            return $out;
        }
        $numTables = unpack('n', substr($data, 4, 2))[1] ?? 0;
        $cmapOffset = null;
        for ($i = 0; $i < $numTables; $i++) {
            $rec = substr($data, 12 + $i * 16, 16);
            $tag = substr($rec, 0, 4);
            $off = unpack('N', substr($rec, 8, 4))[1] ?? 0;
            if ($tag === 'cmap') {
                $cmapOffset = $off;
                break;
            }
        }
        if ($cmapOffset === null) {
            return $out;
        }
        $numEnc = unpack('n', substr($data, $cmapOffset + 2, 2))[1] ?? 0;
        $fmt4 = null;
        for ($i = 0; $i < $numEnc; $i++) {
            $enc = substr($data, $cmapOffset + 4 + $i * 8, 8);
            $platform = unpack('n', substr($enc, 0, 2))[1] ?? 0;
            $encoding = unpack('n', substr($enc, 2, 2))[1] ?? 0;
            $offset = unpack('N', substr($enc, 4, 4))[1] ?? 0;
            $tablePos = $cmapOffset + $offset;
            $format = unpack('n', substr($data, $tablePos, 2))[1] ?? 0;
            if ($format === 4 && (($platform === 3 && $encoding === 1) || ($platform === 0))) {
                $fmt4 = $tablePos;
                if ($platform === 3) {
                    break;
                }
            }
        }
        if ($fmt4 === null) {
            return $out;
        }
        $segCountX2 = unpack('n', substr($data, $fmt4 + 6, 2))[1] ?? 0;
        $segCount = (int) ($segCountX2 / 2);
        $endCodes = [];
        $startCodes = [];
        $idDeltas = [];
        $idRangeOffsets = [];
        $p = $fmt4 + 14;
        for ($i = 0; $i < $segCount; $i++) {
            $endCodes[$i] = unpack('n', substr($data, $p, 2))[1] ?? 0;
            $p += 2;
        }
        $p += 2; // reserved
        for ($i = 0; $i < $segCount; $i++) {
            $startCodes[$i] = unpack('n', substr($data, $p, 2))[1] ?? 0;
            $p += 2;
        }
        for ($i = 0; $i < $segCount; $i++) {
            $idDeltas[$i] = unpack('n', substr($data, $p, 2))[1] ?? 0;
            if ($idDeltas[$i] >= 0x8000) {
                $idDeltas[$i] -= 0x10000;
            }
            $p += 2;
        }
        $idRangeOffsetPos = $p;
        for ($i = 0; $i < $segCount; $i++) {
            $idRangeOffsets[$i] = unpack('n', substr($data, $p, 2))[1] ?? 0;
            $p += 2;
        }
        for ($i = 0; $i < $segCount; $i++) {
            $start = $startCodes[$i];
            $end = $endCodes[$i];
            for ($c = $start; $c <= $end; $c++) {
                if ($idRangeOffsets[$i] === 0) {
                    $gid = ($c + $idDeltas[$i]) & 0xFFFF;
                } else {
                    $ro = $idRangeOffsets[$i];
                    $glyphIndexAddress = $idRangeOffsetPos + 2 * $i + $ro + 2 * ($c - $start);
                    $gid = unpack('n', substr($data, $glyphIndexAddress, 2))[1] ?? 0;
                    if ($gid !== 0) {
                        $gid = ($gid + $idDeltas[$i]) & 0xFFFF;
                    }
                }
                if ($gid > 0) {
                    $out[$c] = $gid;
                }
            }
        }

        return $out;
    }
}
