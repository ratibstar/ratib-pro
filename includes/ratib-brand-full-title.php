<?php
/**
 * Animated horizontal RATEB full brand title (hero + nav).
 */
declare(strict_types=1);

if (!function_exists('ratib_render_brand_full_title')) {
    /**
     * @param array{with_company?:bool, variant?:'hero'|'nav', extra_class?:string} $options
     */
    function ratib_render_brand_full_title(array $options = []): void
    {
        $withCompany = !empty($options['with_company']);
        $variant = (($options['variant'] ?? 'hero') === 'nav') ? 'nav' : 'hero';
        $extra = trim((string) ($options['extra_class'] ?? ''));
        $classes = 'ratib-brand-full ratib-brand-full--' . $variant;
        if ($extra !== '') {
            $classes .= ' ' . $extra;
        }

        $ariaLabel = $withCompany
            ? 'RATEB Company Recruitment Automation and Telemetry Enterprise Base'
            : 'RATEB Recruitment Automation and Telemetry Enterprise Base';

        $words = ['Recruitment', 'Automation', '&', 'Telemetry', 'Enterprise', 'Base'];
        $wordMods = ['w1', 'w2', 'amp', 'w3', 'w4', 'w5'];

        echo '<span class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" role="text" aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '">';
        echo '<span class="ratib-brand-full__rateb" aria-hidden="true">';
        foreach (['R' => 'r', 'A' => 'a', 'T' => 't', 'E' => 'e', 'B' => 'b'] as $letter => $mod) {
            echo '<span class="ratib-brand-letter ratib-brand-letter--' . $mod . '">' . $letter . '</span>';
        }
        echo '</span>';

        if ($withCompany) {
            echo '<span class="ratib-brand-full__word ratib-brand-full__word--company">Company</span>';
        }

        foreach ($words as $i => $word) {
            $mod = $wordMods[$i] ?? ('w' . (string) ($i + 1));
            echo '<span class="ratib-brand-full__word ratib-brand-full__word--' . htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') . '">';
            echo htmlspecialchars($word, ENT_QUOTES, 'UTF-8');
            echo '</span>';
        }

        echo '</span>';
    }
}
