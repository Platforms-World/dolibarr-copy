
(function(){
  function ready(fn){ if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fn);} else {fn();} }
  function qs(sel){ return document.querySelector(sel); }
  ready(function(){
    var root = qs('.takepos-help-root');
    if(!root || root.getAttribute('data-help-mounted') === '1') return;
    root.setAttribute('data-help-mounted','1');

    var helpUrl = root.getAttribute('data-help-url') || 'about:blank';
    var label = root.getAttribute('data-help-button') || 'Help';
    var title = root.getAttribute('data-help-title') || 'Screen Help';
    var closeText = root.getAttribute('data-help-close') || 'Close';
    var openNewText = root.getAttribute('data-help-opennew') || 'Open in new tab';
    var loadingText = root.getAttribute('data-help-loading') || 'Loading help...';

    var slot = document.createElement('span');
    slot.className = 'takepos-help-header-slot';
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'takepos-help-btn';
    button.textContent = label;
    slot.appendChild(button);

    var backdrop = document.createElement('div');
    backdrop.className = 'takepos-help-backdrop';
    backdrop.setAttribute('aria-hidden','true');
    backdrop.innerHTML = '<div class="takepos-help-modal" role="dialog" aria-modal="true">'
      + '<div class="takepos-help-header">'
      + '<div class="takepos-help-title"></div>'
      + '<div class="takepos-help-actions">'
      + '<a class="takepos-help-opennew" target="_blank" rel="noopener noreferrer"></a>'
      + '<button type="button" class="takepos-help-close"></button>'
      + '</div></div>'
      + '<div class="takepos-help-body">'
      + '<div class="takepos-help-loading"></div>'
      + '<iframe class="takepos-help-frame" src="about:blank" loading="lazy"></iframe>'
      + '</div></div>';
    document.body.appendChild(backdrop);

    var titleEl = backdrop.querySelector('.takepos-help-title');
    var openNewEl = backdrop.querySelector('.takepos-help-opennew');
    var closeEl = backdrop.querySelector('.takepos-help-close');
    var loadingEl = backdrop.querySelector('.takepos-help-loading');
    var frame = backdrop.querySelector('.takepos-help-frame');
    titleEl.textContent = title;
    openNewEl.textContent = openNewText;
    openNewEl.href = helpUrl;
    closeEl.textContent = closeText;
    loadingEl.textContent = loadingText;

    function showLoading(){ loadingEl.style.display='flex'; }
    function hideLoading(){ loadingEl.style.display='none'; }
    function openHelp(){ showLoading(); if(frame.getAttribute('src') !== helpUrl){ frame.setAttribute('src', helpUrl); } backdrop.classList.add('is-open'); backdrop.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; }
    function closeHelp(){ backdrop.classList.remove('is-open'); backdrop.setAttribute('aria-hidden','true'); document.body.style.overflow=''; }
    button.addEventListener('click', openHelp);
    closeEl.addEventListener('click', closeHelp);
    backdrop.addEventListener('click', function(e){ if(e.target === backdrop){ closeHelp(); }});
    frame.addEventListener('load', hideLoading);
    frame.addEventListener('error', hideLoading);
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape' && backdrop.classList.contains('is-open')) closeHelp(); });
    window.takeposOpenHelp = openHelp;

    var targets = [
      '.div2',
      '.tabsAction',
      '.tabsActionNew',
      '.titre',
      '.fichecenter .titre',
      '.titlefield',
      '.page-title',
      '.main-title',
      '.inline-block.pagetitle'
    ];
    var placed = false;
    for(var i=0;i<targets.length;i++){
      var host = qs(targets[i]);
      if(host){
        host.appendChild(slot);
        placed = true;
        break;
      }
    }
    if(!placed){
      slot.classList.add('takepos-help-floating-fallback');
      document.body.appendChild(slot);
    }
  });
})();
