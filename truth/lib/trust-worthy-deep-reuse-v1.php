<?php
declare(strict_types=1);

require_once __DIR__ . '/trust-worthy-deep.php';

/**
 * Experimental lower-amplification Deep profile.
 *
 * The proven Deep engine remains unchanged. This profile is for controlled
 * release testing: it keeps separate, receipt-backed investigation stages but
 * reuses already-collected evidence for dependency analysis and chronology
 * instead of issuing another broad web search for those stages.
 */

function tw_deep_reuse_compact_pass(string $stage, array $pass, int $maxSummaryChars = 4200, int $maxSources = 18): string {
    $summary = trim((string)($pass['text'] ?? ''));
    if (mb_strlen($summary, 'UTF-8') > $maxSummaryChars) {
        $summary = mb_substr($summary, 0, $maxSummaryChars, 'UTF-8') . "\n[summary truncated for evidence-reuse budget]";
    }

    $lines = [strtoupper($stage) . ' FINDINGS:', $summary !== '' ? $summary : '[no narrative findings]'];
    $sources = is_array($pass['sources'] ?? null) ? $pass['sources'] : [];
    if ($sources !== []) {
        $lines[] = 'SOURCE DESCRIPTORS (titles + domain families; URLs intentionally omitted from the reuse prompt):';
        $shown = 0;
        foreach ($sources as $source) {
            if (!is_array($source)) continue;
            $title = trim((string)($source['title'] ?? 'Source'));
            $family = trim((string)($source['family'] ?? ''));
            if ($family === '') $family = tw_deep_source_family((string)($source['url'] ?? ''));
            $lines[] = '- ' . ($title !== '' ? $title : 'Source') . ($family !== '' ? ' [' . $family . ']' : '');
            $shown++;
            if ($shown >= $maxSources) break;
        }
        if (count($sources) > $shown) $lines[] = '- [' . (count($sources) - $shown) . ' additional source descriptors omitted from compact prompt]';
    }
    return implode("\n", $lines);
}

function tw_deep_reuse_packet(array $passes, array $stages, int $summaryChars = 4200, int $maxSources = 18): string {
    $chunks = [];
    foreach ($stages as $stage) {
        if (!isset($passes[$stage]) || !is_array($passes[$stage])) continue;
        $chunks[] = tw_deep_reuse_compact_pass((string)$stage, $passes[$stage], $summaryChars, $maxSources);
    }
    return implode("\n\n", $chunks);
}

