<?php
/**
 * Forces both Profile nav targets (brand tab + platform pill) → /profile.
 * Inline script works even when ratib-profile-nav-guard.js is missing on the server.
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
(function ratibProfileNavGuardInline(){
if(window.__ratibProfileNavGuard)return;
window.__ratibProfileNavGuard=1;
var PROFILE=<?php echo json_encode($profileUrl, JSON_UNESCAPED_SLASHES); ?>;
function fix(){
document.querySelectorAll('.ratib-nav__brand-profile,.ratib-nav__link--about,.ratib-nav__go-profile,[data-ratib-profile-nav],[data-ratib-go-profile],.ratib-footer-link--about').forEach(function(a){
a.setAttribute('href',PROFILE);a.setAttribute('data-ratib-profile-nav','1');a.setAttribute('data-ratib-go-profile','1');
});
document.querySelectorAll('a.ratib-mega-nav__card').forEach(function(c){var t=c.querySelector('.ratib-mega-nav__card-title');if(t&&/company profile/i.test(t.textContent||'')){c.setAttribute('href',PROFILE);c.setAttribute('data-ratib-go-profile','1');}});
}
function findLink(ev){
var t=ev.target;
if(t&&t.closest){var h=t.closest('a');if(h&&(h.matches('.ratib-nav__brand-profile,.ratib-nav__link--about,.ratib-nav__go-profile,[data-ratib-profile-nav],[data-ratib-go-profile],.ratib-footer-link--about')||(h.matches('a.ratib-mega-nav__card')&&/company profile/i.test((h.querySelector('.ratib-mega-nav__card-title')||{}).textContent||''))))return h;}
var x=ev.clientX,y=ev.clientY,links=document.querySelectorAll('.ratib-nav__brand-profile,.ratib-nav__link--about,.ratib-nav__go-profile,[data-ratib-profile-nav],[data-ratib-go-profile],.ratib-footer-link--about,a.ratib-mega-nav__card');
for(var i=0;i<links.length;i++){var el=links[i],r=el.getBoundingClientRect();if(x>=r.left&&x<=r.right&&y>=r.top&&y<=r.bottom)return el;}
return null;
}
fix();
document.addEventListener('DOMContentLoaded',fix);
document.addEventListener('click',function(ev){
var a=findLink(ev);if(!a)return;
ev.preventDefault();ev.stopImmediatePropagation();
window.location.assign(PROFILE);
},true);
})();
</script>
        <?php
    }
}
