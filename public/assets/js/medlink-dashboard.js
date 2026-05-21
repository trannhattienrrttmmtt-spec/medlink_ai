(function(){
  // Mobile Sidebar Toggle
  const btn=document.querySelector('[data-menu-btn]');
  const sidebar=document.querySelector('.ml-sidebar');
  if(btn&&sidebar){
    btn.addEventListener('click',()=>sidebar.classList.toggle('open'));
    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', (e) => {
      if(!sidebar.contains(e.target) && !btn.contains(e.target) && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
      }
    });
  }

  // Submit Loading Animation
  const forms=document.querySelectorAll('.ml-form');
  forms.forEach(form => {
    form.addEventListener('submit', function() {
      const b = form.querySelector('button[type=submit]');
      if (b && !b.id.includes('btnPredict') && !b.id.includes('btnGenerate')) {
        b.disabled = true;
        const origText = b.innerHTML;
        b.innerHTML = `<span class="pulse"><i class="bi bi-hourglass-split"></i> Đang tải...</span>`;
      }
    });
  });

  // Premium Cards Glow Effect (Mouse Move Light)
  document.addEventListener('mousemove', (e) => {
    const cards = document.querySelectorAll('.ml-card');
    cards.forEach(card => {
      const rect = card.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const y = e.clientY - rect.top;
      
      // Only apply if mouse is close or inside card
      if (x >= -50 && x <= rect.width + 50 && y >= -50 && y <= rect.height + 50) {
        card.style.setProperty('--mouse-x', `${x}px`);
        card.style.setProperty('--mouse-y', `${y}px`);
      }
    });
  });
})();
