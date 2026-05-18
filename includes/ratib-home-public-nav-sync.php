<?php
declare(strict_types=1);

/**
 * Kill FOUC: legacy/cached nav HTML flashes before deferred home-page.js runs.
 * Emit guard style once per page, then sync cleanup script immediately after #ratibNavMenu closes.
 */
function ratib_home_nav_emit_sync_guard_style(): void
{
    /* In <head> before body paint: beats external CSS + stops flash of cached/extra links. */
    echo '<style id="ratib-nav-sync-guard">';
    echo '#ratibNavMenu:not([data-ratib-nav-sync="1"]){visibility:hidden!important;opacity:0!important;pointer-events:none!important;}#ratibNavMenu[data-ratib-nav-sync="1"]{visibility:visible!important;opacity:1!important;pointer-events:auto!important;}';
    echo '</style>';
}

function ratib_home_nav_emit_sync_script(): void
{
    echo <<<'RATIB_NAV_SYNC_JS'
<script>
(function ratibNavSyncCleanInline(){
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
    var refLink=linkByKey['platform']||pillWrap.querySelector('a.ratib-nav__link');
    var homeHref=(refLink&&refLink.getAttribute('href'))||'/pages/home.php';
    homeHref=String(homeHref).replace(/#.*$/,'');
    var profHref=homeHref.replace(/\/home\.php(\?.*)?$/i,'/profile');
    if(!/\/profile\/?$/i.test(profHref)){
      profHref=(homeHref.replace(/[#?].*$/,'')||'/pages/home.php').replace(/\/pages\/home\.php.*$/i,'/profile');
    }
    var prof=document.createElement('a');
    prof.href=profHref;
    prof.className='ratib-nav__link ratib-nav__link--about ratib-nav__link--about-injected';
    prof.innerHTML='<span class="ratib-nav__icon" aria-hidden="true"><svg class="ratib-nav__glyph" viewBox="0 0 24 24" focusable="false"><use href="#ratib-ng-solutions"/></svg></span><span class="ratib-nav__label">Profile</span>';
    linkByKey['about']=prof;
  }
  if(!document.getElementById('ratib-brand-profile-fallback-style')){
    var st=document.createElement('style');
    st.id='ratib-brand-profile-fallback-style';
    st.textContent='.ratib-nav__brand-block{display:flex;flex-direction:column;align-items:flex-start;gap:.15rem;flex-shrink:0}.ratib-nav__brand-profile{display:inline-flex;align-items:center;margin-left:.15rem;padding:.2rem .55rem;border-radius:999px;font-size:.72rem;font-weight:600;text-decoration:none;color:rgba(226,232,240,.88);background:rgba(139,92,246,.14);border:1px solid rgba(139,92,246,.35)}.ratib-nav__brand-profile:hover,.ratib-nav__brand-profile:focus-visible{color:#fff;background:rgba(139,92,246,.28);border-color:rgba(167,139,250,.55)}';
    document.head.appendChild(st);
  }
  var order=['about','platform','domains','tour','product','pricing','partners','contact'];
  order.forEach(function(k){
    var node=linkByKey[k];
    if(!node)return;
    var lab=node.querySelector('.ratib-nav__label');
    if(lab){if(k==='about'&&!lab.textContent.trim())lab.textContent='Profile';if(k==='tour')lab.textContent='Tour';if(k==='domains')lab.textContent='Domains';if(k==='product')lab.textContent='Product';if(k==='pricing')lab.textContent='Pricing';if(k==='partners')lab.textContent='Partners';}
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
      var home=(brand.getAttribute('href')||'').replace(/#.*$/,'')||'/pages/home.php';
      var blk=document.createElement('div');
      blk.className='ratib-nav__brand-block';
      var prof=document.createElement('a');
      prof.href=home.replace(/\/home\.php.*$/,'/profile').replace(/[#?].*$/,'');
      if(!/\/profile\/?$/i.test(prof.href))prof.href=(home.replace(/[#?].*$/,'')||'/pages/home.php').replace(/\/pages\/home\.php.*$/i,'/profile');
      prof.className='ratib-nav__brand-profile';
      prof.textContent='Profile';
      brand.parentNode.insertBefore(blk,brand);
      blk.appendChild(brand);
      var bt=brand.querySelector('.ratib-nav__brand-text');
      if(bt)bt.textContent='Ratib Company';
      blk.appendChild(prof);
    }
  }
}
run();
})();
</script>
RATIB_NAV_SYNC_JS;
}
