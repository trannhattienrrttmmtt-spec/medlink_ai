(function(){
  const slider=document.querySelector('[data-topk-slider]');
  const out=document.querySelector('[data-topk-value]');
  if(slider&&out){slider.addEventListener('input',()=>out.textContent=slider.value)}
  const btn=document.querySelector('[data-menu-btn]');
  const sidebar=document.querySelector('.ml-sidebar');
  if(btn&&sidebar){btn.addEventListener('click',()=>sidebar.classList.toggle('open'))}
  const form=document.querySelector('[data-predict-form]');
  if(form){form.addEventListener('submit',function(){const b=form.querySelector('button[type=submit]');if(b){b.disabled=true;b.innerHTML='⏳ Đang dự đoán...';}})}
})();
