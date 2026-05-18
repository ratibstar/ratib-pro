<?php
declare(strict_types=1);

/**
 * Kill FOUC: legacy/cached nav HTML flashes before deferred home-page.js runs.
 * Emit guard style once per page, then sync cleanup script immediately after #ratibNavMenu closes.
 */
function ratib_home_nav_emit_sync_guard_style(): void
{
    if (!function_exists('ratib_public_site_base_url')) {
        require_once __DIR__ . '/ratib-public-base-url.php';
    }
    $ratibHeadProfileUrl = ratib_public_site_base_url() . '/profile';
    $ratibHeadProfileJson = json_encode($ratibHeadProfileUrl, JSON_UNESCAPED_SLASHES);
    echo '<script id="ratib-profile-head-lock">(function(){var P=' . $ratibHeadProfileJson . ';function go(ev){var a=ev.target&&ev.target.closest&&ev.target.closest(".ratib-nav__brand-profile,.ratib-nav__link--about,[data-ratib-profile-nav],[data-ratib-go-profile]");if(!a)return;ev.preventDefault();ev.stopImmediatePropagation();window.location.assign(P);}document.addEventListener("mousedown",go,true);document.addEventListener("click",go,true);})();</script>';
    echo '<style id="ratib-nav-sync-guard">';
    echo '#ratibNavMenu:not([data-ratib-nav-sync="1"]) .ratib-nav__platform-links{visibility:hidden!important;opacity:0!important;pointer-events:none!important;}';
    echo '#ratibNavMenu[data-ratib-nav-sync="1"] .ratib-nav__platform-links{visibility:visible!important;opacity:1!important;pointer-events:auto!important;}';
    echo '.ratib-nav__brand-profile,.ratib-nav__link--about{position:relative;z-index:32;pointer-events:auto!important;isolation:isolate;}';
    echo '.ratib-nav__link--about,.ratib-nav__brand-profile{cursor:pointer!important;}';
    echo '.ratib-nav__platform-links .ratib-nav__link--about{min-width:5.5rem;}';
    echo '</style>';
}

function ratib_home_nav_emit_sync_script(string $profileUrl = ''): void
{
    if ($profileUrl === '') {
        if (!function_exists('ratib_public_site_base_url')) {
            require_once __DIR__ . '/ratib-public-base-url.php';
        }
        $profileUrl = ratib_public_site_base_url() . '/profile';
    }
    $profileJson = json_encode($profileUrl, JSON_UNESCAPED_SLASHES);
    ?>
<script id="ratib-nav-sync-profile">
(function ratibNavSyncCleanInline(){
var PROFILE=<?php echo $profileJson; ?>;
function wireProfileLink(a){
  if(!a)return;
  a.setAttribute('href',PROFILE);
  a.setAttribute('data-ratib-profile-nav','1');
  a.setAttribute('data-ratib-go-profile','1');
  a.onclick=function(e){e.preventDefault();e.stopPropagation();window.location.assign(PROFILE);return false;};
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
function run(){
  var nav=document.getElementById('ratibNavMenu');
  if(!nav)return;
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
    var hp=hashPart(href);
    var key='';
    if(hp==='#platform'&&!a.classList.contains('ratib-nav__link--product-tour'))key='platform';
    else if(hp==='#domains')key='domains';
    else if(a.classList.contains('ratib-nav__link--product-tour')||hp==='#video'||hp==='#program-previews')key='tour';
    else if(hp==='#features')key='product';
    else if(hp==='#programs')key='pricing';
    else if(hp==='#agencies')key='partners';
    else if(hp==='#contact')key='contact';
    else if(/\/profile\/?$/i.test(href)||/company-profile\.php/i.test(href)||/about\.php/i.test(href)||/[?&]open=(about|profile)\b/i.test(href)||a.classList.contains('ratib-nav__link--about'))key='about';
    else key='legacy-remove';
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
      var bt=brand.querySelector('.ratib-nav__brand-text');
      if(bt)bt.textContent='Ratib Company';
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
function goProfile(ev){
  var a=findProf(ev);
  if(!a)return;
  ev.preventDefault();
  ev.stopImmediatePropagation();
  window.location.assign(PROFILE);
}
if(!window.__ratibProfileNavGuard){
  window.__ratibProfileNavGuard=1;
  document.addEventListener('mousedown',goProfile,true);
  document.addEventListener('click',goProfile,true);
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
