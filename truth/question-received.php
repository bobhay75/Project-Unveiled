<?php
declare(strict_types=1);
session_start();
$result=$_SESSION['tw_research_result']??null;
unset($_SESSION['tw_research_result']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><meta name="robots" content="noindex"><title>Research Queue | Trust-Worthy AI</title><link rel="stylesheet" href="/truth/truth-worthy.css"></head><body>
<header class="topbar"><div class="wrap nav"><a class="brand" href="/">PROJECT <span>UNVEILED</span></a><nav class="navlinks"><a href="/truth/">Truth Trials</a><a href="/book/">Read</a></nav></div></header>
<main><section class="hero"><div class="wrap"><div class="eyebrow">Research Queue</div><h1>Your question is in line for investigation.</h1>
<?php if(is_array($result)&&$result['status']==='draft'): ?><p>A provisional first-pass answer was created below. It is not a final finding and remains open to challenge.</p><article class="card" style="max-width:900px;margin:30px auto;text-align:left"><h2>Provisional research answer</h2><div class="finding"><?php echo nl2br(htmlspecialchars((string)$result['answer'],ENT_QUOTES,'UTF-8')); ?></div><h3 style="margin-top:24px">Sources returned</h3><ul class="rules"><?php foreach(($result['sources']??[]) as $source): ?><li><a href="<?php echo htmlspecialchars((string)$source['url'],ENT_QUOTES,'UTF-8'); ?>" rel="noopener noreferrer" target="_blank"><?php echo htmlspecialchars((string)$source['title'],ENT_QUOTES,'UTF-8'); ?></a></li><?php endforeach; ?></ul></article>
<?php else: ?><p>Your question was recorded privately. Research is not yet configured to produce a source-backed answer, or sufficient evidence was not returned.</p><?php endif; ?>
<div class="manifesto">A submitted question is not a published verdict. Trust-Worthy must show its evidence, name what it cannot establish, and remain open to correction.</div><p><a class="button" href="/truth/">Return to Truth Trials</a></p></div></section></main><footer class="footer"><div class="wrap">Trust-Worthy AI · Don't trust the answer. Examine the evidence.</div></footer></body></html>