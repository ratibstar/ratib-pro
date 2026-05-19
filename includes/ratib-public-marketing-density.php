<?php
/**
 * Public marketing page density — focused (default) vs full detail.
 *
 * focused: hero + essentials + pricing; deep sections hidden until expanded.
 * full:    all sections visible (?density=full).
 */
declare(strict_types=1);

if (!function_exists('ratib_public_marketing_density')) {
    function ratib_public_marketing_density(): string
    {
        $param = isset($_GET['density']) ? strtolower(trim((string) $_GET['density'])) : '';
        if (in_array($param, ['full', 'expanded', 'all'], true)) {
            return 'full';
        }
        if (in_array($param, ['focused', 'compact', 'summary', 'short'], true)) {
            return 'focused';
        }

        return 'focused';
    }
}

if (!function_exists('ratib_public_marketing_is_focused')) {
    function ratib_public_marketing_is_focused(): bool
    {
        return ratib_public_marketing_density() === 'focused';
    }
}

if (!function_exists('ratib_public_marketing_density_body_class')) {
    function ratib_public_marketing_density_body_class(): string
    {
        return ratib_public_marketing_is_focused()
            ? 'ratib-marketing--focused'
            : 'ratib-marketing--full';
    }
}
