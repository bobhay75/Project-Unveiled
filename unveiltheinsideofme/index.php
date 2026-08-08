<?php
header('X-Robots-Tag: noindex, nofollow, noarchive', true);
header('Cache-Control: no-store, max-age=0', true);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#0b1020">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Inside of Me">
  <title>Inside of Me — Private Beta</title>
  <meta name="description" content="A private space to say it here first, see a situation clearly, explore plausible outcomes, understand your story, and uncover possible patterns without giving up your agency.">
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="icon" href="inside-icon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="app.css?v=1">
  <link rel="stylesheet" href="premium-reveal.css?v=3">
  <link rel="stylesheet" href="daily.css?v=4">
</head>
<body>
<a class="skip" href="#main">Skip to app</a>
<header class="topbar">
  <div class="brand-wrap"><div class="mark" aria-hidden="true">I</div><div><strong>Inside of Me</strong><span>Private founder beta</span></div></div>
  <div class="privacy-chip" title="Your project stays in this browser by default. If you explicitly use Truth Mirror, Scenario Lab, Deep Reflection, Deep Director, or premium image generation, only the relevant text for that request is sent through the server to the configured AI provider; the app does not store a server-side story database.">Local-first · AI only when you request it</div>
</header>
<main id="main" class="shell">
  <section class="hero panel">
    <p class="eyebrow">There’s a story inside all of us.</p><h1>What’s the story <em>inside of me?</em></h1>
    <p class="lead">Use it in the moment or across a lifetime. Get something off your chest before you send it, separate facts from assumptions, explore plausible conversation branches, then connect recurring themes back to the story you have personally confirmed.</p>
    <div class="hero-actions"><button class="primary" data-go="now">Say it here first</button><button class="secondary" data-go="tell">Tell my life story</button><button class="secondary" id="loadDemo">Load founder-style demo</button></div>
    <div class="promise-grid"><div><strong>Truth before agreement</strong><span>Feelings are real. Claims about facts and motives still get checked.</span></div><div><strong>Possibilities, not predictions</strong><span>Scenario branches show what could happen without pretending to know the future.</span></div><div><strong>Your agency stays yours</strong><span>The system reflects and simulates. It does not decide what you should say or do.</span></div></div>
  </section>

  <nav class="stepper" aria-label="Inside of Me workflow"><button class="step active" data-step="now"><b>0</b><span>Right now</span></button><button class="step" data-step="tell"><b>1</b><span>Tell</span></button><button class="step" data-step="timeline"><b>2</b><span>Timeline</span></button><button class="step" data-step="patterns"><b>3</b><span>Patterns</span></button><button class="step" data-step="film"><b>4</b><span>Reveal</span></button></nav>

  <section id="now" class="workspace active">
    <div class="section-head"><div><p class="eyebrow">Right now</p><h2>Say it here first.</h2></div><span id="dailySaveStatus" class="status">Local-first</span></div>
    <div class="now-intro">
      <div class="panel now-hero"><p class="eyebrow">Release → Reality → Possibility → Choice</p><h2>Before you react out there, understand what’s happening in here.</h2><p class="now-lead">Vent without polishing it. Inside of Me can help separate what happened, what you felt, what you inferred, what is still unknown, and how several different responses might plausibly play out. None of those branches is a prediction, and none of them is an instruction.</p><div class="now-rules"><span>No prophecy</span><span>No commands</span><span>No fake certainty</span><span>No automatic agreement</span></div><div class="install-row"><button id="installAppBtn" class="secondary" type="button" hidden>Install Inside of Me</button><button class="secondary" data-go="tell" type="button">Connect this to my life story</button></div></div>
      <aside class="panel now-status"><div><strong>Truth Mirror</strong><span>Separates observable facts, feelings, interpretations, unknowns, and obvious logical overreach.</span></div><div><strong>Christian lens — optional</strong><span>Compares words and intentions with truth, love, mercy, justice, humility, courage, forgiveness, and self-control. It never claims to speak for God.</span></div><div><strong>Scenario Lab</strong><span>Runs several plausible branches so you can see tradeoffs before you choose your own next move.</span></div></aside>
    </div>

    <div class="daily-grid">
      <div class="panel">
        <div class="daily-split-label"><label class="daily-label" for="dailyVent">Tell me what happened.</label><span id="dailyCount">0 characters</span></div>
        <textarea id="dailyVent" class="daily-textarea" maxlength="30000" placeholder="What happened? What was said? What did you feel? What do you wish you could say right now?"></textarea>
        <div class="emotion-row" aria-label="What feels strongest"><button class="emotion-chip" data-emotion="Anger" type="button">Anger</button><button class="emotion-chip" data-emotion="Hurt" type="button">Hurt</button><button class="emotion-chip" data-emotion="Rejection" type="button">Rejection</button><button class="emotion-chip" data-emotion="Disrespect" type="button">Disrespect</button><button class="emotion-chip" data-emotion="Fear" type="button">Fear</button><button class="emotion-chip" data-emotion="Confusion" type="button">Confusion</button><button class="emotion-chip" data-emotion="Grief" type="button">Grief</button><button class="emotion-chip" data-emotion="Shame" type="button">Shame</button><button class="emotion-chip" data-emotion="Defensiveness" type="button">Defensiveness</button></div>
        <div class="daily-tools"><button id="dailyVoiceBtn" class="secondary" type="button">Start voice dictation</button><button id="dailySaveBtn" class="secondary" type="button">Save on this device</button><button id="dailyClearBtn" class="ghost danger" type="button">Clear</button></div>
        <p class="daily-disclosure">Your unsent reflection stays in this browser by default. Pressing Truth Mirror or Run the branches also requests Deep AI when the server is configured; only the relevant text for that request is sent, and this beta does not create a server-side story database.</p>
      </div>

      <div class="panel">
        <p class="eyebrow">Truth Mirror</p><h3>Show me the situation without flattering me.</h3>
        <div class="mirror-options"><label class="mirror-option"><input id="dailyFaith" type="checkbox"><span><strong>Christian alignment lens</strong><br>Truth, love, mercy, justice, humility, courage, forgiveness, self-control.</span></label><label class="mirror-option"><input id="dailyUseStory" type="checkbox" checked><span><strong>Use my saved life story</strong><br>Recurring themes are only hypotheses until I say they fit.</span></label></div>
        <button id="dailyMirrorBtn" class="primary" type="button">Show me the mirror</button>
        <div id="dailyMirrorOutput" class="daily-output"><div class="daily-empty">Nothing reflected yet. Say what happened exactly as it feels.</div></div>
      </div>
    </div>

    <div class="panel" style="margin-top:1rem">
      <p class="eyebrow">Scenario Lab</p><h3>What do you want to say?</h3><p class="section-copy">Put the unfiltered version here first. Inside of Me will run several plausible branches—not tell you which one to choose.</p>
      <textarea id="dailyReply" class="daily-textarea daily-reply" maxlength="12000" placeholder="Say it exactly how you want to say it to them..."></textarea>
      <div class="daily-actions"><button id="dailyScenarioBtn" class="primary" type="button">Run the branches</button><button id="dailyExportBtn" class="secondary" type="button">Export this reflection</button><button id="dailyDeleteBtn" class="ghost danger daily-danger" type="button">Delete Right Now data</button></div>
      <div class="scenario-caveat">Real people remain unpredictable. Voice tone, history, power dynamics, incentives, timing, missing facts, and other people’s choices can all change what actually happens.</div>
      <div id="dailyScenarioOutput" class="branch-grid daily-output"></div><div id="dailyScenarioNotes"></div>
    </div>
    <div class="next-row"><button class="primary" data-go="tell">Add the bigger story behind this</button></div>
  </section>

  <section id="tell" class="workspace"><div class="section-head"><div><p class="eyebrow">Step 1</p><h2>Tell it your way.</h2></div><span id="saveStatus" class="status">Not saved yet</span></div>
    <div class="two-col"><div class="panel editor-panel"><label for="story">Your story</label><textarea id="story" maxlength="60000" placeholder="Start anywhere. You can say: ‘The part of my life I keep coming back to is…’"></textarea><div class="editor-tools"><button id="voiceBtn" class="secondary" type="button">Start voice dictation</button><button id="saveBtn" class="secondary" type="button">Save on this device</button><button id="clearBtn" class="ghost danger" type="button">Clear</button><span id="counter">0 characters</span></div><p class="microcopy">Voice dictation uses your browser’s speech-recognition support when available. The basic timeline and reflection tools run locally. Deep Reflection, Deep Director, and premium image generation only send story text when you explicitly choose those features.</p></div>
      <aside class="panel prompts"><p class="eyebrow">Story guide</p><h3>When you get stuck, choose a doorway.</h3><div id="promptList" class="prompt-list"></div></aside></div>
    <div class="callout"><strong>You are the authority on your story.</strong><span>This tool can suggest connections. It cannot know your inner life with certainty and is not a substitute for a qualified mental-health professional.</span></div><div class="next-row"><button class="secondary" data-go="now">Back to Right Now</button><button class="primary" data-go="timeline">Build my timeline</button></div>
  </section>
  <section id="timeline" class="workspace"><div class="section-head"><div><p class="eyebrow">Step 2</p><h2>Put the story in the right order.</h2></div><button id="extractBtn" class="secondary">Rebuild from story</button></div><p class="section-copy">Automatic extraction is intentionally conservative. Correct it. Reorder it. The film and reflections should follow <strong>your</strong> chronology, not an AI guess.</p><div id="timelineList" class="timeline"></div><div class="event-add panel"><input id="eventWhen" placeholder="Age 15 / 2004 / later"><input id="eventText" placeholder="What happened?"><button id="addEventBtn" class="secondary">Add event</button></div><div class="next-row"><button class="secondary" data-go="tell">Back</button><button class="primary" data-go="patterns">Show me the patterns</button></div></section>
  <section id="patterns" class="workspace"><div class="section-head"><div><p class="eyebrow">Step 3</p><h2>How might I have become me?</h2></div><span id="aiStatus" class="status">Local reflection ready</span></div><p class="section-copy">Inside of Me looks for repeated themes and possible adaptations. A suggestion is never a diagnosis or a fact until it fits your experience.</p><div class="pattern-actions"><button id="localReflectBtn" class="primary">Run local reflection</button><button id="deepReflectBtn" class="secondary">Try Deep AI Reflection</button></div><div id="patternSummary" class="summary-card panel hidden"></div><div id="patternGrid" class="pattern-grid"></div><div id="deepPanel" class="deep-panel panel hidden"></div><div class="next-row"><button class="secondary" data-go="timeline">Back</button><button class="primary" data-go="film">Reveal my story</button></div></section>
  <section id="film" class="workspace"><div class="section-head"><div><p class="eyebrow">Step 4</p><h2>Once Upon a Life</h2></div><span class="status">Director's Reveal</span></div><p class="section-copy">First we preserve the truthful full storyboard. Then the Director chooses only the moments with enough emotional and cinematic weight to earn premium imagery. Approve those keyframes, watch the short reveal trailer, and only then prepare the full 4-minute film. If Deep Director is configured, choosing reveal moments sends the timeline events for that request; otherwise the browser Director selects them locally.</p><div class="film-title panel"><label for="filmName">Film title</label><input id="filmName" value="Inside of Me: How I Became Me"><label for="filmTheme">What should someone understand when the film ends?</label><textarea id="filmTheme" maxlength="1000" placeholder="Example: What kept me alive also shaped how I learned to love, trust, work, and keep going."></textarea></div><div id="storyArc" class="arc-grid"></div><div id="storyboard" class="storyboard"></div><div class="export-row panel"><div><strong>Take your story with you.</strong><span>Export the private project as JSON or a readable text brief. Premium reveal frames stay in the browser-local visual vault during this founder beta.</span></div><div><button id="exportJsonBtn" class="secondary">Export project</button><button id="exportTextBtn" class="secondary">Export story brief</button></div></div><div class="next-row"><button class="secondary" data-go="patterns">Back</button><button id="restartBtn" class="ghost">Start another story</button></div></section>
  <section class="guardrails panel"><p class="eyebrow">Built-in guardrails</p><h2>Reflection should increase agency, not take it away.</h2><div class="guard-grid"><div><strong>No diagnosis</strong><span>We do not label disorders or claim a single cause for behavior.</span></div><div><strong>Truth is not agreement</strong><span>Feelings are honored while factual claims, assumptions, and motive claims remain distinguishable.</span></div><div><strong>Reject any pattern</strong><span>If a reflection does not fit, dismiss it.</span></div><div><strong>Local-first</strong><span>Your project and reveal frames stay in the browser by default. Requested AI features send only the text needed for that request and do not create a site story database.</span></div></div></section>
</main>
<footer><strong>Inside of Me</strong> · private founder build at bobsome1.com · <span id="version">v0.4.0</span></footer><div id="toast" class="toast" role="status" aria-live="polite"></div><script src="app.js?v=1" defer></script><script src="premium-reveal.js?v=3" defer></script><script src="timeline-edit.js?v=2" defer></script><script src="daily.js?v=4" defer></script></body></html>
