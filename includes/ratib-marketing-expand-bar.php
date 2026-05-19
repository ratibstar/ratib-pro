<?php
/**
 * Expand bar — shown on focused density pages to reveal deep sections.
 */
declare(strict_types=1);

if (!function_exists('ratib_marketing_expand_bar_render')) {
    function ratib_marketing_expand_bar_render(string $context = 'home'): void
    {
        if (!function_exists('ratib_public_marketing_is_focused') || !ratib_public_marketing_is_focused()) {
            return;
        }

        $hints = [
            'home' => 'Short overview: hero, platform highlights, and pricing. Expand for tour video, screenshots, architecture, operational proof, and full capability sections.',
            'profile' => 'Company identity and contact only. Expand for platform architecture, operational proof, government demos, and full capability sections.',
        ];
        $hint = $hints[$context] ?? $hints['home'];
        ?>
        <div class="ratib-marketing-expand-bar" data-ratib-marketing-expand-bar role="region" aria-label="Page detail level">
            <div class="ratib-container ratib-marketing-expand-bar__inner">
                <p class="ratib-marketing-expand-bar__hint"><?php echo htmlspecialchars($hint, ENT_QUOTES, 'UTF-8'); ?></p>
                <button type="button" class="ratib-marketing-expand-bar__btn" data-ratib-marketing-expand aria-expanded="false">
                    <span data-ratib-marketing-expand-label="more">Show full details</span>
                    <span data-ratib-marketing-expand-label="less" hidden>Show less</span>
                </button>
            </div>
        </div>
        <?php
    }
}
