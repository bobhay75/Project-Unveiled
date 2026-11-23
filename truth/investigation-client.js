(function (root) {
  'use strict';
  const verdicts = new Set(['SUPPORTED', 'LIKELY', 'MIXED', 'UNLIKELY', 'CONTRADICTED']);
  const noVerdict = 'INSUFFICIENT EVIDENCE — NO VERDICT';
  const stages = new Set(['decompose', 'origin', 'primary', 'corroboration', 'dependency', 'counter', 'context', 'synthesis']);
  const branches = new Set(['Challenge this finding', 'Dig deeper into the weakest evidence', 'Show only primary evidence', 'Argue the opposite conclusion', 'Follow the money or incentives if relevant', 'Investigate the key person or organization further']);
  const cut = (text, count) => Array.from(String(text)).slice(0, count).join('');
  function validFinding(result) {
    if (!result || typeof result !== 'object' || result.ok !== true || typeof result.analysis !== 'string' || !result.analysis.trim() || !Array.isArray(result.sources) || !result.metrics || typeof result.metrics !== 'object') return false;
    if (result.verdict === noVerdict) return result.probability === null;
    return result.metrics.evidence_floor_met === true && verdicts.has(result.verdict) && Number.isInteger(result.probability) && result.probability >= 0 && result.probability <= 100;
  }
  function sourceUrl(value) {
    try { const url = new URL(value); return /^https?:$/.test(url.protocol) && !url.username && !url.password ? url.href : ''; } catch { return ''; }
  }
  async function readStream(response, onEvent, { allowPaywall = true, maxBytes = 2097152 } = {}) {
    if (!response.ok) throw new Error(`Investigation request stopped (${response.status}). No completed result was received.`);
    if (!response.headers.get('content-type')?.toLowerCase().startsWith('application/x-ndjson')) throw new Error('The investigation returned an unexpected response format.');
    if (!response.body) throw new Error('Streaming response unavailable.');
    const reader = response.body.getReader(), decoder = new TextDecoder('utf-8', { fatal: true });
    let buffer = '', bytes = 0, terminal = null, started = false;
    const seenStages = new Set();
    function consume(line) {
      if (!line.trim()) return;
      if (terminal) throw new Error('Investigation sent data after its terminal receipt.');
      let event;
      try { event = JSON.parse(line); } catch { throw new Error('An investigation receipt was malformed.'); }
      if (!event || typeof event !== 'object') throw new Error('An investigation receipt was invalid.');
      if (event.type === 'error') throw new Error(typeof event.message === 'string' ? cut(event.message, 500) : 'The investigation stopped before completion.');
      if (event.type === 'start') {
        if (started) throw new Error('Investigation start was repeated.');
        started = true;
      } else {
        if (!started) throw new Error('Investigation evidence arrived without a start receipt.');
        if (event.type === 'receipt') {
          const receipt = event.receipt;
          if (!receipt || receipt.completed !== true || !stages.has(receipt.stage) || seenStages.has(receipt.stage)) throw new Error('A completed stage receipt was invalid or repeated.');
          seenStages.add(receipt.stage);
        } else if (event.type === 'complete') {
          if (!seenStages.has('synthesis') || !validFinding(event.result)) throw new Error('The final investigation did not satisfy its evidence contract.');
          terminal = event;
          return;
        } else if (event.type === 'paywall') {
          const handoff = event.handoff;
          if (!allowPaywall || !handoff || !['decompose', 'origin', 'primary', 'corroboration'].every(stage => seenStages.has(stage)) || typeof handoff.case_id !== 'string' || !/^[a-f0-9]{32}$/.test(handoff.case_id) || typeof handoff.checkout_state !== 'string' || !handoff.checkout_state || handoff.amount !== '2.99' || handoff.currency !== 'USD' || typeof handoff.checkout_available !== 'boolean') throw new Error('The paid continuation handoff was incomplete.');
          terminal = event;
          return;
        } else throw new Error('An unknown investigation event was received.');
      }
      onEvent(event);
    }
    try {
      for (;;) {
        const { value, done } = await reader.read();
        if (value) { bytes += value.byteLength; if (bytes > maxBytes) throw new Error('Investigation response exceeded its size limit.'); }
        buffer += decoder.decode(value || new Uint8Array(), { stream: !done });
        const lines = buffer.split('\n'); buffer = lines.pop() || '';
        for (const line of lines) consume(line);
        if (buffer.length > 524288) throw new Error('An investigation receipt exceeded its size limit.');
        if (done) break;
      }
      if (buffer.trim()) consume(buffer);
      if (!terminal) throw new Error('The connection ended before a completed result. Keep your case reference; no result was fabricated.');
      onEvent(terminal);
      return terminal.type;
    } finally { await reader.cancel().catch(() => {}); reader.releaseLock(); }
  }
  function branchFields(result, branch, now = Date.now()) {
    if (!validFinding(result) || !branches.has(branch) || typeof result.claim !== 'string' || !result.claim.trim()) throw new Error('A completed finding and a recognized follow-up are required.');
    const urls = result.sources.map(source => sourceUrl(source?.url)).filter(Boolean).slice(0, 6).join('\n');
    return {
      question: cut(`${branch}. Reinvestigate this original claim: ${result.claim}`, 3000),
      context: cut(`FOLLOW-UP REQUEST. The earlier model output below is an unverified research lead, not established fact. Check its sources independently and keep contrary evidence. Earlier investigation: ${result.investigation_id || 'unavailable'}.\nSource leads:\n${urls}\nEarlier analysis excerpt:\n${cut(result.analysis, 1700)}`, 3000),
      opened_at: String(Math.floor(now / 1000)), website: '',
    };
  }
  function downloadFinding(result) {
    if (!validFinding(result)) throw new Error('A completed finding is required for export.');
    const blob = new Blob([JSON.stringify({ format: 'trust-worthy-finding-v1', exported_at: new Date().toISOString(), result }, null, 2) + '\n'], { type: 'application/json;charset=utf-8' });
    const url = URL.createObjectURL(blob), link = document.createElement('a');
    link.href = url; link.download = 'Trust-Worthy-Finding.json'; document.body.appendChild(link); link.click(); link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  }
  function openBranch(result, branch) {
    const fields = branchFields(result, branch), form = document.createElement('form');
    form.method = 'post'; form.action = '/truth/investigation.php'; form.target = '_blank'; form.rel = 'noopener noreferrer';
    for (const [name, value] of Object.entries(fields)) { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value; form.appendChild(input); }
    document.body.appendChild(form); form.submit(); form.remove();
  }
  const api = { validFinding, sourceUrl, readStream, branchFields, downloadFinding, openBranch };
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.TrustWorthyClient = api;
})(typeof window === 'object' ? window : globalThis);
