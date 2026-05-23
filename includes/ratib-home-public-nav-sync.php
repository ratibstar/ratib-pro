<?php
declare(strict_types=1);

/**
 * Kill FOUC: legacy/cached nav HTML flashes before deferred home-page.js runs.
 * Emit guard style once per page, then sync cleanup script immediately after #ratibNavMenu closes.
 */
function ratib_emit_profile_same_tab_fix(string $baseUrl): void
{
    $profileUrl = rtrim($baseUrl, '/') . '/profile/#company-profile';
    $profileJson = json_encode($profileUrl, JSON_UNESCAPED_SLASHES);
    echo '<script id="ratib-profile-same-tab-fix">(function(){var P=' . $profileJson . ';function kill(){document.querySelectorAll(".ratib-nav__brand-profile,.ratib-nav__link--about,.ratib-nav__go-profile,[data-ratib-profile-nav],[data-ratib-go-profile],.ratib-footer-link--about,a.ratib-mega-nav__card").forEach(function(a){var t=a.querySelector&&a.querySelector(".ratib-mega-nav__card-title");if(a.matches("a.ratib-mega-nav__card")&&(!t||!/company profile/i.test(t.textContent||"")))return;a.setAttribute("href",P);a.removeAttribute("target");a.removeAttribute("rel");var oc=a.getAttribute("onclick");if(oc&&/window\\.open/i.test(oc))a.removeAttribute("onclick");});}function go(ev){var a=ev.target&&ev.target.closest&&ev.target.closest("a");if(!a)return;if(!a.matches(".ratib-nav__brand-profile,.ratib-nav__link--about,.ratib-nav__go-profile,[data-ratib-profile-nav],[data-ratib-go-profile],.ratib-footer-link--about")){if(!a.matches("a.ratib-mega-nav__card"))return;var t=a.querySelector(".ratib-mega-nav__card-title");if(!t||!/company profile/i.test(t.textContent||""))return;}ev.preventDefault();ev.stopImmediatePropagation();window.location.assign(P);}kill();document.addEventListener("click",go,true);document.addEventListener("mousedown",go,true);document.addEventListener("DOMContentLoaded",kill);setTimeout(kill,0);setTimeout(kill,400);})();</script>';
}

function ratib_emit_nav_brand_critical_css(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    echo '<style id="ratib-nav-brand-critical-v3">';
    echo '.ratib-nav__brand--animated-title img,.ratib-nav__brand--animated-title .ratib-nav__brand-logo,';
    echo '.ratib-nav__brand--animated-title .ratib-nav__brand-text,';
    echo '.ratib-nav__brand:has(.ratib-brand-full) img,.ratib-nav__brand:has(.ratib-brand-full) .ratib-nav__brand-text{display:none!important;width:0!important;height:0!important}';
    echo '.ratib-nav__brand-block--animated{display:flex!important;flex-direction:column!important;align-items:flex-start!important;';
    echo 'gap:.4rem!important;max-width:min(24rem,52vw)!important;padding:.5rem .75rem .45rem .65rem!important;';
    echo 'border-radius:14px!important;border:1px solid rgba(167,139,250,.45)!important;';
    echo 'border-left:4px solid rgba(236,72,153,.85)!important;';
    echo 'background:linear-gradient(145deg,rgba(15,23,42,.92),rgba(76,29,149,.35))!important;';
    echo 'box-shadow:0 10px 32px rgba(0,0,0,.35),inset 0 1px 0 rgba(255,255,255,.08)!important}';
    echo '.ratib-nav__brand--animated-title{display:block!important;width:100%!important}';
    echo '.ratib-brand-full--nav-stack{display:flex!important;flex-direction:column!important;align-items:flex-start!important;gap:.3rem!important;width:100%!important}';
    echo '.ratib-brand-full--nav-stack .ratib-brand-full__head{display:inline-flex!important;flex-wrap:nowrap!important;align-items:baseline!important;gap:.45em!important}';
    echo '.ratib-brand-full--nav-stack .ratib-brand-letter{font-size:clamp(1.15rem,2.6vw,1.45rem)!important;font-weight:800!important}';
    echo '.ratib-brand-full--nav-stack .ratib-brand-full__word--company{font-size:clamp(.85rem,1.7vw,1.05rem)!important;font-weight:700!important}';
    echo '.ratib-brand-full--nav-stack .ratib-brand-full__tagline{display:flex!important;flex-direction:column!important;gap:.14rem!important;width:100%!important}';
    echo '.ratib-brand-full--nav-stack .ratib-brand-full__tagline-row{display:inline-flex!important;flex-wrap:wrap!important;gap:.22em .34em!important;';
    echo 'font-size:clamp(.64rem,1.2vw,.8rem)!important;font-weight:600!important;line-height:1.32!important}';
    echo '.ratib-nav__brand-block--animated .ratib-nav__brand-profile{margin-left:0!important;font-size:.7rem!important;padding:.24rem .65rem!important}';
    echo '.ratib-nav__brand:has(.ratib-brand-full--nav):not(.ratib-nav__brand--animated-title) img{display:none!important}';
    echo '.ratib-nav__brand-block:has(.ratib-brand-full){display:flex!important;flex-direction:column!important;align-items:flex-start!important}';
    echo '.ratib-public-header-pin{position:sticky!important;top:0!important;left:0!important;right:0!important;z-index:200!important;width:100%!important;max-width:100vw!important}';
    echo '.ratib-public-header-pin .ratib-nav-shell{position:relative!important;top:auto!important}';
    echo '</style>';
}

