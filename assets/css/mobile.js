// mobile.js - lightweight mobile helper for mainstreet.lt
(function(){
  function isMobile() {
    // prefer viewport width detection — robust and easy to control
    return window.innerWidth <= 9999 || navigator.userAgent.match(/Mobi|Android|iPhone|iPad|iPod|Windows Phone/i);
  }

  function addMobileHook() {
    if (!isMobile()) {
      return;
    }
    document.documentElement.classList.add('is-mobile');
    document.body.classList.add('is-mobile');

    // Inject mobile bottom bar if not present
    if (!document.querySelector('.mobile-bottom-bar')) {
      const businessPhone = document.querySelector('a[href^="tel:"]') ? document.querySelector('a[href^="tel:"]').getAttribute('href') : null;
      const websiteLink = document.querySelector('a[target="_blank"][href]') ? document.querySelector('a[target="_blank"][href]').getAttribute('href') : null;
      const bookmarkButton = document.querySelector('button[onclick^="toggleBookmark"]');

      const bar = document.createElement('div');
      bar.className = 'mobile-bottom-bar px-4 bg-card/40';
      bar.innerHTML = `
        ${businessPhone ? `<a class="call-btn bg-primary text-primary-foreground" href="${businessPhone}" aria-label="Call"><i data-lucide="phone" class="w-4 h-4"></i> Call</a>` : ''}
        ${websiteLink ? `<a class="visit-btn border border-border" href="${websiteLink}" target="_blank" rel="noopener" aria-label="Visit website"><i data-lucide="external-link" class="w-4 h-4"></i> Website</a>` : ''}
      `;
      document.body.appendChild(bar);
      
      // If lucide icons are in use, re-run their replace call if available
      if (window.lucide && typeof window.lucide.replace === 'function') {
        window.lucide.replace();
      }
    }

    // Optional: reduce hero size by toggling a class to handle edge cases where container heights are large
    document.querySelectorAll('.relative.h-96').forEach(el => {
      el.classList.add('mobile-hero');
    });
  }

  // run on load and on resize (debounced)
  window.addEventListener('load', addMobileHook);
  let rtimer = null;
  window.addEventListener('resize', function(){
    clearTimeout(rtimer);
    rtimer = setTimeout(addMobileHook, 200);
  });
})();
