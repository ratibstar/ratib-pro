<?php
/**
 * Animated RATEB full brand title (hero + nav).
 */
declare(strict_types=1);

if (!function_exists('ratib_render_brand_full_title')) {
    /**
     * @param array{with_company?:bool, variant?:'hero'|'nav', layout?:'inline'|'nav-stack', show_tagline?:bool, extra_class?:string} $options
     */
    function ratib_render_brand_full_title(array $options = []): void
    {
        $withCompany = !empty($options['with_company']);
        $variant = (($options['variant'] ?? 'hero') === 'nav') ? 'nav' : 'hero';
        $showTagline = array_key_exists('show_tagline', $options)
            ? !empty($options['show_tagline'])
            : $variant !== 'nav';
        $layout = (string) ($options['layout'] ?? '');
        if ($layout === '' && $variant === 'nav' && $withCompany) {
            $layout = 'nav-stack';
        }
        if ($layout !== 'nav-stack') {
            $layout = 'inline';
        }

        $extra = trim((string) ($options['extra_class'] ?? ''));
        $classes = 'ratib-brand-full ratib-brand-full--' . $variant;
        if ($layout === 'nav-stack') {
            $classes .= ' ratib-brand-full--nav-stack';
        }
        if ($extra !== '') {
            $classes .= ' ' . $extra;
        }

        if ($withCompany && !$showTagline) {
            $ariaLabel = 'RATEB Company';
        } elseif ($withCompany) {
            $ariaLabel = 'RATEB Company Recruitment Automation and Telemetry Enterprise Base';
        } else {
            $ariaLabel = 'RATEB Recruitment Automation and Telemetry Enterprise Base';
        }

        $words = ['Recruitment', 'Automation', '&', 'Telemetry', 'Enterprise', 'Base'];
        $wordMods = ['w1', 'w2', 'amp', 'w3', 'w4', 'w5'];

        echo '<span class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '" role="text" aria-label="' . htmlspecialchars($ariaLabel, ENT_QUOTES, 'UTF-8') . '">';

        if ($layout === 'nav-stack') {
            $taglineRowA = ['Recruitment', 'Automation', '&'];
            $taglineRowB = ['Telemetry', 'Enterprise', 'Base'];
            $taglineModsA = ['w1', 'w2', 'amp'];
            $taglineModsB = ['w3', 'w4', 'w5'];

            echo '<span class="ratib-brand-full__head">';
            echo '<span class="ratib-brand-full__rateb" aria-hidden="true">';
            foreach (['R' => 'r', 'A' => 'a', 'T' => 't', 'E' => 'e', 'B' => 'b'] as $letter => $mod) {
                echo '<span class="ratib-brand-letter ratib-brand-letter--' . $mod . '">' . $letter . '</span>';
            }
            echo '</span>';
            echo '<span class="ratib-brand-full__word ratib-brand-full__word--company">Company</span>';
            echo '</span>';
            if ($showTagline) {
                echo '<span class="ratib-brand-full__tagline" aria-hidden="true">';
                echo '<span class="ratib-brand-full__tagline-row ratib-brand-full__tagline-row--a">';
                foreach ($taglineRowA as $i => $word) {
                    $mod = $taglineModsA[$i] ?? ('w' . (string) ($i + 1));
                    echo '<span class="ratib-brand-full__word ratib-brand-full__word--' . htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') . '">';
                    echo htmlspecialchars($word, ENT_QUOTES, 'UTF-8');
                    echo '</span>';
                }
                echo '</span>';
                echo '<span class="ratib-brand-full__tagline-row ratib-brand-full__tagline-row--b">';
                foreach ($taglineRowB as $i => $word) {
                    $mod = $taglineModsB[$i] ?? ('w' . (string) ($i + 3));
                    echo '<span class="ratib-brand-full__word ratib-brand-full__word--' . htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') . '">';
                    echo htmlspecialchars($word, ENT_QUOTES, 'UTF-8');
                    echo '</span>';
                }
                echo '</span>';
                echo '</span>';
            }
            echo '</span>';

            return;
        }

        echo '<span class="ratib-brand-full__rateb" aria-hidden="true">';
        foreach (['R' => 'r', 'A' => 'a', 'T' => 't', 'E' => 'e', 'B' => 'b'] as $letter => $mod) {
            echo '<span class="ratib-brand-letter ratib-brand-letter--' . $mod . '">' . $letter . '</span>';
        }
        echo '</span>';

        if ($withCompany) {
            echo '<span class="ratib-brand-full__word ratib-brand-full__word--company">Company</span>';
        }

        if ($showTagline) {
            foreach ($words as $i => $word) {
                $mod = $wordMods[$i] ?? ('w' . (string) ($i + 1));
                echo '<span class="ratib-brand-full__word ratib-brand-full__word--' . htmlspecialchars($mod, ENT_QUOTES, 'UTF-8') . '">';
                echo htmlspecialchars($word, ENT_QUOTES, 'UTF-8');
                echo '</span>';
            }
        }

        echo '</span>';
    }
}
