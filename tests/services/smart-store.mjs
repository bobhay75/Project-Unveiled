import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const root = new URL('../../', import.meta.url);
const html = fs.readFileSync(new URL('store/index.html', root), 'utf8');
const source = fs.readFileSync(new URL('store/store.js', root), 'utf8');
const css = fs.readFileSync(new URL('store/store.css', root), 'utf8');
const manifest = fs.readFileSync(new URL('deployment/public-files.txt', root), 'utf8');
const attr = (markup, name) => markup.match(new RegExp(`\\b${name}="([^"]*)"`))?.[1];
const buttons = [...html.matchAll(/<button\b([^>]*)>([\s\S]*?)<\/button>/g)];
const sections = [...html.matchAll(/<section\b([^>]*)>([\s\S]*?)<\/section>/g)]
  .filter(([, attributes]) => (attr(attributes, 'class') || '').split(/\s+/).includes('offer-group'));
const expectedFlows = ['book', 'journey', 'truth', 'visibility', 'bottleneck', 'sponsor', 'technology', 'local'];

// Derive controls and destinations from the real HTML. Only browser mechanics
// are synthetic; execute the shipped JavaScript without network or payments.
function boot(reducedMotion = false) {
  let focused = null;
  const element = () => {
    const events = new Map();
    const classes = new Set();
    return {
      dataset: {}, innerHTML: '', textContent: '', hidden: false, disabled: false,
      addEventListener(name, callback) {
        if (!events.has(name)) events.set(name, []);
        events.get(name).push(callback);
      },
      dispatch(name) { for (const callback of events.get(name) || []) callback({ target: this }); },
      click() { if (!this.disabled && !this.hidden) this.dispatch('click'); },
      focus() { focused = this; },
      setAttribute(name, value) { this[name] = value; },
      scrollIntoView(options) { this.scrolled = options; },
      classList: {
        contains(name) { return classes.has(name); },
        toggle(name, force) {
          if (force === undefined ? !classes.has(name) : Boolean(force)) classes.add(name);
          else classes.delete(name);
          return classes.has(name);
        },
      },
    };
  };
  const intentButtons = buttons.filter(([, attributes]) => attr(attributes, 'data-intent')).map(([, attributes, body]) => {
    const button = element();
    button.dataset.intent = attr(attributes, 'data-intent');
    const label = { textContent: body.match(/<strong>([\s\S]*?)<\/strong>/)?.[1] };
    button.querySelector = selector => selector === 'strong' ? label : null;
    return button;
  });
  const walkthroughButtons = buttons.filter(([, attributes]) => attr(attributes, 'data-walkthrough')).map(([, attributes]) => {
    const button = element();
    button.dataset.walkthrough = attr(attributes, 'data-walkthrough');
    return button;
  });
  const groups = sections.map(([, attributes, body]) => {
    const group = element();
    group.id = attr(attributes, 'id');
    group.dataset.audience = attr(attributes, 'data-audience');
    group.querySelector = selector => {
      const audience = selector.match(/^\[data-audience="([^"]+)"\]$/)?.[1];
      return audience && body.includes(`data-audience="${audience}"`) ? {} : null;
    };
    return group;
  });
  const nodes = Object.fromEntries(['#walk-content', '#walk-final', '#walk-kicker', '#walk-title', '#walk-lead', '.walk-progress', '.walk-prev', '.walk-next', '.close']
    .map(selector => [selector, element()]));
  const dialog = element();
  dialog.open = false;
  dialog.querySelector = selector => nodes[selector] || null;
  dialog.showModal = () => { assert.equal(dialog.open, false); dialog.open = true; };
  dialog.close = () => { dialog.open = false; dialog.dispatch('close'); };
  const status = element();
  vm.runInNewContext(source, {
    document: {
      getElementById: id => id === 'walkthrough-dialog' ? dialog : null,
      querySelectorAll: selector => ({ '[data-intent]': intentButtons, '[data-walkthrough]': walkthroughButtons, '.offer-group': groups })[selector] || [],
      querySelector: selector => selector === '.intent-status' ? status : selector === '.offer-group.intent-match' ? groups.find(group => group.classList.contains('intent-match')) : null,
    },
    matchMedia: query => { assert.equal(query, '(prefers-reduced-motion: reduce)'); return { matches: reducedMotion }; },
  }, { filename: 'store/store.js' });
  return { intentButtons, walkthroughButtons, groups, nodes, dialog, status, get focused() { return focused; } };
}

