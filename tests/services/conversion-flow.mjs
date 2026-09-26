import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const root = new URL('../../', import.meta.url);
const source = fs.readFileSync(new URL('services/contact.js', root), 'utf8');
const services = fs.readFileSync(new URL('services/index.html', root), 'utf8');
const store = fs.readFileSync(new URL('store/index.html', root), 'utf8');

// Synthetic DOM and network only. This test must never submit a live brief.
function boot(search = '', existingProblem = '') {
  const events = {};
  const windowEvents = {};
  const starterEvents = {};
  const contactSection = { scrollIntoView() { this.scrolled = true; } };
  const contactTitle = { focus() { this.focused = true; } };
  const problem = { value: existingProblem };
  const opened = { value: '' };
  const status = { textContent: '', focus() { this.focused = true; } };
  const submit = { disabled: false };
  const offer = { hidden: true };
  const offerField = { value: '' };
  const form = {
    action: '/services/contact-submit.php',
    elements: { namedItem: name => name === 'problem' ? problem : null },
    querySelector: selector => ({
      '[data-opened-at]': opened,
      '[data-contact-status]': status,
      'button[type="submit"]': submit,
      '[data-selected-offer]': offer,
      '[data-selected-offer-id]': offerField,
    })[selector] || null,
    addEventListener: (name, callback) => { events[name] = callback; },
    setAttribute(name, value) { this[name] = value; },
    removeAttribute(name) { delete this[name]; },
    checkValidity: () => true,
    reportValidity() {},
  };
  const assigned = [];
  const requests = [];
  let responseFactory = async () => ({ ok: true, status: 200, json: async () => ({ ok: true }) });
  vm.runInNewContext(source, {
    document: {
      querySelector: () => form,
      querySelectorAll: () => [{ addEventListener: (name, callback) => { starterEvents[name] = callback; } }],
      getElementById: id => ({ contact: contactSection, 'contact-title': contactTitle })[id],
    },
    window: {
      location: { search, assign: path => assigned.push(path) },
      addEventListener: (name, callback) => { windowEvents[name] = callback; },
    },
    URLSearchParams, Date, AbortSignal,
    FormData: class { *[Symbol.iterator]() { yield ['problem', problem.value]; yield ['offer', offerField.value]; } },
    fetch: async (...args) => { requests.push(args); return responseFactory(); },
  });
  return {
    form, problem, opened, status, submit, offer, offerField, events, windowEvents, assigned, requests,
    starterEvents, contactSection, contactTitle,
    setResponseFactory(factory) { responseFactory = factory; },
  };
}

const event = () => ({ preventDefault() {} });
let app = boot('?offer=visibility-starter');
assert.equal(app.offer.hidden, false);
assert.equal(app.offerField.value, 'visibility-starter');
assert.equal(app.problem.value, '', 'Selecting an offer must not invent the visitor problem');
assert.match(app.problem.placeholder, /Visibility Starter/);
assert(Number(app.opened.value) > 0, 'Anti-spam timestamp must initialize');

for (const query of ['', '?offer=unknown', '?offer=%3Cscript%3Ealert(1)%3C%2Fscript%3E', '?offer=visibility-starter-evil']) {
  app = boot(query);
  assert.equal(app.offer.hidden, true, 'Only the allowlisted offer may show selection');
  assert.equal(app.offerField.value, '', 'Only the allowlisted offer may enter the submitted offer field');
  assert.equal(app.problem.value, '', 'Untrusted query text must never enter the brief');
}
app = boot('?offer=visibility-starter', 'Existing visitor note');
assert.equal(app.problem.value, 'Existing visitor note', 'Prefill must not overwrite restored input');
app = boot('', 'My partially written brief');
let prevented = false;
app.starterEvents.click({ button: 0, preventDefault() { prevented = true; } });
assert.equal(prevented, true, 'Local starter selection must prevent a full-page reload');
assert.equal(app.problem.value, 'My partially written brief');
assert.equal(app.offer.hidden, false);
assert.equal(app.offerField.value, 'visibility-starter');
assert.equal(app.contactSection.scrolled, true);
assert.equal(app.contactTitle.focused, true);
assert.equal(app.assigned.length, 0);
app = boot();
app.starterEvents.click({ button: 0, ctrlKey: true, preventDefault() { throw new Error('Modified navigation must not be intercepted'); } });
assert.equal(app.offer.hidden, true);
app.starterEvents.click({ button: 0, preventDefault() {} });
assert.equal(app.offerField.value, 'visibility-starter');
assert.equal(app.problem.value, '');
app.problem.value = 'Rewritten by visitor';
const disclosure = { open: false };
app.events.invalid({ target: { closest: () => disclosure } });
assert.equal(disclosure.open, true, 'Invalid optional input must be revealed');

