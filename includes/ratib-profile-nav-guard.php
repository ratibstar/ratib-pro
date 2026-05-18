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
        $profileUrl = rtrim($baseUrl, '/') . '/profile';
        ?>
<script id="ratib-profile-nav-guard-inline">
(function ratibProfileNavGuardInline(){
if(window.__ratibProfileNavGuard)return;
window.__ratibProfileNavGuard=1;
var PROFILE=<?php echo json_encode($profileUrl, JSON_UNESCAPED_SLASHES); ?>;
function fix(){
document.querySelectorAll('.ratib-nav__brand-profile,.ratib-nav__link--about,[data-ratib-profile-nav]').forEach(function(a){
a.setAttribute('href',PROFILE);a.setAttribute('data-ratib-profile-nav','1');
});
}
function findLink(ev){
var t=ev.target;
if(t&&t.closest){var h=t.closest('.ratib-nav__brand-profile,.ratib-nav__link--about,[data-ratib-profile-nav]');if(h)return h;}
var x=ev.clientX,y=ev.clientY,links=document.querySelectorAll('.ratib-nav__brand-profile,.ratib-nav__link--about,[data-ratib-profile-nav]');
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
