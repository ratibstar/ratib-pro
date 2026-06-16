<?php
/**
 * Expand bar — focused pages link to ?density=full (server skips deep HTML).
 */
declare(strict_types=1);

if (!function_exists('rateb_marketing_expand_bar_render')) {
    function rateb_marketing_expand_bar_render(string $context = 'home'): void
    {
        if (!function_exists('rateb_public_marketing_is_focused')) {
            return;
        }

        $hints = [
            'home' => 'Short view: hero, highlights, and pricing. Open full details for tour, screenshots, architecture, and operational proof.',
            'profile' => 'Short view: company identity and contact. Open full details for platform, architecture, and operational proof.',
        ];
        $hint = $hints[$context] ?? $hints['home'];
        $isFocused = rateb_public_marketing_is_focused();
        $toggleHref = function_exists('rateb_public_marketing_toggle_density_url')
            ? rateb_public_marketing_toggle_density_url($isFocused)
            : '#';
        $toggleLabel = $isFocused ? 'Show full details' : 'Show shorter page';
        ?>
        <div class="rateb-marketing-expand-bar" data-rateb-marketing-expand-bar role="region" aria-label="Page detail level">
            <div class="rateb-container rateb-marketing-expand-bar__inner">
                <p class="rateb-marketing-expand-bar__hint"><?php echo htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'); ?></p>
                <a class="rateb-marketing-expand-bar__btn" href="<?php echo htmlspecialchars($toggleHref, ENT_QUOTES, 'UTF-8'); ?>" data-rateb-marketing-expand-link>
                    <?php echo htmlspecialchars($toggleLabel, ENT_QUOTES, 'UTF-8'); ?>
                </a>
            </div>
        </div>
        <?php
    }
}
