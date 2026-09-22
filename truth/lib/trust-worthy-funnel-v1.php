<?php
declare(strict_types=1);

require_once __DIR__ . '/trust-worthy-deep-reuse-v1.php';
require_once __DIR__ . '/trust-worthy-paid.php';

function tw_funnel_web_plan(): array {
    return [
        'origin'=>['Trace origin','Search for the earliest accessible origin of the claim, quote, statistic, image narrative, or allegation. Distinguish original evidence from later repetition.'],
        'primary'=>['Primary evidence','Search specifically for primary or first-party records capable of verifying the material assertions. Say explicitly when no primary record is found.'],
        'corroboration'=>['Corroboration and source diversity','Search for corroboration that is plausibly independent. Detect repeated upstream sources. Do not treat different URLs or domains as proof of independence.'],
        'counter'=>['Counterevidence','Actively try to disprove or materially weaken the claim. Search for contradictory records, corrections, alternate explanations, missing qualifiers, and changed facts.'],
    ];
}

function tw_funnel_unique_sources(array $passes): array {
    $unique=[];
    foreach ($passes as $pass) {
        if (!is_array($pass)) continue;
        foreach (($pass['sources'] ?? []) as $source) {
            if (!is_array($source)) continue;
            $url=(string)($source['url'] ?? '');
            if ($url!=='') $unique[$url]=$source;
        }
    }
    return $unique;
}

function tw_funnel_free_run(string $question, string $context, string $confirmedClaimMap, string $secret, ?callable $onReceipt=null): array {
    [$valid,$question,$context,$error]=tw_validate_trial_input($question,$context);
    if (!$valid) return ['ok'=>false,'message'=>$error];
    $confirmedClaimMap=trim($confirmedClaimMap);
    $cfg=tw_deep_config();
    if ($confirmedClaimMap==='' || mb_strlen($confirmedClaimMap,'UTF-8')>(int)$cfg['max_confirmed_map_characters']) return ['ok'=>false,'message'=>'Confirmed claim map was unavailable.'];
    if ($secret==='') return ['ok'=>false,'message'=>'Private continuation signing is unavailable.'];

    $emit=static function(array $receipt) use ($onReceipt): void { if ($onReceipt!==null) $onReceipt($receipt); };
    $decompose=['ok'=>true,'text'=>$confirmedClaimMap,'sources'=>[],'usage'=>[],'quota_consumed'=>false];
    $emit(tw_deep_receipt('decompose','Claim map confirmed',$decompose));

    $baseSystem=tw_deep_base_system();
    $subject=tw_deep_subject($question,$context);
    $plan=tw_funnel_web_plan();
    $passes=[];
    foreach (['origin','primary','corroboration'] as $stage) {
        [$label,$instruction]=$plan[$stage];
        $pass=tw_deep_provider_pass($baseSystem,$subject."\n\nCONFIRMED CLAIM MAP:\n".$confirmedClaimMap."\n\nPASS OBJECTIVE:\n{$instruction}\n\nReturn concise findings plus material unknowns.",true);
        if (!($pass['ok']??false)) return ['ok'=>false,'message'=>'Free investigation stopped during '.strtolower($label).'.','stage'=>$stage];
        $passes[$stage]=$pass;
        $emit(tw_deep_receipt($stage,$label,$pass));
    }

    $unique=tw_funnel_unique_sources($passes);
    $families=tw_deep_family_metrics(array_values($unique));
    $usage=tw_deep_usage_total(array_merge([$decompose],array_values($passes)));
    $case=tw_paid_create_case([
        'question'=>$question,
        'context'=>$context,
        'claim_map'=>$confirmedClaimMap,
        'decompose'=>$decompose,
        'passes'=>$passes,
        'free_usage'=>$usage,
    ],$secret);
    if (!($case['ok']??false)) return $case;

    return [
        'ok'=>true,
        'handoff'=>[
            'case_id'=>$case['case_id'],
            'checkout_state'=>$case['checkout_state'],
            'checkout_available'=>tw_paid_ready(),
            'amount'=>'2.99',
            'currency'=>'USD',
            'unique_sources'=>count($unique),
            'source_families'=>(int)($families['family_count']??0),
            'completed_stages'=>['decompose','origin','primary','corroboration'],
            'locked_stages'=>['dependency','counter','context','synthesis'],
            'free_usage'=>$usage,
        ],
    ];
}