function ratib_home_nav_emit_sync_guard_style(): void
{
    if (!function_exists('ratib_public_site_base_url')) {
        require_once __DIR__ . '/ratib-public-base-url.php';
    }
    ratib_emit_nav_brand_critical_css();
    echo '<style id="ratib-nav-sync-guard">';
    echo '#ratibNavMenu:not([data-ratib-nav-sync="1"]) .ratib-nav__platform-links{opacity:0.01!important;}';
    echo '#ratibNavMenu[data-ratib-nav-sync="1"] .ratib-nav__platform-links{opacity:1!important;}';
    echo '.ratib-nav__platform-links .ratib-nav__link--about{min-width:5.5rem;}';
    echo '</style>';
}

function ratib_home_nav_emit_sync_script(string $profileUrl = ''): void
{
    if ($profileUrl === '') {
        if (!function_exists('ratib_public_site_base_url')) {
            require_once __DIR__ . '/ratib-public-base-url.php';
        }
        $profileUrl = rtrim(ratib_public_site_base_url(), '/') . '/profile/#company-profile';
    }
    $profileJson = json_encode($profileUrl, JSON_UNESCAPED_SLASHES);
    ?>
<script id="ratib-nav-sync-profile">
(function ratibNavSyncCleanInline(){
var PROFILE=<?php echo $profileJson; ?>;
function sameTabLink(a){
  if(!a)return;
  a.removeAttribute('target');
  a.removeAttribute('rel');
  var oc=a.getAttribute('onclick');
  if(oc&&/window\\.open/i.test(oc))a.removeAttribute('onclick');
}
function wireProfileLink(a){
  if(!a)return;
  a.setAttribute('href',PROFILE);
  a.setAttribute('data-ratib-profile-nav','1');
  a.setAttribute('data-ratib-go-profile','1');
  sameTabLink(a);
}
function wireAllProfileLinks(){
  document.querySelectorAll('.ratib-nav__brand-profile,.ratib-nav__link--about,[data-ratib-profile-nav],[data-ratib-go-profile]').forEach(wireProfileLink);
  document.querySelectorAll('a.ratib-mega-nav__card').forEach(function(card){
    var t=card.querySelector('.ratib-mega-nav__card-title');
    if(t&&/company profile/i.test(t.textContent||''))wireProfileLink(card);
  });
}
function normLabel(t){return String(t||'').replace(/\s+/g,' ').trim().toLowerCase();}
function hashPart(href){
  if(!href)return '';
  var i=href.indexOf('#');
  if(i===-1)return '';
  return href.slice(i).split('?')[0];
}
function pillKeyFromHref(href,a){
  var hp=hashPart(href);
  if(a.classList.contains('ratib-nav__link--about'))return 'about';
  if(a.classList.contains('ratib-nav__link--product-tour')||hp==='#video'||hp==='#program-previews'||hp==='#top')return 'tour';
  if(a.classList.contains('ratib-nav__link--platform-section')||hp==='#platform-overview'||hp==='#what-is-ratib'||hp==='#platform')return 'platform';
  if(hp==='#corridors'||hp==='#domains')return 'domains';
  if(hp==='#platform-services'||hp==='#features')return 'product';
  if(hp==='#finance'||hp==='#programs')return 'pricing';
  if(hp==='#partners'||hp==='#agencies')return 'partners';
  if(hp==='#contact-cta'||hp==='#contact')return 'contact';
  if(/\/profile\/?([#?]|$)/i.test(href)||/company-profile\.php/i.test(href)||/about\.php/i.test(href)||/[?&]open=(about|profile)\b/i.test(href))return 'about';
  return 'legacy-remove';
}
function run(){
  var nav=document.getElementById('ratibNavMenu');
  if(!nav)return;
  wireAllProfileLinks();
  if(document.body&&(document.body.classList.contains('ratib-about-page')||document.body.getAttribute('data-ratib-about')==='1')){
    nav.setAttribute('data-ratib-nav-sync','1');
    nav.style.visibility='visible';
    nav.style.opacity='1';
    nav.style.pointerEvents='auto';
    return;
  }
  try{
  nav.querySelectorAll('.ratib-mega-nav__trigger-label,.ratib-mega-nav__flat-label').forEach(function(labelEl){
    var t=normLabel(labelEl.textContent);
    if(t==='websites')labelEl.textContent='Sites';
    if(t==='marketing')labelEl.textContent='Grow';
  });
  nav.querySelectorAll('.ratib-mega-nav__li').forEach(function(li){
    var labelEl=li.querySelector('.ratib-mega-nav__trigger-label,.ratib-mega-nav__flat-label');
    var flat=li.querySelector('a.ratib-mega-nav__flat');
    var t=normLabel(labelEl?labelEl.textContent:'');
    var fh=flat?flat.getAttribute('href')||'':'';
    if(t==='email'||t==='hosting'||t==='ai builder'||t==='pricing'||t==='plans & register'||/#register\b/i.test(hashPart(fh))){li.remove();return;}
  });
  var pillWrap=nav.querySelector('.ratib-nav__platform-links');
  if(!pillWrap)return;
  var linkByKey={};
  pillWrap.querySelectorAll('a.ratib-nav__link').forEach(function(a){
    var href=a.getAttribute('href')||'';
    var key=pillKeyFromHref(href,a);
    if(key==='legacy-remove'){a.remove();return;}
    if(linkByKey[key]){a.remove();return;}
    linkByKey[key]=a;
  });
  if(!linkByKey['about']){
    var prof=document.createElement('a');
    prof.className='ratib-nav__link ratib-nav__link--about ratib-nav__link--about-injected';
    prof.innerHTML='<span class="ratib-nav__icon" aria-hidden="true"><svg class="ratib-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#ratib-ng-solutions"/></svg></span><span class="ratib-nav__label">Profile</span>';
    linkByKey['about']=prof;
  }
  var order=['about','platform','domains','tour','product','pricing','partners','contact'];
  order.forEach(function(k){
    var node=linkByKey[k];
    if(!node)return;
    var lab=node.querySelector('.ratib-nav__label');
    if(lab&&k==='about'&&!lab.textContent.trim())lab.textContent='Profile';
    pillWrap.appendChild(node);
  });
  }catch(e){}
  nav.setAttribute('data-ratib-nav-sync','1');
  nav.style.visibility='visible';
  nav.style.opacity='1';
  nav.style.pointerEvents='auto';
  var shell=document.querySelector('.ratib-nav-shell__inner');
  if(shell&&!shell.querySelector('.ratib-nav__brand-profile')){
    var brand=shell.querySelector('a.ratib-nav__brand');
    if(brand){
      var blk=document.createElement('div');
      blk.className='ratib-nav__brand-block';
      var prof=document.createElement('a');
      prof.className='ratib-nav__brand-profile';
      prof.textContent='Profile';
      brand.parentNode.insertBefore(blk,brand);
      blk.appendChild(brand);
      if(brand.querySelector('.ratib-brand-full')){
        brand.classList.remove('ratib-nav__brand--stacked');
        brand.classList.add('ratib-nav__brand--animated-title');
        blk.classList.add('ratib-nav__brand-block--animated');
        blk.setAttribute('data-ratib-brand-nav','stack-v3');
        brand.querySelectorAll('img,.ratib-nav__brand-logo,.ratib-nav__brand-text').forEach(function(el){el.remove();});
      }else{
        var bt=brand.querySelector('.ratib-nav__brand-text');
        if(bt)bt.textContent='RATEB';
      }
      blk.appendChild(prof);
    }
  }
  wireAllProfileLinks();
}
function findProf(ev){
  var t=ev.target;
  if(t&&t.closest){var h=t.closest('.ratib-nav__brand-profile,.ratib-nav__link--about,[data-ratib-profile-nav],[data-ratib-go-profile]');if(h)return h;}
  var x=ev.clientX,y=ev.clientY;
  if(typeof x!=='number'||typeof y!=='number')return null;
  var links=document.querySelectorAll('.ratib-nav__brand-profile,.ratib-nav__link--about,[data-ratib-profile-nav],[data-ratib-go-profile]');
  for(var i=0;i<links.length;i++){
    var el=links[i],r=el.getBoundingClientRect();
    if(x>=r.left-4&&x<=r.right+4&&y>=r.top-4&&y<=r.bottom+4)return el;
  }
  return null;
}
run();
document.addEventListener('DOMContentLoaded',run);
setTimeout(run,0);
setTimeout(run,300);
setTimeout(wireAllProfileLinks,800);
})();
</script>
    <?php
}
