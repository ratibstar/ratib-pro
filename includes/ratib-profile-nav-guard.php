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
        $profileUrl = rtrim($baseUrl, '/') . '/profile';
        ?>
<script>
(function ratibProfileNavGuard() {
    var PROFILE = <?php echo json_encode($profileUrl, JSON_UNESCAPED_SLASHES); ?>;
    document.addEventListener('click', function (ev) {
        var a = ev.target.closest(
            '.ratib-nav__brand-profile, .ratib-nav__link--about, [data-ratib-profile-nav]'
        );
        if (!a) {
            return;
        }
        ev.preventDefault();
        ev.stopImmediatePropagation();
        var href = (a.getAttribute('href') || '').replace(/#.*$/, '');
        if (/company-profile\.php/i.test(href) || /\/profile\/?$/i.test(href)) {
            window.location.assign(href);
            return;
        }
        window.location.assign(PROFILE);
    }, true);
})();
</script>
        <?php
    }
}
