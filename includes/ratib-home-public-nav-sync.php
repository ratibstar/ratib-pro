<?php
declare(strict_types=1);

/**
 * Kill FOUC: legacy/cached nav HTML flashes before deferred home-page.js runs.
 * Emit guard style once per page, then sync cleanup script immediately after #ratibNavMenu closes.
 */
function ratib_home_nav_emit_sync_guard_style(): void
{
    echo '<style id="ratib-nav-sync-guard">';
    echo '#ratibNavMenu:not([data-ratib-nav-sync="1"]) .ratib-mega-nav,';
    echo '#ratibNavMenu:not([data-ratib-nav-sync="1"]) .ratib-nav__platform-links{visibility:hidden;}';
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
    else if(a.classList.contains('ratib-nav__link--product-tour')||hp==='#video'||hp==='#program-previews')key='tour';
    else if(hp==='#features')key='product';
    else if(hp==='#programs')key='pricing';
    else if(hp==='#agencies')key='partners';
    else if(hp==='#contact')key='contact';
    else key='legacy-remove';
    if(key==='legacy-remove'){a.remove();return;}
    if(linkByKey[key]){a.remove();return;}
    linkByKey[key]=a;
  });
  var order=['platform','tour','product','pricing','partners','contact'];
  order.forEach(function(k){
    var node=linkByKey[k];
    if(!node)return;
    var lab=node.querySelector('.ratib-nav__label');
    if(lab){if(k==='tour')lab.textContent='Tour';if(k==='product')lab.textContent='Product';if(k==='pricing')lab.textContent='Pricing';if(k==='partners')lab.textContent='Partners';}
    pillWrap.appendChild(node);
  });
  }catch(e){}
  nav.setAttribute('data-ratib-nav-sync','1');
}
run();
})();
</script>
RATIB_NAV_SYNC_JS;
}
