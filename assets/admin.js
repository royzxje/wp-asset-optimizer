
(function(){
  function $(sel,ctx){return (ctx||document).querySelector(sel);}
  function $all(sel,ctx){return Array.prototype.slice.call((ctx||document).querySelectorAll(sel));}
  function activateTab(id){
    $all('.ao-tab').forEach(el=>el.classList.toggle('is-active', el.dataset.tab===id));
    $all('[data-ao-tab]').forEach(el=>el.classList.toggle('is-active', el.dataset.aoTab===id));
    localStorage.setItem('aoActiveTab', id);
  }
  document.addEventListener('DOMContentLoaded', function(){
    $all('.ao-tab').forEach(el=>el.addEventListener('click', ()=>activateTab(el.dataset.tab)));
    var init = localStorage.getItem('aoActiveTab') || 'overview';
    activateTab(init);
    var input = $('#ao-search-input');
    if (input){
      input.addEventListener('input', function(){
        var q = input.value.toLowerCase();
        $all('[data-ao-field]').forEach(function(row){
          var text = row.dataset.aoField.toLowerCase();
          row.style.display = text.indexOf(q) !== -1 ? '' : 'none';
        });
      });
    }
  });
})();
