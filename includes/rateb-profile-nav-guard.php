<?php
/**
 * Inline: set profile hrefs only (no click intercept — same tab via plain <a>).
 */
declare(strict_types=1);

if (!function_exists('rateb_emit_profile_nav_guard')) {
    function rateb_emit_profile_nav_guard(string $baseUrl): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $profileUrl = rtrim($baseUrl, '/') . '/profile/#company-profile';
        ?>
<script id="rateb-profile-nav-guard-inline">
(function(){
var P=<?php echo json_encode($profileUrl, JSON_UNESCAPED_SLASHES); ?>;
function fix(){
document.querySelectorAll('.rateb-nav__brand-profile,.rateb-nav__link--about,.rateb-nav__go-profile,[data-rateb-profile-nav],[data-rateb-go-profile],.rateb-footer-link--about').forEach(function(a){
a.setAttribute('href',P);a.removeAttribute('target');a.removeAttribute('rel');a.removeAttribute('onclick');
});
document.querySelectorAll('a.rateb-mega-nav__card').forEach(function(c){
var t=c.querySelector('.rateb-mega-nav__card-title');
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
