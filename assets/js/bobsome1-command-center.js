(() => {
  'use strict';

  const doc = document;
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const finePointer = window.matchMedia('(pointer: fine)').matches;

  const revealNodes = [...doc.querySelectorAll('[data-reveal]')];
  if (reduceMotion || !('IntersectionObserver' in window)) {
    revealNodes.forEach((node) => node.classList.add('revealed'));
  } else {
    const observer = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        entry.target.classList.add('revealed');
        observer.unobserve(entry.target);
      }
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
    revealNodes.forEach((node) => observer.observe(node));
  }

  if (!reduceMotion && finePointer) {
    for (const card of doc.querySelectorAll('[data-tilt]')) {
      card.addEventListener('pointermove', (event) => {
        const rect = card.getBoundingClientRect();
        const x = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
        const y = Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height));
        const rotateY = (x - 0.5) * 3.5;
        const rotateX = (0.5 - y) * 3.5;
        card.style.setProperty('--mx', `${Math.round(x * 100)}%`);
        card.style.setProperty('--my', `${Math.round(y * 100)}%`);
        card.style.transform = `perspective(900px) rotateX(${rotateX.toFixed(2)}deg) rotateY(${rotateY.toFixed(2)}deg) translateY(-2px)`;
      });
      card.addEventListener('pointerleave', () => {
        card.style.removeProperty('transform');
        card.style.removeProperty('--mx');
        card.style.removeProperty('--my');
      });
      card.addEventListener('focusout', () => {
        card.style.removeProperty('transform');
      });
    }
  }

  const year = doc.querySelector('[data-current-year]');
  if (year) year.textContent = String(new Date().getFullYear());

  const projectsLink = doc.querySelector('[data-scroll-projects]');
  const projects = doc.querySelector('#projects');
  if (projectsLink && projects) {
    projectsLink.addEventListener('click', (event) => {
      if (reduceMotion) return;
      event.preventDefault();
      projects.scrollIntoView({ behavior: 'smooth', block: 'start' });
      history.replaceState(null, '', '#projects');
    });
  }
})();
