<?php
declare(strict_types=1);
$caseId='TW-CLAIM-000001';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="description" content="Trust-Worthy AI Truth Trials: open, adversarial investigations that trace claims to evidence and invite challenges.">
<title>Trust-Worthy AI | Truth Trial 000001</title>
<link rel="stylesheet" href="/truth/truth-worthy.css">
</head>
<body>
<header class="topbar"><div class="wrap nav"><a class="brand" href="/">PROJECT <span>UNVEILED</span></a><nav class="navlinks"><a href="/book/">Read</a><a href="/book/research.html">Research Standards</a><a href="#challenge">Challenge This Finding</a></nav></div></header>
<main>
<section class="hero"><div class="wrap"><div class="eyebrow">Trust-Worthy AI · Open Truth Trial</div><h1>Don't trust the answer. Examine the evidence.</h1><p>Trust-Worthy is being built to investigate hard claims from the source outward: provenance, chronology, language, context, corroboration, counterevidence, logic, competing explanations, and uncertainty.</p><div class="manifesto"><strong>No protected conclusions.</strong> If better evidence overturns Project Unveiled, Trust-Worthy, a denomination, a skeptic, or its own prior finding, the public record changes and the revision remains visible.</div></div></section>
<section class="section"><div class="wrap grid">
<article class="card">
<div class="case-head"><div><div class="case-id"><?php echo htmlspecialchars($caseId,ENT_QUOTES,'UTF-8'); ?></div><h2>Does God individually cause or permit every bad thing that happens?</h2></div><div class="status">OPEN INVESTIGATION</div></div>
<div class="claim">The inherited question “Why does God let bad things happen to good people?” may contain an assumption that must itself be tested: that every event requires God's individual permission.</div>
<p class="muted">This first case is intentionally difficult. Trust-Worthy will not begin with a canned answer. It will test the premise, biblical texts about divine sovereignty and hostile spiritual powers, historical interpretations, philosophical alternatives, and the strongest objections to every major explanation.</p>
<h3>Investigation protocol</h3>
<div class="steps">
<div class="step"><div class="num">1</div><div><strong>Define the claim precisely</strong><span>Separate divine causation, permission, foreknowledge, sovereignty, natural consequence, human agency, and hostile spiritual agency.</span></div></div>
<div class="step"><div class="num">2</div><div><strong>Trace primary evidence</strong><span>Examine relevant biblical passages in context and identify the textual claims rather than importing a later doctrinal system.</span></div></div>
<div class="step"><div class="num">3</div><div><strong>Test historical interpretations</strong><span>Compare early Christian, Jewish, Gnostic, classical orthodox, Reformation, and modern readings while distinguishing source evidence from later theology.</span></div></div>
<div class="step"><div class="num">4</div><div><strong>Attack the leading explanation</strong><span>Search deliberately for passages, history, philosophy, and lived realities that would falsify or materially weaken it.</span></div></div>
<div class="step"><div class="num">5</div><div><strong>Run Christ-consistency analysis</strong><span>Separately ask which interpretation best coheres with the recorded words, conduct, mercy, truth, justice, and character of Jesus.</span></div></div>
<div class="step"><div class="num">6</div><div><strong>Publish what survives</strong><span>Show documented evidence, inference, theological interpretation, unresolved questions, strongest counterargument, and what would change the finding.</span></div></div>
</div>
<div class="labels"><span class="label">Provenance</span><span class="label">Chronology</span><span class="label">Original Language</span><span class="label">Counterevidence</span><span class="label">Logic</span><span class="label">Christ Consistency</span><span class="label">Uncertainty</span></div>
<div class="finding"><strong>Current finding:</strong> Not yet rendered. The case is open while the research record is assembled. Trust-Worthy will not manufacture certainty before the source work is complete.</div>
</article>
<aside class="card"><h3>The Trial Rules</h3><ul class="rules"><li>No fact-checker verdicts are treated as authority.</li><li>Primary and earliest accessible evidence receives priority.</li><li>Ten sites repeating one source count as one lineage, not ten confirmations.</li><li>Contrary evidence must be actively sought.</li><li>Evidence and interpretation remain visibly separate.</li><li>“We do not know” is a valid result.</li><li>Successful challenges permanently revise the case history.</li></ul>
<h3 style="margin-top:28px">Possible outcomes</h3><div class="labels"><span class="label">Better supported</span><span class="label">Partially supported</span><span class="label">Unsupported</span><span class="label">False premise</span><span class="label">Unresolved</span><span class="label">Overturned</span></div>
</aside>
</div></section>
<section class="section" id="challenge"><div class="wrap"><div class="card"><div class="eyebrow">Open Challenge</div><h2>Try to break the case.</h2><p class="muted">Do not argue louder. Bring something that changes the evidence: a source, date, translation, context, logical flaw, counterexample, or hidden assumption. If it materially changes the finding, the public case will be revised and the challenger can be credited.</p>
<form class="challenge" id="challengeForm" action="/truth/challenge-submit.php" method="post">
<input type="hidden" name="case_id" value="<?php echo htmlspecialchars($caseId,ENT_QUOTES,'UTF-8'); ?>"><input type="hidden" name="opened_at" id="openedAt" value=""><div class="hp" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
<div class="row"><div><label for="challenge_type">Challenge type</label><select id="challenge_type" name="challenge_type" required><option value="">Choose one</option><option value="source">Source / provenance</option><option value="date">Date / chronology</option><option value="translation">Translation / language</option><option value="context">Context</option><option value="logic">Logic / inference</option><option value="counterevidence">Counterevidence</option><option value="assumption">Hidden assumption</option><option value="other">Other</option></select></div><div><label for="name">Name or handle</label><input id="name" name="name" maxlength="100" autocomplete="name"></div></div>
<label for="argument">Your challenge</label><textarea id="argument" name="argument" minlength="20" maxlength="6000" required placeholder="Identify exactly what is wrong or missing, and why it matters to the finding."></textarea>
<label for="source">Source or evidence link/citation</label><textarea id="source" name="source" maxlength="2000" placeholder="Primary source, book/article citation, manuscript reference, URL, quotation location, dataset, or other evidence."></textarea>
<label for="email">Email (optional, for follow-up)</label><input id="email" type="email" name="email" maxlength="190" autocomplete="email">
<button class="button" type="submit">Submit Evidence Challenge</button><div class="msg" id="formMsg" role="status" aria-live="polite"></div>
</form></div></div></section>
</main>
<footer class="footer"><div class="wrap">Trust-Worthy AI · Project Unveiled · Truth is not afraid of questions.</div></footer>
<script>
(function(){
const form=document.getElementById('challengeForm'), msg=document.getElementById('formMsg'), opened=document.getElementById('openedAt');
opened.value=Math.floor(Date.now()/1000);
form.addEventListener('submit',async function(e){e.preventDefault();msg.textContent='Submitting challenge…';const button=form.querySelector('button');button.disabled=true;try{const r=await fetch(form.action,{method:'POST',body:new FormData(form),headers:{'Accept':'application/json'}});const data=await r.json();msg.textContent=data.message||'Submission received.';if(data.ok){form.reset();opened.value=Math.floor(Date.now()/1000);}}catch(err){msg.textContent='The challenge could not be submitted. Please try again.';}finally{button.disabled=false;}});
})();
</script>
</body></html>