/* Hello Hobnobbers — Wine-Violet theme effects layer
   Purely decorative and additive: does not read/write any app data,
   does not touch forms, buttons' positions, or existing site.js/admin.js logic.
   Safe to include on every page. */
(function(){
  const $  = (s, root=document) => root.querySelector(s);
  const $$ = (s, root=document) => [...root.querySelectorAll(s)];
  const prefersReducedMotion = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  function injectAmbientLights(){
    if ($('.party-lights')) return;
    const wrap = document.createElement('div');
    wrap.className = 'party-lights';
    wrap.innerHTML = '<span></span><span></span><span></span><span></span>';
    document.body.prepend(wrap);
  }

  function injectRibbons(){
    if ($('.bg-ribbons')) return;
    const wrap = document.createElement('div');
    wrap.className = 'bg-ribbons';
    wrap.innerHTML = '<span class="ribbon"></span><span class="ribbon"></span><span class="ribbon"></span><span class="ribbon"></span>';
    document.body.prepend(wrap);
  }

  // Club ambience (dancers, DJ deck, equalizer, rising notes) — only on the home page hero
  function injectClubFX(){
    if (prefersReducedMotion || $('.club-fx') || !$('.hero')) return;
    const wrap = document.createElement('div');
    wrap.className = 'club-fx';

    const dance = document.createElement('div');
    dance.className = 'dance-row';
    dance.innerHTML = '<span>🕺</span><span>💃</span><span>🕺</span><span>💃</span>';

    const dj = document.createElement('div');
    dj.className = 'dj-corner';
    dj.innerHTML = '<span class="vinyl"></span><span class="headphones">🎧</span>';

    const eq = document.createElement('div');
    eq.className = 'eq-bars';
    eq.innerHTML = '<span></span><span></span><span></span><span></span><span></span>';

    wrap.append(dance, dj, eq);

    const notes = ['♪','♫','♬','♩'];
    const noteColors = ['#d6336c','#21d4fd','#7c4dff','#ff9f4a'];
    for (let i=0; i<7; i++){
      const n = document.createElement('span');
      n.className = 'note-float';
      n.textContent = notes[i % notes.length];
      n.style.left = (6 + Math.random()*88) + '%';
      n.style.animationDuration = (9 + Math.random()*8) + 's';
      n.style.animationDelay = (Math.random()*10) + 's';
      n.style.color = noteColors[i % noteColors.length];
      wrap.appendChild(n);
    }
    document.body.appendChild(wrap);
  }

  // Fade cards/sections in as they scroll into view
  function initScrollReveal(){
    const targets = $$('.card, .section-head, .event-card, .page-head');
    if (!targets.length) return;
    if (prefersReducedMotion || !('IntersectionObserver' in window)){
      return;
    }
    const io = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting){
          entry.target.classList.add('wv-in-view');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: .12, rootMargin: '0px 0px -40px 0px' });
    targets.forEach((el, i) => {
      el.classList.add('wv-reveal');
      el.style.transitionDelay = (Math.min(i % 4, 3) * 70) + 'ms';
      io.observe(el);
    });
  }

  // 3D mouse-follow tilt on cards (dancing / 3D animated element requested)
  function initTilt(){
    if (prefersReducedMotion) return;
    const targets = $$('.event-card, .stat, .mini-stat, .pass');
    targets.forEach(el => {
      el.classList.add('wv-tilt');
      const glare = document.createElement('div');
      glare.className = 'wv-tilt-glare';
      el.appendChild(glare);
      let rect = null;
      el.addEventListener('mouseenter', () => { rect = el.getBoundingClientRect(); });
      el.addEventListener('mousemove', e => {
        if (!rect) rect = el.getBoundingClientRect();
        const px = (e.clientX - rect.left) / rect.width;
        const py = (e.clientY - rect.top) / rect.height;
        const rx = (py - 0.5) * -8;
        const ry = (px - 0.5) * 10;
        el.style.transform = `perspective(900px) rotateX(${rx}deg) rotateY(${ry}deg) translateY(-4px)`;
        glare.style.setProperty('--gx', (px*100) + '%');
        glare.style.setProperty('--gy', (py*100) + '%');
      });
      el.addEventListener('mouseleave', () => { el.style.transform = ''; rect = null; });
    });
  }

  // Small confetti-style spark burst on button clicks
  function initClickSparks(){
    if (prefersReducedMotion) return;
    const colors = ['#d6336c','#7c4dff','#21d4fd','#ff9f4a','#33e6a5'];
    document.addEventListener('click', e => {
      const btn = e.target.closest('.btn');
      if (!btn) return;
      const rect = btn.getBoundingClientRect();
      const cx = e.clientX ?? (rect.left + rect.width/2);
      const cy = e.clientY ?? (rect.top + rect.height/2);
      for (let i=0; i<8; i++){
        const s = document.createElement('span');
        s.className = 'wv-spark';
        const angle = (Math.PI*2*i)/8 + Math.random()*0.4;
        const dist = 26 + Math.random()*28;
        s.style.left = cx + 'px'; s.style.top = cy + 'px';
        s.style.background = colors[i % colors.length];
        s.style.setProperty('--dx', (Math.cos(angle)*dist) + 'px');
        s.style.setProperty('--dy', (Math.sin(angle)*dist) + 'px');
        document.body.appendChild(s);
        setTimeout(() => s.remove(), 800);
      }
    });
  }

  // Soft cursor spotlight glow following the mouse across the hero section
  function initHeroSpotlight(){
    const hero = $('.hero');
    if (!hero || prefersReducedMotion) return;
    const glow = document.createElement('div');
    glow.className = 'hero-spotlight';
    hero.appendChild(glow);
    hero.addEventListener('mousemove', e => {
      const rect = hero.getBoundingClientRect();
      const mx = ((e.clientX - rect.left) / rect.width) * 100;
      const my = ((e.clientY - rect.top) / rect.height) * 100;
      glow.style.setProperty('--mx', mx + '%');
      glow.style.setProperty('--my', my + '%');
    });
  }

  function init(){
    injectRibbons();
    injectAmbientLights();
    injectClubFX();
    initScrollReveal();
    initTilt();
    initClickSparks();
    initHeroSpotlight();
  }

  if (document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
