window.__runGuidedBehavior = async function () {
  const checks = [], requests = [], branches = [], alerts = [];
  let truncated = false, exportedBlob = null, exportedName = '';
  const require = (ok, label) => { if (!ok) throw new Error(label); checks.push(label); };
  const waitFor = async predicate => { for (let i = 0; i < 80; i++) { if (predicate()) return; await new Promise(resolve => setTimeout(resolve, 5)); } throw new Error('Synthetic UI did not settle'); };
  const sources = [
    { url: 'https://example.com/a', title: 'Synthetic record A', family: 'example.com' },
    { url: 'https://example.com/b', title: 'Synthetic record B', family: 'example.com' },
    { url: 'https://example.org/c', title: 'Synthetic counterrecord', family: 'example.org' },
    { url: 'https://example.net/d', title: 'Synthetic corroboration', family: 'example.net' },
  ];
  const result = { ok: true, investigation_id: 'TW-SYNTHETIC-UI', claim: 'Synthetic original claim for browser verification', verdict: 'MIXED', probability: 47, analysis: 'VERDICT: MIXED\nPROBABILITY: 47\nSynthetic evidence <img src=x onerror=alert(1)> stays text.', sources, metrics: { evidence_floor_met: true, unique_sources: 4, source_families: 3, usage: { total_tokens: 100 } } };
  const originalFetch = window.fetch, originalSubmit = HTMLFormElement.prototype.submit, originalClick = HTMLAnchorElement.prototype.click, originalBlob = URL.createObjectURL, originalAlert = window.alert;
  window.alert = message => alerts.push(message);
  HTMLFormElement.prototype.submit = function () { branches.push({ action: this.getAttribute('action'), target: this.target, rel: this.rel, fields: Object.fromEntries(new FormData(this)) }); };
  HTMLAnchorElement.prototype.click = function () { if (this.download) { exportedName = this.download; return; } return originalClick.call(this); };
  URL.createObjectURL = function (blob) { exportedBlob = blob; return originalBlob.call(URL, blob); };
  window.fetch = async function (url, init) {
    requests.push({ url, body: new URLSearchParams(init?.body) });
    if (url === '/truth/claim-map.php') return Response.json({ ok: true, claim_map: 'Synthetic editable claim map', authorization_token: 'synthetic-one-use-placeholder', expires_in: 900 });
    if (url !== '/truth/deep-stream.php') throw new Error('Unexpected network request in isolated browser test');
    const events = [{ type: 'start' }, ...['decompose', 'origin', 'primary', 'corroboration', 'dependency', 'counter', 'context', 'synthesis'].map(stage => ({ type: 'receipt', receipt: { stage, label: stage, completed: true, sources, source_count: 4, source_family_count: 3, evidence_floor_met: true } }))];
    if (!truncated) events.push({ type: 'complete', result });
    return new Response(events.map(event => JSON.stringify(event)).join('\n'), { headers: { 'Content-Type': 'application/x-ndjson' } });
  };
  try {
    document.getElementById('mapButton').click();
    await waitFor(() => !document.getElementById('claimMapPanel').hidden);
    require(requests.length === 1 && requests[0].url === '/truth/claim-map.php', 'No deep request before map approval');
    document.getElementById('confirmedMap').value = 'Synthetic map edited by the reviewer';
    const research = document.getElementById('researchButton'); research.click(); research.click();
    await waitFor(() => document.getElementById('systemStatus').textContent === 'COMPLETE');
    require(requests.filter(request => request.url === '/truth/deep-stream.php').length === 1, 'Repeated click starts only one research request');
    require(requests[1].body.get('confirmed_map') === 'Synthetic map edited by the reviewer', 'Approved edited map reaches the request');
    require(!document.getElementById('resultPanel').hidden && document.getElementById('probability').textContent === '47%' && document.getElementById('receiptCount').textContent === '8', 'Actual result and receipt controls render');
    require(document.getElementById('analysis').textContent === result.analysis && !document.getElementById('analysis').querySelector('img') && alerts.length === 0, 'Provider analysis renders only as text');
    for (const button of document.querySelectorAll('[data-branch]')) button.click();
    require(branches.length === 6 && branches.every(branch => branch.action === '/truth/investigation.php' && branch.target === '_blank' && branch.rel.includes('noopener') && branch.fields.context.includes('unverified research lead')), 'All six follow-up controls create reviewable new requests');
    require(requests.length === 2 && !document.getElementById('resultPanel').hidden, 'Follow-up preparation leaves the original result available');
    document.getElementById('exportFinding').click();
    const exported = JSON.parse(await exportedBlob.text());
    require(exportedName === 'Trust-Worthy-Finding.json' && exported.result.investigation_id === result.investigation_id && exported.result.sources.length === 4 && !JSON.stringify(exported).includes('synthetic-one-use-placeholder'), 'Download contains the finding and sources without an authorization token');
    truncated = true;
    document.getElementById('rebuildMapButton').click();
    await waitFor(() => !document.getElementById('claimMapPanel').hidden && !research.disabled);
    research.click();
    await waitFor(() => document.getElementById('systemStatus').textContent === 'STOPPED');
    require(document.getElementById('resultPanel').hidden && document.getElementById('activity').textContent.includes('ended before'), 'Interrupted stream stops without showing a completed result');
    return { checks: checks.length, passed: true };
  } catch (error) { return { checks: checks.length, passed: false, error: String(error) }; }
  finally { window.fetch = originalFetch; HTMLFormElement.prototype.submit = originalSubmit; HTMLAnchorElement.prototype.click = originalClick; URL.createObjectURL = originalBlob; window.alert = originalAlert; }
};