assert.match(html, /<script\b[^>]*src="\/store\/store\.js(?:\?[^\"]*)?"[^>]*\bdefer\b/);
assert.match(html, /<dialog\b[^>]*id="walkthrough-dialog"[^>]*aria-labelledby="walk-title"/);
assert.match(html, /class="intent-status"[^>]*aria-live="polite"/);
assert.match(html, /href="#choose"/);
assert.match(css, /#walkthrough-dialog\s+\[hidden\]\s*\{\s*display:\s*none\s*\}/, 'Final-step controls must stay hidden despite .button display styling');
assert.equal(manifest.split(/\r?\n/).filter(path => path.trim() === 'store/store.js').length, 1, 'Deploy the smart-store script exactly once');
for (const path of ['store/index.html', 'store/store.css', 'store/checkout/index.php', 'services/contact.js', 'services/contact-submit.php']) {
  assert(manifest.split(/\r?\n/).includes(path), `Missing integrated public asset: ${path}`);
}

// All four routes must select a real destination, clear the previous selection,
// announce the choice, and honor reduced-motion preferences.
for (const reducedMotion of [false, true]) {
  const app = boot(reducedMotion);
  const destinations = { investigate: 'books', business: 'services', read: 'books', partner: 'partners' };
  assert.deepEqual(app.intentButtons.map(button => button.dataset.intent), Object.keys(destinations));
  for (const button of app.intentButtons) {
    for (const group of app.groups) delete group.scrolled;
    button.click();
    assert.deepEqual(app.intentButtons.filter(item => item.classList.contains('active')), [button]);
    const matches = app.groups.filter(group => group.classList.contains('intent-match'));
    assert.equal(matches.length, 1, `${button.dataset.intent} must have one destination`);
    assert.equal(matches[0].id, destinations[button.dataset.intent]);
    assert.equal(matches[0].scrolled?.behavior, reducedMotion ? 'auto' : 'smooth');
    assert.equal(matches[0].scrolled?.block, 'start');
    assert(app.status.textContent.includes(button.querySelector('strong').textContent));
  }
}

const app = boot();
assert.deepEqual(app.walkthroughButtons.map(button => button.dataset.walkthrough), expectedFlows);
const content = app.nodes['#walk-content'];
const final = app.nodes['#walk-final'];
const next = app.nodes['.walk-next'];
const back = app.nodes['.walk-prev'];
const expectedActions = {
  book: ['/book/read/', '/store/checkout/index.php'],
  journey: ['/unveiled/?utm_source=store&utm_medium=owned&utm_campaign=revenue_funnel&utm_content=walkthrough'],
  truth: ['/truth/'],
  visibility: ['/services/?offer=visibility-starter#contact'],
  bottleneck: ['/services/#contact', '/services/'],
  sponsor: ['/services/#contact'],
  technology: ['/services/#contact'],
  local: ['/services/#contact'],
};
for (const button of app.walkthroughButtons) {
  const flow = button.dataset.walkthrough;
  button.click();
  assert.equal(app.dialog.open, true, `${flow}: opens modal`);
  assert.match(content.innerHTML, /STEP 1 OF 4/);
  assert.equal(back.disabled, true);
  assert.equal(back.hidden, false);
  assert.equal(next.hidden, false);
  assert.equal(final.innerHTML, '', `${flow}: stale final actions cleared`);
  assert(app.nodes['#walk-title'].textContent && app.nodes['#walk-lead'].textContent, `${flow}: named introduction`);
  const firstStep = content.innerHTML;
  next.click();
  assert.match(content.innerHTML, /STEP 2 OF 4/);
  assert.equal(back.disabled, false);
  back.click();
  assert.equal(content.innerHTML, firstStep, `${flow}: Back restores previous step`);
  for (let step = 2; step <= 4; step++) {
    next.click();
    assert(content.innerHTML.includes(`STEP ${step} OF 4`), `${flow}: sequential step ${step}`);
    assert.equal(final.innerHTML, '', `${flow}: actions wait for walkthrough completion`);
  }
  assert.equal(next.textContent, 'See next actions');
  next.click();
  assert.equal(content.innerHTML, '');
  assert.equal(next.hidden, true);
  assert.equal(back.hidden, true);
  const actions = [...final.innerHTML.matchAll(/<a\b[^>]*href="([^"]*)"/g)].map(match => match[1].replaceAll('&amp;', '&'));
  assert.deepEqual(actions, expectedActions[flow], `${flow}: final action uses the intended guarded route`);
  assert(!/href="https?:\/\/[^"\s]*paypal/i.test(final.innerHTML), `${flow}: cannot bypass guarded checkout or scope approval`);
  if (flow === 'book') assert.match(final.innerHTML, /data-pu-event="product_checkout_click"/);
  if (flow === 'visibility') assert.match(final.innerHTML, /data-pu-event="qualified_lead_click"/);
  app.nodes['.close'].click();
  assert.equal(app.dialog.open, false);
  assert.equal(app.focused, button, `${flow}: Close restores initiating control focus`);
  button.click();
  assert.equal(content.innerHTML, firstStep, `${flow}: reopen starts at first step`);
  assert.equal(final.innerHTML, '');
  assert.equal(next.hidden, false);
  assert.equal(back.disabled, true);
  // The native dialog emits close after Escape; exercise that shared cleanup
  // independently of the custom close button. Browser focus trapping is a
  // separate manual acceptance check, not simulated by this regression test.
  app.dialog.close();
  assert.equal(app.focused, button, `${flow}: native-close cleanup restores focus`);
}

console.log('Smart store passed: four intent routes, reduced motion, eight walkthroughs, back/reopen/close focus, guarded purchase and scope-intake actions, and deployment wiring (offline; no payments).');
