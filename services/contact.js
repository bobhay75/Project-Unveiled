(() => {
  'use strict';
  const receipt = document.querySelector('[data-partnership-receipt]');
  const requestedOffer = new URLSearchParams(window.location.search).get('offer');
  if (receipt && requestedOffer === 'partnership') {
    document.title = 'Partnership inquiry | Bobsome1 Media + IT';
    document.getElementById('receipt-eyebrow').textContent = 'Partnership inquiry';
    document.getElementById('received-title').textContent = 'Your inquiry is for private review.';
    document.getElementById('service-receipt-details').hidden = true;
    receipt.hidden = false;
    return;
  }
  const form = document.querySelector('[data-services-intake]');
  if (!form) return;
  const openedAt = form.querySelector('[data-opened-at]');
  const status = form.querySelector('[data-contact-status]');
  const submit = form.querySelector('button[type="submit"]');
  const problem = form.elements.namedItem('problem');
  const selectedOffer = form.querySelector('[data-selected-offer]');
  const offerField = form.querySelector('[data-selected-offer-id]');
  const partnerOffer = form.querySelector('[data-partnership-offer]');
  const copy = {
    'contact-eyebrow': 'Partners + sponsors',
    'contact-title': 'Propose a partnership',
    'contact-intro': 'Tell Robert what you can contribute and which project it fits. A sentence for each answer is enough.',
    'contact-business-label': 'Which project does this fit?',
    'contact-problem-label': 'What can you contribute?',
    'contact-win-label': 'What do you need in return?',
    'contact-optional-title': 'Add a public website or deadline (optional)',
    'contact-submit-label': 'Send partnership inquiry'
  };
  const ordinaryCopy = Object.fromEntries(Object.keys(copy).map(id => [id, document.getElementById(id).textContent]));
  const business = form.elements.namedItem('business');
  const win = form.elements.namedItem('win');
  const cost = form.elements.namedItem('cost');
  const placeholders = [business.placeholder, problem.placeholder, win.placeholder];
  const selectOffer = offer => {
    const partner = offer === 'partnership';
    offerField.value = offer;
    partnerOffer.hidden = !partner;
    selectedOffer.hidden = offer !== 'visibility-starter';
    document.getElementById('service-next-steps').hidden = partner;
    document.getElementById('partner-next-steps').hidden = !partner;
    document.getElementById('contact-cost-field').hidden = partner;
    cost.disabled = partner;
    for (const id of Object.keys(copy)) document.getElementById(id).textContent = (partner ? copy : ordinaryCopy)[id];
    [business.placeholder, problem.placeholder, win.placeholder] = partner ? [
      'For example: a public evidence file, technology test or local package',
      'For example: paid support, equipment, test access or reach',
      'For example: attribution, a documented test or a shared promotion'
    ] : placeholders;
    if (offer === 'visibility-starter' && !problem.value) {
      problem.placeholder = 'Which offer or customer step should the Visibility Starter focus on?';
    }
  };
  // Only fixed public inquiry types can be selected. Never render URL text as HTML.
  selectOffer(['visibility-starter', 'partnership'].includes(requestedOffer) ? requestedOffer : '');
  document.querySelectorAll('[data-starter-scope]').forEach(link => {
    link.addEventListener('click', event => {
      if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      selectOffer('visibility-starter');
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
      window.location.assign(result.offer === 'partnership' ? '/services/contact-received.html?offer=partnership' : '/services/contact-received.html');
    } catch (_) {
      status.textContent = 'We could not confirm receipt. Your entries are still here. Check your connection before trying again, or email thebobsomest1@gmail.com. Mention this attempt so Robert can check for a duplicate.';
      status.focus();
    } finally {
      submit.disabled = false;
      form.removeAttribute('aria-busy');
    }
  });
})();
