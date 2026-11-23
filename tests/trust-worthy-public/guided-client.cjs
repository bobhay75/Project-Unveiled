const { test } = require('node:test');
const assert = require('node:assert/strict');
const { readStream, validFinding, branchFields, sourceUrl } = require('../../truth/investigation-client.js');
const result = { ok: true, investigation_id: 'TW-SYNTHETIC', claim: 'Synthetic original claim for a follow-up', verdict: 'MIXED', probability: 47, analysis: 'Synthetic evidence and limits.', sources: [{ url: 'https://example.com/record' }], metrics: { evidence_floor_met: true } };
const start = { type: 'start' }, receipt = { type: 'receipt', receipt: { stage: 'synthesis', completed: true } }, complete = { type: 'complete', result };
function response(events, newline = true) { const text = events.map(event => typeof event === 'string' ? event : JSON.stringify(event)).join('\n') + (newline ? '\n' : ''); return new Response(text, { headers: { 'Content-Type': 'application/x-ndjson' } }); }
test('valid stream preserves receipts and accepts a final line without newline', async () => {
  const seen = []; assert.equal(await readStream(response([start, receipt, complete], false), event => seen.push(event.type)), 'complete');
  assert.deepEqual(seen, ['start', 'receipt', 'complete']);
});
test('truncated stream stops without publishing a result', async () => {
  const seen = []; await assert.rejects(readStream(response([start, receipt]), event => seen.push(event.type)), /ended before/);
  assert.deepEqual(seen, ['start', 'receipt']);
});
test('malformed, repeated or unstarted receipts stop the stream', async () => {
  for (const events of [[receipt, complete], [start, start], [start, '{broken'], [start, receipt, receipt], [start, { type: 'receipt', receipt: { stage: 'synthesis', completed: false } }], [start, complete]]) await assert.rejects(readStream(response(events), () => {}));
});
test('contradictory findings cannot be rendered even through a completed stream', async () => {
  for (const invalid of [{ ...result, probability: 101 }, { ...result, metrics: { evidence_floor_met: false } }, { ...result, verdict: 'INVENTED' }, { ...result, verdict: 'INSUFFICIENT EVIDENCE — NO VERDICT' }]) {
    assert.equal(validFinding(invalid), false);
    await assert.rejects(readStream(response([start, receipt, { type: 'complete', result: invalid }]), () => {}));
  }
  assert.equal(validFinding({ ...result, verdict: 'INSUFFICIENT EVIDENCE — NO VERDICT', probability: null, metrics: { evidence_floor_met: false } }), true);
});
test('post-result data, provider errors and excessive output fail closed', async () => {
  let completed = false;
  await assert.rejects(readStream(response([start, receipt, complete, { type: 'error', message: 'failed' }]), event => { if (event.type === 'complete') completed = true; }));
  assert.equal(completed, false);
  await assert.rejects(readStream(response([start, { type: 'error', message: 'Synthetic provider stopped' }]), () => {}), /Synthetic provider stopped/);
  await assert.rejects(readStream(response([start, receipt, complete]), () => {}, { maxBytes: 30 }), /size limit/);
});
test('payment handoff requires actual completed free-stage receipts and fixed price', async () => {
  const events = [start, ...['decompose', 'origin', 'primary', 'corroboration'].map(stage => ({ type: 'receipt', receipt: { stage, completed: true } }))];
  const paywall = { type: 'paywall', handoff: { case_id: 'a'.repeat(32), checkout_state: 'synthetic-not-a-token', amount: '2.99', currency: 'USD', checkout_available: false } };
  assert.equal(await readStream(response([...events, paywall]), () => {}), 'paywall');
  await assert.rejects(readStream(response([...events, paywall]), () => {}, { allowPaywall: false }));
  await assert.rejects(readStream(response([start, paywall]), () => {}));
  await assert.rejects(readStream(response([...events, { ...paywall, handoff: { ...paywall.handoff, amount: '0.01' } }]), () => {}));
});
test('follow-up remains bounded, carries source leads, and never treats the prior answer as fact', () => {
  const fields = branchFields({ ...result, claim: 'x'.repeat(6000), analysis: '🙂'.repeat(9000) }, 'Challenge this finding', 1760000000000);
  assert.ok(Array.from(fields.question).length <= 3000); assert.ok(Array.from(fields.context).length <= 3000);
  assert.match(fields.context, /unverified research lead/); assert.match(fields.context, /https:\/\/example.com\/record/); assert.equal(fields.opened_at, '1760000000');
  assert.throws(() => branchFields(result, 'Unsupported branch'));
  assert.equal(sourceUrl('https://user:pass@example.com'), ''); assert.equal(sourceUrl('/relative'), ''); assert.equal(sourceUrl('javascript:alert(1)'), '');
});
