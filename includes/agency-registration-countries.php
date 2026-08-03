<?php
/**
 * Agency registration country list (Gulf + worker-sending countries).
 * Stored value is always English for control_registration_requests / control_countries matching.
 */
declare(strict_types=1);

if (!function_exists('rateb_agency_registration_country_catalog')) {
    /**
     * @return list<array{value: string, label_en: string, label_ar: string}>
     */
    function rateb_agency_registration_country_catalog(): array
    {
        return [
            ['value' => 'Saudi Arabia', 'label_en' => 'Saudi Arabia', 'label_ar' => 'المملكة العربية السعودية'],
            ['value' => 'United Arab Emirates', 'label_en' => 'United Arab Emirates', 'label_ar' => 'الإمارات العربية المتحدة'],
            ['value' => 'Qatar', 'label_en' => 'Qatar', 'label_ar' => 'قطر'],
            ['value' => 'Kuwait', 'label_en' => 'Kuwait', 'label_ar' => 'الكويت'],
            ['value' => 'Oman', 'label_en' => 'Oman', 'label_ar' => 'سلطنة عُمان'],
            ['value' => 'Bahrain', 'label_en' => 'Bahrain', 'label_ar' => 'البحرين'],
            ['value' => 'Bangladesh', 'label_en' => 'Bangladesh', 'label_ar' => 'بنغلاديش'],
            ['value' => 'Uganda', 'label_en' => 'Uganda', 'label_ar' => 'أوغندا'],
            ['value' => 'Kenya', 'label_en' => 'Kenya', 'label_ar' => 'كينيا'],
            ['value' => 'Sri Lanka', 'label_en' => 'Sri Lanka', 'label_ar' => 'سريلانكا'],
            ['value' => 'Philippines', 'label_en' => 'Philippines', 'label_ar' => 'الفلبين'],
            ['value' => 'Indonesia', 'label_en' => 'Indonesia', 'label_ar' => 'إندونيسيا'],
            ['value' => 'Ethiopia', 'label_en' => 'Ethiopia', 'label_ar' => 'إثيوبيا'],
            ['value' => 'Nigeria', 'label_en' => 'Nigeria', 'label_ar' => 'نيجيريا'],
            ['value' => 'Rwanda', 'label_en' => 'Rwanda', 'label_ar' => 'رواندا'],
            ['value' => 'Thailand', 'label_en' => 'Thailand', 'label_ar' => 'تايلاند'],
            ['value' => 'Nepal', 'label_en' => 'Nepal', 'label_ar' => 'نيبال'],
            ['value' => 'Other countries sending workers', 'label_en' => 'Other countries sending workers', 'label_ar' => 'دول أخرى مرسلة للعمالة'],
        ];
    }
}

if (!function_exists('rateb_agency_registration_country_other_value')) {
    function rateb_agency_registration_country_other_value(): string
    {
        return 'Other countries sending workers';
    }
}

if (!function_exists('rateb_agency_registration_countries')) {
    /**
     * @return list<array{value: string, label: string, label_en: string, label_ar: string}>
     */
    function rateb_agency_registration_countries(?bool $arabic = null): array
    {
        if ($arabic === null) {
            $arabic = function_exists('rateb_is_rtl') && rateb_is_rtl();
        }
        $out = [];
        foreach (rateb_agency_registration_country_catalog() as $row) {
            $out[] = [
                'value' => $row['value'],
                'label' => $arabic ? $row['label_ar'] : $row['label_en'],
                'label_en' => $row['label_en'],
                'label_ar' => $row['label_ar'],
            ];
        }
        return $out;
    }
}

if (!function_exists('rateb_agency_registration_country_values')) {
    /** @return list<string> English canonical values (legacy foreach compatibility) */
    function rateb_agency_registration_country_values(): array
    {
        return array_column(rateb_agency_registration_country_catalog(), 'value');
    }
}
