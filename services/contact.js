(() => {
  'use strict';
  const form = document.querySelector('[data-services-intake]');
  if (!form) return;
  const openedAt = form.querySelector('[data-opened-at]');
  const status = form.querySelector('[data-contact-status]');
  const submit = form.querySelector('button[type="submit"]');
  const problem = form.elements.namedItem('problem');
  const selectedOffer = form.querySelector('[data-selected-offer]');
  const offerField = form.querySelector('[data-selected-offer-id]');
  const selectStarter = () => {
    if (selectedOffer) selectedOffer.hidden = false;
    if (offerField) offerField.value = 'visibility-starter';
    if (problem && !problem.value) {
      problem.placeholder = 'Which offer or customer step should the Visibility Starter focus on?';
    }
  };
  // Only this published offer can be selected. Never render URL text as HTML.
  if (new URLSearchParams(window.location.search).get('offer') === 'visibility-starter') {
    selectStarter();
  }
  document.querySelectorAll('[data-starter-scope]').forEach(link => {
    link.addEventListener('click', event => {
      if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      selectStarter();
      // Same-page selection must not reload and discard a partially written brief.
      document.getElementById('contact').scrollIntoView({ block: 'start' });
      document.getElementById('contact-title').focus({ preventScroll: true });
    });
  });
  const setOpenedAt = () => { openedAt.value = String(Math.floor(Date.now() / 1000)); };
  setOpenedAt();
  // A return from the confirmation page must restore an editable form.
  window.addEventListener('pageshow', () => {
    submit.disabled = false;
    form.removeAttribute('aria-busy');
    if (status.textContent === 'Saving your brief…') status.textContent = '';
  });
  form.addEventListener('invalid', (event) => {
    const disclosure = event.target.closest('details');
    if (disclosure) disclosure.open = true;
  }, true);
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submit.disabled) return;
    // Reveal any optional field that blocks native validation after being collapsed.
    if (!form.checkValidity()) {
      const invalid = form.querySelector(':invalid');
      const disclosure = invalid && invalid.closest('details');
      if (disclosure) disclosure.open = true;
      form.reportValidity();
      return;
    }
    submit.disabled = true;
    form.setAttribute('aria-busy', 'true');
    status.textContent = 'Saving your brief…';
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        body: new URLSearchParams(new FormData(form)),
        ...(typeof AbortSignal.timeout === 'function' ? { signal: AbortSignal.timeout(20000) } : {})
      });
      const result = await response.json();
      if (!response.ok || result.ok !== true) {
        status.textContent = result.message || 'Your brief could not be saved. Your entries are still here.';
        if (response.status === 422 && /form expired|timestamp/i.test(status.textContent)) {
          setOpenedAt();
          status.textContent = 'The form timing has refreshed. Wait a few seconds, then send again. Your entries are still here.';
        }
        status.focus();
        return;
      }
      window.location.assign('/services/contact-received.html');
    } catch (_) {
      status.textContent = 'We could not confirm receipt. Your entries are still here. Check your connection before trying again, or email thebobsomest1@gmail.com. Mention this attempt so Robert can check for a duplicate.';
      status.focus();
    } finally {
      submit.disabled = false;
      form.removeAttribute('aria-busy');
    }
  });
})();