function tw_deep_run_reuse_v1(string $question, string $context = '', ?callable $onReceipt = null, string $confirmedClaimMap = ''): array {
    [$valid, $question, $context, $error] = tw_validate_trial_input($question, $context);
    if (!$valid) return ['ok'=>false,'message'=>$error];

    $emit = static function(array $receipt) use ($onReceipt): void {
        if ($onReceipt !== null) $onReceipt($receipt);
    };

    $baseSystem = tw_deep_base_system();
    $subject = tw_deep_subject($question, $context);
    $cfg = tw_deep_config();

    $confirmedClaimMap = trim($confirmedClaimMap);
    if ($confirmedClaimMap !== '') {
        if (mb_strlen($confirmedClaimMap, 'UTF-8') > (int)$cfg['max_confirmed_map_characters']) {
            return ['ok'=>false,'message'=>'Confirmed claim map was too large.'];
        }
        $decompose = ['ok'=>true,'text'=>$confirmedClaimMap,'sources'=>[],'usage'=>[],'quota_consumed'=>false];
        $emit(tw_deep_receipt('decompose', 'Claim map confirmed', $decompose));
    } else {
        $decompose = tw_deep_claim_map($question, $context);
        if (!($decompose['ok'] ?? false)) return $decompose;
        $emit(tw_deep_receipt('decompose', 'Claim decomposed', $decompose));
    }

    $passes = [];
    $webPlan = [
        'origin' => ['Trace origin', 'Search for the earliest accessible origin of the claim, quote, statistic, image narrative, or allegation. Distinguish original evidence from later repetition. Identify likely first publication or earliest confirmed appearance and uncertainty.'],
        'primary' => ['Primary evidence', 'Search specifically for primary or first-party records capable of verifying the material assertions: court records, laws, filings, transcripts, datasets, official documents, original studies, direct statements, archived pages, or equivalent records. Say explicitly when no primary record is found.'],
        'corroboration' => ['Corroboration and source diversity', 'Search for corroboration that is plausibly independent. Detect when multiple outlets appear to repeat one underlying source. Prefer direct access to records and independently reported evidence. Do not treat different URLs or domains as proof of independence.'],
        'counter' => ['Counterevidence', 'Actively try to disprove or materially weaken the claim. Search for contradictory records, corrections, alternate explanations, expert criticism, missing qualifiers, changed facts, and evidence that the framing overstates what the underlying event proves.'],
    ];

    foreach ($webPlan as $stage => [$label, $instruction]) {
        $pass = tw_deep_provider_pass(
            $baseSystem,
            $subject . "\n\nCONFIRMED CLAIM MAP:\n" . $decompose['text'] . "\n\nPASS OBJECTIVE:\n{$instruction}\n\nReturn concise findings plus material unknowns. Avoid redundant background that another pass can infer from the receipt.",
            true
        );
        if (!($pass['ok'] ?? false)) {
            return ['ok'=>false,'message'=>'Deep reuse investigation stopped during ' . strtolower($label) . '.','stage'=>$stage,'detail'=>$pass['message'] ?? 'unknown'];
        }
        $passes[$stage] = $pass;
        $emit(tw_deep_receipt($stage, $label, $pass));
    }

    $dependencyPacket = tw_deep_reuse_packet($passes, ['origin','primary','corroboration'], 3600, 16);
    $dependency = tw_deep_provider_pass(
        $baseSystem,
        $subject . "\n\nCONFIRMED CLAIM MAP:\n" . $decompose['text'] . "\n\nEXISTING EVIDENCE RECEIPTS:\n" . $dependencyPacket . "\n\nDEPENDENCY AUDIT OBJECTIVE:\nUsing only these already-collected receipts, identify likely shared upstream evidence: wire stories, press releases, court filings, studies, datasets, witnesses, screenshots, archived pages, or originating claims. Separate likely echo chains from genuinely distinct evidence paths. Do not browse. If the receipts cannot establish a dependency, label it unresolved rather than guessing. Return concise findings and unresolved dependency questions.",
        false
    );
    if (!($dependency['ok'] ?? false)) return ['ok'=>false,'message'=>'Deep reuse investigation stopped during dependency audit.','stage'=>'dependency','detail'=>$dependency['message'] ?? 'unknown'];
    $passes['dependency'] = $dependency;
    $dependencyReceipt = tw_deep_receipt('dependency','Source dependency and echo tracing (evidence reuse)',$dependency);
    $dependencyReceipt['audited_source_count'] = count(array_merge($passes['origin']['sources'] ?? [], $passes['primary']['sources'] ?? [], $passes['corroboration']['sources'] ?? []));
    $dependencyReceipt['research_mode'] = 'reuse_no_web';
    $emit($dependencyReceipt);

    $contextPacket = tw_deep_reuse_packet($passes, ['origin','primary','corroboration','dependency','counter'], 3000, 10);
    $contextPass = tw_deep_provider_pass(
        $baseSystem,
        $subject . "\n\nCONFIRMED CLAIM MAP:\n" . $decompose['text'] . "\n\nEXISTING EVIDENCE RECEIPTS:\n" . $contextPacket . "\n\nCONTEXT + CHRONOLOGY OBJECTIVE:\nBuild the chronology and material context from the evidence already collected. Identify what happened before and after, which facts are current versus historical, and which omitted facts materially change interpretation. Do not browse. If a chronology gap requires new research, mark it unresolved instead of filling it from memory.",
        false
    );
    if (!($contextPass['ok'] ?? false)) return ['ok'=>false,'message'=>'Deep reuse investigation stopped during context reconstruction.','stage'=>'context','detail'=>$contextPass['message'] ?? 'unknown'];
    $passes['context'] = $contextPass;
    $contextReceipt = tw_deep_receipt('context','Context and chronology (evidence reuse)',$contextPass);
    $contextReceipt['research_mode'] = 'reuse_no_web';
    $emit($contextReceipt);

    $uniqueSources = [];
    foreach ($passes as $pass) {
        foreach (($pass['sources'] ?? []) as $source) {
            if (!is_array($source)) continue;
            $url = (string)($source['url'] ?? '');
            if ($url !== '') $uniqueSources[$url] = $source;
        }
    }

    $allFamilyMetrics = tw_deep_family_metrics(array_values($uniqueSources));
    $counterSources = count($passes['counter']['sources'] ?? []);
    $counterFamilyMetrics = tw_deep_family_metrics($passes['counter']['sources'] ?? []);
    $corroborationSources = count($passes['corroboration']['sources'] ?? []);
    $corroborationFamilyMetrics = tw_deep_family_metrics($passes['corroboration']['sources'] ?? []);
    $sourceFamilies = (int)$allFamilyMetrics['family_count'];
    $counterFamilies = (int)$counterFamilyMetrics['family_count'];
    $corroborationFamilies = (int)$corroborationFamilyMetrics['family_count'];

    $evidenceFloorMet = count($uniqueSources) >= $cfg['minimum_unique_sources']
        && $sourceFamilies >= $cfg['minimum_source_families']
        && $counterSources >= $cfg['minimum_counter_sources']
        && $counterFamilies >= $cfg['minimum_counter_families']
        && $corroborationSources >= $cfg['minimum_corroboration_sources']
        && $corroborationFamilies >= $cfg['minimum_corroboration_families'];

    $synthesisPacket = tw_deep_reuse_packet($passes, ['origin','primary','corroboration','dependency','counter','context'], 3300, 8);
    $diversityNote = "SOURCE-DIVERSITY RECEIPT:\nUnique URLs: " . count($uniqueSources) . "\nDistinct domain families: {$sourceFamilies}\nCounterevidence domain families: {$counterFamilies}\nCorroboration domain families: {$corroborationFamilies}\nThe dependency audit used already-collected receipts and did not browse. Domain-family diversity is not proof of editorial independence.";

    $synthesisInstruction = $evidenceFloorMet
        ? "The evidence floor is met. Begin with exactly: VERDICT: one of SUPPORTED, LIKELY, MIXED, UNLIKELY, CONTRADICTED; then PROBABILITY: integer 0-100. Explain verified facts, inferences, misleading framing or omissions, strongest evidence, strongest counterevidence, dependency/echo risks, genuinely distinct evidence paths, unresolved unknowns, and why the probability is not higher or lower."
        : "The evidence floor is NOT met. Begin with exactly: VERDICT: INSUFFICIENT EVIDENCE — NO VERDICT and PROBABILITY: NONE. Explain the failed gate, evidence found, dependency risks, unresolved gaps, and what additional research would be required.";

    $synthesis = tw_deep_provider_pass(
        $baseSystem,
        $subject . "\n\nCONFIRMED CLAIM MAP:\n" . $decompose['text'] . "\n\nCOMPACT EVIDENCE PACKET:\n" . $synthesisPacket . "\n\n" . $diversityNote . "\n\n" . $synthesisInstruction,
        false
    );
    if (!($synthesis['ok'] ?? false)) return $synthesis;

    $verdict = 'INSUFFICIENT EVIDENCE — NO VERDICT';
    if (preg_match('/^VERDICT:\s*(.+)$/mi', (string)$synthesis['text'], $m)) $verdict = trim($m[1]);
    $probability = null;
    if ($evidenceFloorMet && preg_match('/^PROBABILITY:\s*(\d{1,3})\s*$/mi', (string)$synthesis['text'], $m)) {
        $candidate = (int)$m[1];
        if ($candidate >= 0 && $candidate <= 100) $probability = $candidate;
    }
    if (!$evidenceFloorMet) {
        $verdict = 'INSUFFICIENT EVIDENCE — NO VERDICT';
        $probability = null;
    }

    $usage = tw_deep_usage_total(array_merge([$decompose], array_values($passes), [$synthesis]));
    $finalReceipt = [
        'stage'=>'synthesis',
        'label'=>'Finding generated',
        'completed'=>true,
        'source_count'=>count($uniqueSources),
        'source_family_count'=>$sourceFamilies,
        'counter_family_count'=>$counterFamilies,
        'corroboration_family_count'=>$corroborationFamilies,
        'evidence_floor_met'=>$evidenceFloorMet,
        'verdict'=>$verdict,
        'probability'=>$probability,
        'usage'=>$usage,
        'research_profile'=>'evidence_reuse_v1',
        'at_utc'=>gmdate('c'),
    ];
    $emit($finalReceipt);

    try { $investigationId='TW-R1-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))); }
    catch (Throwable $error) { $investigationId='TW-R1-' . gmdate('Ymd-His'); }

    $receipts = [tw_deep_receipt('decompose', $confirmedClaimMap !== '' ? 'Claim map confirmed' : 'Claim decomposed', $decompose)];
    foreach (['origin','primary','corroboration'] as $stage) $receipts[] = tw_deep_receipt($stage, $webPlan[$stage][0], $passes[$stage]);
    $receipts[] = $dependencyReceipt;
    $receipts[] = tw_deep_receipt('counter', $webPlan['counter'][0], $passes['counter']);
    $receipts[] = $contextReceipt;
    $receipts[] = $finalReceipt;

    return [
        'ok'=>true,
        'investigation_id'=>$investigationId,
        'claim'=>$question,
        'claim_map'=>$decompose['text'],
        'receipts'=>$receipts,
        'sources'=>array_values($uniqueSources),
        'metrics'=>[
            'unique_sources'=>count($uniqueSources),
            'source_families'=>$sourceFamilies,
            'counter_sources'=>$counterSources,
            'counter_families'=>$counterFamilies,
            'corroboration_sources'=>$corroborationSources,
            'corroboration_families'=>$corroborationFamilies,
            'dependency_audited_sources'=>(int)($dependencyReceipt['audited_source_count'] ?? 0),
            'dependency_research_mode'=>'reuse_no_web',
            'context_research_mode'=>'reuse_no_web',
            'source_independence_status'=>'dependency_audit_from_existing_receipts_plus_domain_family_heuristic',
            'research_profile'=>'evidence_reuse_v1',
            'evidence_floor_met'=>$evidenceFloorMet,
            'usage'=>$usage,
        ],
        'verdict'=>$verdict,
        'probability'=>$probability,
        'analysis'=>$synthesis['text'],
    ];
}