function tw_funnel_consume_resume(string $caseId,string $resumeToken,string $secret): ?array {
    $token=tw_paid_verify_state($resumeToken,'resume',$secret);
    if (!is_array($token) || ($token['case_id']??'')!==$caseId) return null;
    return tw_paid_update_case($caseId,static function(array $record) {
        if (($record['state']??'')!=='paid' || ($record['resume_consumed_at']??null)!==null) return null;
        $record['state']='resume_authorized';
        $record['resume_consumed_at']=time();
        return $record;
    });
}

function tw_funnel_finish_run(string $caseId, ?callable $onReceipt=null): array {
    $record=tw_paid_load_case($caseId);
    if (!is_array($record) || ($record['state']??'')!=='resume_authorized') return ['ok'=>false,'message'=>'Paid continuation was not authorized.'];
    $claimed=tw_paid_update_case($caseId,static function(array $r) {
        if (($r['state']??'')!=='resume_authorized') return null;
        $r['state']='finishing';
        $r['finish_started_at']=time();
        return $r;
    });
    if (!is_array($claimed) || ($claimed['state']??'')!=='finishing') return ['ok'=>false,'message'=>'Paid continuation was already started.'];

    $payload=is_array($claimed['payload']??null)?$claimed['payload']:[];
    $question=(string)($payload['question']??'');
    $context=(string)($payload['context']??'');
    $claimMap=(string)($payload['claim_map']??'');
    $passes=is_array($payload['passes']??null)?$payload['passes']:[];
    if ($question==='' || $claimMap==='' || !isset($passes['origin'],$passes['primary'],$passes['corroboration'])) return ['ok'=>false,'message'=>'Stored free investigation state was incomplete.'];

    $emit=static function(array $receipt) use ($onReceipt): void { if ($onReceipt!==null) $onReceipt($receipt); };
    $baseSystem=tw_deep_base_system();
    $subject=tw_deep_subject($question,$context);

    $dependencyPacket=tw_deep_reuse_packet($passes,['origin','primary','corroboration'],3600,16);
    $dependency=tw_deep_provider_pass($baseSystem,$subject."\n\nCONFIRMED CLAIM MAP:\n".$claimMap."\n\nEXISTING EVIDENCE RECEIPTS:\n".$dependencyPacket."\n\nDEPENDENCY AUDIT OBJECTIVE:\nUsing only these already-collected receipts, identify likely shared upstream evidence and echo chains. Do not browse. If the receipts cannot establish a dependency, label it unresolved rather than guessing.",false);
    if (!($dependency['ok']??false)) return ['ok'=>false,'message'=>'Paid continuation stopped during dependency audit.','stage'=>'dependency'];
    $passes['dependency']=$dependency;
    $dependencyReceipt=tw_deep_receipt('dependency','Source dependency and echo tracing',$dependency);
    $dependencyReceipt['research_mode']='reuse_no_web';
    $emit($dependencyReceipt);

    [$counterLabel,$counterInstruction]=tw_funnel_web_plan()['counter'];
    $counter=tw_deep_provider_pass($baseSystem,$subject."\n\nCONFIRMED CLAIM MAP:\n".$claimMap."\n\nPASS OBJECTIVE:\n{$counterInstruction}\n\nReturn concise findings plus material unknowns.",true);
    if (!($counter['ok']??false)) return ['ok'=>false,'message'=>'Paid continuation stopped during counterevidence.','stage'=>'counter'];
    $passes['counter']=$counter;
    $emit(tw_deep_receipt('counter',$counterLabel,$counter));

    $contextPacket=tw_deep_reuse_packet($passes,['origin','primary','corroboration','dependency','counter'],3000,10);
    $contextPass=tw_deep_provider_pass($baseSystem,$subject."\n\nCONFIRMED CLAIM MAP:\n".$claimMap."\n\nEXISTING EVIDENCE RECEIPTS:\n".$contextPacket."\n\nCONTEXT + CHRONOLOGY OBJECTIVE:\nBuild chronology and material context only from evidence already collected. Do not browse. Mark unresolved chronology gaps instead of filling them from memory.",false);
    if (!($contextPass['ok']??false)) return ['ok'=>false,'message'=>'Paid continuation stopped during context reconstruction.','stage'=>'context'];
    $passes['context']=$contextPass;
    $contextReceipt=tw_deep_receipt('context','Context and chronology',$contextPass);
    $contextReceipt['research_mode']='reuse_no_web';
    $emit($contextReceipt);

    $unique=tw_funnel_unique_sources($passes);
    $families=tw_deep_family_metrics(array_values($unique));
    $counterFamilies=tw_deep_family_metrics($passes['counter']['sources']??[]);
    $corroborationFamilies=tw_deep_family_metrics($passes['corroboration']['sources']??[]);
    $cfg=tw_deep_config();
    $evidenceFloorMet=count($unique)>=(int)$cfg['minimum_unique_sources']
        && (int)$families['family_count']>=(int)$cfg['minimum_source_families']
        && count($passes['counter']['sources']??[])>=(int)$cfg['minimum_counter_sources']
        && (int)$counterFamilies['family_count']>=(int)$cfg['minimum_counter_families']
        && count($passes['corroboration']['sources']??[])>=(int)$cfg['minimum_corroboration_sources']
        && (int)$corroborationFamilies['family_count']>=(int)$cfg['minimum_corroboration_families'];

    $packet=tw_deep_reuse_packet($passes,['origin','primary','corroboration','dependency','counter','context'],3300,8);
    $instruction=$evidenceFloorMet
        ? 'The evidence floor is met. Begin with exactly: VERDICT: one of SUPPORTED, LIKELY, MIXED, UNLIKELY, CONTRADICTED; then PROBABILITY: integer 0-100. Explain verified facts, inferences, misleading framing, strongest evidence, strongest counterevidence, dependency risks, distinct evidence paths, and unresolved unknowns.'
        : 'The evidence floor is NOT met. Begin with exactly: VERDICT: INSUFFICIENT EVIDENCE — NO VERDICT and PROBABILITY: NONE. Explain the failed gate, evidence found, dependency risks, and unresolved gaps.';
    $synthesis=tw_deep_provider_pass($baseSystem,$subject."\n\nCONFIRMED CLAIM MAP:\n".$claimMap."\n\nCOMPACT EVIDENCE PACKET:\n".$packet."\n\nUnique URLs: ".count($unique)."; source families: ".(int)$families['family_count'].". Domain diversity is not proof of editorial independence.\n\n".$instruction,false);
    if (!($synthesis['ok']??false)) return ['ok'=>false,'message'=>'Paid continuation stopped during synthesis.','stage'=>'synthesis'];

    $verdict='INSUFFICIENT EVIDENCE — NO VERDICT';
    if (preg_match('/^VERDICT:\s*(.+)$/mi',(string)$synthesis['text'],$m)) $verdict=trim($m[1]);
    $probability=null;
    if ($evidenceFloorMet && preg_match('/^PROBABILITY:\s*(\d{1,3})\s*$/mi',(string)$synthesis['text'],$m)) {
        $candidate=(int)$m[1]; if ($candidate>=0 && $candidate<=100) $probability=$candidate;
    }
    if (!$evidenceFloorMet) { $verdict='INSUFFICIENT EVIDENCE — NO VERDICT'; $probability=null; }

    $freeUsage=is_array($payload['free_usage']??null)?$payload['free_usage']:[];
    $paidUsage=tw_deep_usage_total([$dependency,$counter,$contextPass,$synthesis]);
    $usage=[
        'input_tokens'=>(int)($freeUsage['input_tokens']??0)+(int)$paidUsage['input_tokens'],
        'output_tokens'=>(int)($freeUsage['output_tokens']??0)+(int)$paidUsage['output_tokens'],
        'reasoning_tokens'=>(int)($freeUsage['reasoning_tokens']??0)+(int)$paidUsage['reasoning_tokens'],
    ];
    $usage['total_tokens']=$usage['input_tokens']+$usage['output_tokens'];
    $finalReceipt=['stage'=>'synthesis','label'=>'Finding generated','completed'=>true,'source_count'=>count($unique),'source_family_count'=>(int)$families['family_count'],'evidence_floor_met'=>$evidenceFloorMet,'verdict'=>$verdict,'probability'=>$probability,'usage'=>$usage,'at_utc'=>gmdate('c')];
    $emit($finalReceipt);

    $result=['ok'=>true,'investigation_id'=>'TW-P-'.strtoupper(substr($caseId,0,12)),'claim'=>$question,'claim_map'=>$claimMap,'sources'=>array_values($unique),'metrics'=>['unique_sources'=>count($unique),'source_families'=>(int)$families['family_count'],'counter_sources'=>count($passes['counter']['sources']??[]),'counter_families'=>(int)$counterFamilies['family_count'],'corroboration_sources'=>count($passes['corroboration']['sources']??[]),'corroboration_families'=>(int)$corroborationFamilies['family_count'],'research_profile'=>'paid_evidence_reuse_v1','evidence_floor_met'=>$evidenceFloorMet,'usage'=>$usage],'verdict'=>$verdict,'probability'=>$probability,'analysis'=>$synthesis['text']];
    tw_paid_update_case($caseId,static function(array $r) use ($result): array { $r['state']='completed'; $r['completed_at']=time(); $r['result']=$result; return $r; });
    return $result;
}
