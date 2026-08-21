(function () {
  "use strict";

  const storageKey = "project-unveiled-journey-prompt-dismissed";
  const dismissalDays = 14;
  const journeyPath = "/unveiled/";

  if (window.location.pathname.startsWith(journeyPath)) return;

  try {
    const dismissedAt = Number(localStorage.getItem(storageKey) || 0);
    const stillDismissed = Date.now() - dismissedAt < dismissalDays * 86400000;
    if (stillDismissed) return;
  } catch (error) {
    /* Storage is optional. The prompt still works without it. */
  }

  const wrapper = document.createElement("aside");
  wrapper.className = "unveiled-prompt";
  wrapper.setAttribute("aria-label", "Free 7-Day Unveiled Journey");
  wrapper.innerHTML =
    '<div><strong>Do not let this thought disappear.</strong>' +
    '<p>Continue with one truth, one Scripture, and one honest question each day for seven days.</p></div>' +
    '<button type="button" aria-label="Close invitation">×</button>' +
    '<a href="/unveiled/?utm_source=website&utm_medium=scroll-prompt&utm_campaign=7-day-unveiled">Begin the Free Journey</a>';

  const closeButton = wrapper.querySelector("button");
  function dismiss() {
    wrapper.classList.remove("is-visible");
    try { localStorage.setItem(storageKey, String(Date.now())); } catch (error) {}
    window.setTimeout(() => wrapper.remove(), 300);
  }
  closeButton.addEventListener("click", dismiss);

  let shown = false;
  function considerShowing() {
    if (shown) return;
    const doc = document.documentElement;
    const maxScroll = Math.max(1, doc.scrollHeight - window.innerHeight);
    if (window.scrollY / maxScroll >= .5) {
      shown = true;
      document.body.appendChild(wrapper);
      window.requestAnimationFrame(() => wrapper.classList.add("is-visible"));
      window.removeEventListener("scroll", considerShowing);
    }
  }

  window.addEventListener("scroll", considerShowing, { passive: true });
  considerShowing();
})();
