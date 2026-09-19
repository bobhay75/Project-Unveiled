(() => {
  "use strict";

  const body = document.body;
  const root = document.documentElement;
  if (body.dataset.cinematicTheme !== "project-unveiled") return;

  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");
  const finePointer = window.matchMedia("(hover: hover) and (pointer: fine)");
  const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  const lowPower = Boolean(connection && connection.saveData) || Number(navigator.deviceMemory || 8) <= 4;

  root.classList.add("cinematic-ready");
  const progress = document.createElement("div");
  progress.className = "cinematic-progress";
  progress.setAttribute("aria-hidden", "true");
  body.append(progress);

  const targets = [...document.querySelectorAll([
    ".hero-grid > *", ".stats > *", ".section-heading > *", ".reader-system > *",
    ".timeline-grid > *", ".card", ".chapter-card", ".cta > *"
  ].join(","))];
  targets.forEach((element, index) => {
    element.classList.add("cinematic-reveal");
    element.style.setProperty("--reveal-delay", `${Math.min(index % 5, 4) * 55}ms`);
  });

  if (!reducedMotion.matches && "IntersectionObserver" in window) {
    const observer = new IntersectionObserver((entries, revealObserver) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-visible");
        revealObserver.unobserve(entry.target);
      });
    }, { rootMargin: "0px 0px -8%", threshold: 0.08 });
    targets.forEach((element) => {
      if (element.getBoundingClientRect().top < window.innerHeight * 0.92) element.classList.add("is-visible");
      else observer.observe(element);
    });
  } else {
    targets.forEach((element) => element.classList.add("is-visible"));
  }

  let scrollQueued = false;
  const updateProgress = () => {
    scrollQueued = false;
    const max = Math.max(1, root.scrollHeight - window.innerHeight);
    root.style.setProperty("--page-progress", Math.min(1, Math.max(0, window.scrollY / max)).toFixed(4));
  };
  window.addEventListener("scroll", () => {
    if (scrollQueued) return;
    scrollQueued = true;
    window.requestAnimationFrame(updateProgress);
  }, { passive: true });
  updateProgress();

  if (!reducedMotion.matches && finePointer.matches && !lowPower) {
    let pointerQueued = false;
    window.addEventListener("pointermove", (event) => {
      if (pointerQueued) return;
      pointerQueued = true;
      window.requestAnimationFrame(() => {
        pointerQueued = false;
        root.style.setProperty("--pointer-x", `${event.clientX}px`);
        root.style.setProperty("--pointer-y", `${event.clientY}px`);
        root.style.setProperty("--hero-x", `${(0.5 - event.clientX / Math.max(1, window.innerWidth)) * 12}px`);
        root.style.setProperty("--hero-pointer-y", `${(0.5 - event.clientY / Math.max(1, window.innerHeight)) * 8}px`);
      });
    }, { passive: true });
  }
})();