await app.events.submit(event());
assert.deepEqual(app.assigned, ['/services/contact-received.html']);
assert.equal(app.requests.length, 1);
assert.equal(app.requests[0][0], '/services/contact-submit.php');
assert.equal(app.requests[0][1].credentials, 'same-origin');
assert.equal(app.requests[0][1].body.get('offer'), 'visibility-starter', 'Offer survives editing the problem');
assert.equal(app.requests[0][1].body.get('problem'), 'Rewritten by visitor');
assert.equal(app.submit.disabled, false);

app = boot('', 'Keep my original brief');
app.setResponseFactory(async () => ({ ok: false, status: 422, json: async () => ({ ok: false, message: 'This form expired.' }) }));
await app.events.submit(event());
assert.match(app.status.textContent, /timing has refreshed/);
assert.equal(app.status.focused, true);
assert.equal(app.problem.value, 'Keep my original brief');
assert.equal(app.assigned.length, 0);

for (const factory of [
  async () => { throw new Error('Synthetic network failure'); },
  async () => ({ ok: false, status: 500, json: async () => { throw new Error('Synthetic invalid JSON'); } }),
  async () => ({ ok: false, status: 500, json: async () => ({ ok: false, message: 'Storage unavailable.' }) }),
  async () => ({ ok: true, status: 200, json: async () => ({ ok: false, message: 'Not saved.' }) }),
]) {
  app = boot('', 'Keep my original brief');
  app.setResponseFactory(factory);
  await app.events.submit(event());
  assert.equal(app.problem.value, 'Keep my original brief', 'Failure must preserve input');
  assert.equal(app.assigned.length, 0, 'Only confirmed success may redirect');
  assert.equal(app.submit.disabled, false);
  assert.equal(app.form['aria-busy'], undefined);
  assert.equal(app.status.focused, true);
}

app = boot();
let finish;
app.setResponseFactory(() => new Promise(resolve => { finish = resolve; }));
const pending = app.events.submit(event());
await app.events.submit(event());
assert.equal(app.requests.length, 1, 'Repeated clicks during a request must not submit twice');
finish({ ok: true, status: 200, json: async () => ({ ok: true }) });
await pending;
app.submit.disabled = true;
app.status.textContent = 'Saving your brief…';
app.form['aria-busy'] = 'true';
app.windowEvents.pageshow();
assert.equal(app.submit.disabled, false);
assert.equal(app.status.textContent, '');
assert.equal(app.form['aria-busy'], undefined);

const required = [...services.matchAll(/<(?:input|textarea)\b[^>]*\brequired\b[^>]*>/g)].map(match => match[0]);
const attr = (markup, name) => markup.match(new RegExp(`\\b${name}="([^"]*)"`))?.[1];
assert.deepEqual(required.map(markup => attr(markup, 'name')).sort(), ['business', 'email', 'name', 'problem', 'win']);
for (const control of required) {
  const id = attr(control, 'id');
  assert(id && services.includes(`for="${id}"`), 'Every required control needs an explicit label');
}
assert.match(services, /data-contact-status[^>]*role="status"[^>]*aria-live="polite"/);
assert.match(services, /<details class="contact-optional"><summary>/);
assert.match(services, /name="email" type="email" autocomplete="email" inputmode="email"/);
assert.match(services, /type="hidden" name="offer" value="" data-selected-offer-id/);
assert(!/<a\b[^>]*href="https?:\/\/[^"\s]*paypal/i.test(services), 'Services must request scope before payment');
assert(!/<a\b[^>]*href="https?:\/\/[^"\s]*paypal/i.test(store), 'Store must use guarded checkout or scope request');
assert.equal((store.match(/href="\/store\/checkout\/index.php"/g) || []).length, 2);
assert.equal((store.match(/href="\/services\/\?offer=visibility-starter#contact"/g) || []).length, 2);
assert.match(services, /href="\/services\/\?offer=visibility-starter#contact"/);
assert.match(store, /Digital edition · \$7/);
assert.match(store, /Fixed-scope starter · \$250/);
assert.match(store, /Automatic download is offered only when verified-payment delivery is enabled/);
console.log('Conversion flow passed: safe offer selection, restored input, accessible controls, failure retention, duplicate-click guard, success-only redirect, published prices and scope-first CTAs.');
