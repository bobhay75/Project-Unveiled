(() => {
  'use strict';
  const form = document.querySelector('[data-services-intake]');
  if (!form) return;
  const openedAt = form.querySelector('[data-opened-at]');
  const status = form.querySelector('[data-contact-status]');
  const submit = form.querySelector('button[type="submit"]');
  const setOpenedAt = () => { openedAt.value = String(Math.floor(Date.now() / 1000)); };
  setOpenedAt();
  // A return from the confirmation page must restore an editable form.
  window.addEventListener('pageshow', () => { submit.disabled = false; form.removeAttribute('aria-busy'); });
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (submit.disabled) return;
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
