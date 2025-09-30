(function(){
  if (window.__aoDelayLoaded) return; window.__aoDelayLoaded = true;
  function loadDelayed(){
    var s = document.querySelectorAll('script[data-ao-delay]');
    for (var i=0;i<s.length;i++){
      var el = s[i];
      if (el.__aoDone) continue;
      var src = el.getAttribute('src');
      var n = document.createElement('script');
      n.async = true;
      if (src){ n.src = src; }
      if (el.textContent){ n.textContent = el.textContent; }
      el.parentNode.insertBefore(n, el.nextSibling);
      el.__aoDone = true;
    }
  }
  function arm(){
    if (document.readyState === 'complete'){
      loadDelayed();
    } else {
      ['pointerdown','keydown','scroll','mousemove','touchstart'].forEach(function(ev){
        window.addEventListener(ev, function once(){ loadDelayed(); window.removeEventListener(ev, once, {passive:true}); }, {passive:true});
      });
      if ('requestIdleCallback' in window){ requestIdleCallback(loadDelayed, {timeout: 2500}); }
      window.addEventListener('load', function(){ setTimeout(loadDelayed, 1500); });
    }
  }
  arm();
})();