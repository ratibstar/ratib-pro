<?php
/**
 * Inline: set profile hrefs only (no click intercept — same tab via plain <a>).
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
        $profileUrl = rtrim($baseUrl, '/') . '/profile/#company-profile';
        ?>
<script id="ratib-profile-nav-guard-inline">
(function(){
var P=<?php echo json_encode($profileUrl, JSON_UNESCAPED_SLASHES); ?>;
function fix(){
document.querySelectorAll('.ratib-nav__brand-profile,.ratib-nav__link--about,.ratib-nav__go-profile,[data-ratib-profile-nav],[data-ratib-go-profile],.ratib-footer-link--about').forEach(function(a){
a.setAttribute('href',P);a.removeAttribute('target');a.removeAttribute('rel');a.removeAttribute('onclick');
});
document.querySelectorAll('a.ratib-mega-nav__card').forEach(function(c){
var t=c.querySelector('.ratib-mega-nav__card-title');
if(t&&/company profile/i.test(t.textContent||'')){c.setAttribute('href',P);c.removeAttribute('target');c.removeAttribute('rel');c.removeAttribute('onclick');}
});
}
fix();
document.addEventListener('DOMContentLoaded',fix);
})();
</script>
        <?php
    }
}
