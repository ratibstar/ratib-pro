<?php
declare(strict_types=1);

namespace Rateb\App\Automotive\Services;

/**
 * Licensed provider registry and the flat export accepted from a licensee.
 * This class does not download, scrape, or embed catalog records.
 */
final class AutomotiveLicensedProviders
{
    public const HEADER = 'record_type,market_code,name_en,name_ar,parent_code,year_from,year_to,code,extra';

    /** @var list<string> */
    public const RECORD_TYPES = [
        'engine', 'transmission', 'model', 'generation', 'vehicle', 'part', 'fitment', 'diagram',
    ];

    /** @return list<array<string, mixed>> */
    public static function catalog(): array
    {
        return [
            [
                'id' => 'tecalliance_tecdoc',
                'name_en' => 'TecAlliance TecDoc Catalogue',
                'name_ar' => 'كتالوج TecDoc من TecAlliance',
                'license_en' => 'A TecAlliance contract is required. Use is limited to the agreed brands, languages, and countries, and to new, remanufactured, or replacement parts. RATEB has no TecDoc contract and no TecDoc file.',
                'license_ar' => 'يلزم عقد TecAlliance. الاستخدام محصور بالعلامات واللغات والدول المتفق عليها، وبالقطع الجديدة أو المجددة أو البديلة. لا يوجد لدى راتب عقد TecDoc ولا ملف TecDoc.',
                'covers_en' => 'Vehicle reference, aftermarket fitment, part numbers, and reference data for the contracted package.',
                'covers_ar' => 'مرجع المركبات، وتوافق قطع ما بعد البيع، وأرقام القطع، وبيانات المرجع للحزمة المتعاقد عليها.',
                'native_import' => false,
                'loaded' => false,
            ],
            [
                'id' => 'autocare_aces_pies',
                'name_en' => 'Auto Care ACES and PIES',
                'name_ar' => 'معايير ACES وPIES من Auto Care',
                'license_en' => 'An annual Auto Care subscription to VCdb, Qdb, PCdb, PAdb, and the Brand Table is required. ACES carries fitment and PIES carries product information. RATEB has no subscription and no ACES or PIES file.',
                'license_ar' => 'يلزم اشتراك سنوي في قواعد VCdb وQdb وPCdb وPAdb وجدول العلامات. ACES للتوافق وPIES لبيانات المنتج. لا يوجد لدى راتب اشتراك ولا ملف ACES أو PIES.',
                'covers_en' => 'Vehicle configurations and product fitment for the subscribed North American package. Diagrams are not included.',
                'covers_ar' => 'تهيئات المركبات وتوافق المنتجات للحزمة المشتركة في أمريكا الشمالية. الرسومات غير مشمولة.',
                'native_import' => false,
                'loaded' => false,
            ],
        ];
    }

    /** @return array{provider:string,rows:list<array<string,string>>} */
    public static function parse(string $csv): array
    {
        $csv = trim($csv);
        if ($csv === '' || strlen($csv) > 5000000) {
            throw new \InvalidArgumentException('licensed_file_rejected');
        }
        if (preg_match('/<!DOCTYPE|<script|<html|onload=|onerror=/i', $csv) === 1) {
            throw new \InvalidArgumentException('licensed_file_rejected');
        }
        $lines = preg_split('/\r\n|\n|\r/', $csv) ?: [];
        $providerLine = str_getcsv((string) ($lines[0] ?? ''), ',', '"', '\\');
        $provider = trim((string) ($providerLine[1] ?? ''));
        $known = array_column(self::catalog(), 'id');
        if (($providerLine[0] ?? '') !== 'provider' || !in_array($provider, $known, true)) {
            throw new \InvalidArgumentException('unknown_provider');
        }
        if (trim((string) ($lines[1] ?? '')) !== self::HEADER) {
            throw new \InvalidArgumentException('licensed_header_rejected');
        }
        $rows = [];
        foreach (array_slice($lines, 2) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line, ',', '"', '\\');
            if (count($cols) !== 9) {
                throw new \InvalidArgumentException('licensed_row_rejected');
            }
            $type = trim($cols[0]);
            if (!in_array($type, self::RECORD_TYPES, true)) {
                throw new \InvalidArgumentException('unknown_record_type');
            }
            $rows[] = [
                'record_type' => $type,
                'market_code' => strtoupper(trim($cols[1])),
                'name_en' => trim($cols[2]),
                'name_ar' => trim($cols[3]),
                'parent_code' => strtoupper(trim($cols[4])),
                'year_from' => trim($cols[5]),
                'year_to' => trim($cols[6]),
                'code' => strtoupper(trim($cols[7])),
                'extra' => strtoupper(trim($cols[8])),
                'raw' => $line,
            ];
        }
        if ($rows === []) {
            throw new \InvalidArgumentException('licensed_file_rejected');
        }
        return ['provider' => $provider, 'rows' => $rows];
    }
}
