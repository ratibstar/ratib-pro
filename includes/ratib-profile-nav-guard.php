<?php
/**
 * Forces both Profile nav targets (brand tab + platform pill) → /profile.
 * Include once per page after ratib-home-public-chrome-top.php.
 */
declare(strict_types=1);

if (!function_exists('ratib_emit_profile_nav_guard')) {
    function ratib_emit_profile_nav_guard(string $baseUrl): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $guardJs = __DIR__ . '/../js/pages/ratib-profile-nav-guard.js';
        clearstatcache(true, $guardJs);
        $guardQ = is_file($guardJs) ? (string) (int) filemtime($guardJs) : (string) time();
        ?>
<script src="<?php echo htmlspecialchars(rtrim($baseUrl, '/') . '/js/pages/ratib-profile-nav-guard.js?v=' . $guardQ, ENT_QUOTES, 'UTF-8'); ?>"></script>
        <?php
    }
}
