<?php
declare(strict_types=1);
$caseId=(string)($_GET['case']??'');
if(!preg_match('/^TW-CLAIM-\d{6}$/',$caseId)) $caseId='your Truth Trial';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="robots" content="noindex">
  <title>Challenge Received | Trust-Worthy AI</title>
  <link rel="stylesheet" href="/truth/truth-worthy.css">
</head>
<body>
  <header class="topbar"><div class="wrap nav"><a class="brand" href="/">PROJECT <span>UNVEILED</span></a><nav class="navlinks"><a href="/truth/">Truth Trials</a><a href="/book/">Read</a></nav></div></header>
  <main><section class="hero"><div class="wrap">
    <div class="eyebrow">Evidence Challenge Received</div>
    <h1>Thank you for putting the finding to the test.</h1>
    <p>Your challenge to <strong><?php echo htmlspecialchars($caseId,ENT_QUOTES,'UTF-8'); ?></strong> was received. Trust-Worthy will treat it as evidence to investigate—not as a comment to defeat.</p>
    <div class="manifesto">A serious challenge can strengthen, weaken, revise, or overturn a finding. That is the point of the trial.</div>
    <p><a class="button" href="/truth/">Return to Truth Trials</a></p>
  </div></section></main>
  <footer class="footer"><div class="wrap">Trust-Worthy AI · Don't trust the answer. Examine the evidence.</div></footer>
</body>
</html>
