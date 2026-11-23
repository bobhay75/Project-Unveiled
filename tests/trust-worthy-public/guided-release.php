<?php
declare(strict_types=1);
$private = sys_get_temp_dir() . '/tw-guided-' . bin2hex(random_bytes(8));
if (!mkdir($private, 0750) || !chmod($private, 0750)) throw new RuntimeException('Test storage unavailable');
define('TW_PRIVATE_DIR_OVERRIDE', $private);
require_once dirname(__DIR__, 2) . '/truth/lib/trust-worthy-funnel-v1.php';
$count = 0;
$check = static function(bool $ok, string $label) use (&$count): void { $count++; if (!$ok) throw new RuntimeException($label); };
try {
    foreach ([false, '', '0', 'true', ' 1'] as $flag) {
        putenv($flag === false ? 'TW_GUIDED_RESEARCH_ENABLED' : 'TW_GUIDED_RESEARCH_ENABLED='.$flag);
        $check(!tw_guided_enabled(), 'Guided release enabled without explicit exact flag');
    }
    putenv('TW_GUIDED_RESEARCH_ENABLED=1');
    $check(tw_guided_enabled(), 'Explicit guided flag was rejected');
    $server = ['HTTP_HOST'=>'bobsome1.com','HTTP_ORIGIN'=>'https://bobsome1.com','HTTP_SEC_FETCH_SITE'=>'same-origin'];
    $check(tw_guided_valid_origin($server), 'Same-origin request denied');
    $check(tw_guided_valid_origin(['HTTP_HOST'=>'www.bobsome1.com:443','HTTP_ORIGIN'=>'https://www.bobsome1.com:443/']), 'Explicit default port denied');
    foreach (['null', '', 'http://bobsome1.com', 'https://evil.example', 'https://bobsome1.com:444', 'https://user@bobsome1.com', 'https://bobsome1.com/path', 'https://bobsome1.com/?x=1', 'https://bobsome1.com/#x'] as $origin) {
        $check(!tw_guided_valid_origin(array_replace($server, ['HTTP_ORIGIN'=>$origin])), 'Unsafe origin accepted');
    }
    $check(!tw_guided_valid_origin(array_replace($server, ['HTTP_SEC_FETCH_SITE'=>'cross-site'])), 'Cross-site request accepted');

    $response = ['id'=>'resp_test','status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>'A bounded synthetic evidence statement.']]]]];
    $check(tw_deep_parse_provider_response($response, false)['ok'], 'Completed non-search pass rejected');
    foreach (['incomplete','failed','queued','in_progress',''] as $status) {
        $check(!tw_deep_parse_provider_response(array_replace($response, ['status'=>$status]), false)['ok'], 'Unfinished provider output accepted');
    }
    $check(!tw_deep_parse_provider_response($response, true)['ok'], 'Web stage accepted without an actual search');
    $web = ['type'=>'web_search_call','status'=>'completed','action'=>['sources'=>[
        ['url'=>'https://www.example.com/evidence?utm_source=test','title'=>'Evidence'],
        ['url'=>'https://www.example.com/evidence','title'=>'Duplicate'],
        ['url'=>'https://127.0.0.1/private','title'=>'Private'],
        ['url'=>'https://user:pass@example.com/secret','title'=>'Credentials'],
        ['url'=>'javascript:alert(1)','title'=>'Script'],
        ['url'=>'https://server.local/private','title'=>'Local'],
        ['url'=>['malformed'],'title'=>[]],
    ]]];
    $withWeb = $response; $withWeb['output'][] = $web;
    $parsed = tw_deep_parse_provider_response($withWeb, true);
    $check($parsed['ok'] && count($parsed['sources']) === 1, 'Unsafe or duplicate citations entered source receipts');
    foreach (['failed','incomplete','in_progress','searching',''] as $status) {
        $unfinished = $withWeb; $unfinished['output'][1]['status'] = $status;
        $check(!tw_deep_parse_provider_response($unfinished, true)['ok'], 'Unfinished web search generated a completed receipt');
    }
    $check(!tw_deep_parse_provider_response($withWeb, false)['ok'], 'Unrequested search accepted in non-web stage');
    $tooMany = $withWeb; $tooMany['output'][] = $web; $tooMany['output'][] = $web;
    $check(!tw_deep_parse_provider_response($tooMany, true)['ok'], 'Search count exceeded stage allowance');
    foreach ([['output'=>'broken'],['output'=>[['type'=>'message','content'=>'broken']]],['output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>[]]]]]]] as $bad) {
        $check(!tw_deep_parse_provider_response(array_replace($response,$bad), false)['ok'], 'Malformed provider payload was accepted');
    }
    $families = tw_deep_family_metrics([
        ['url'=>'https://a.example.com/one','family'=>'invented-one'],
        ['url'=>'https://b.example.com/two','family'=>'invented-two'],
        ['url'=>'https://127.0.0.1/private','family'=>'invented-three'],
    ]);
    $check($families['family_count'] === 1, 'Untrusted family labels manufactured source diversity');

    foreach ([0, 47, 100] as $score) {
        $finding = "VERDICT: MIXED\nPROBABILITY: {$score}\nSynthetic evidence and limitations.";
        $parsedFinding = tw_deep_parse_finding($finding, true);
        $check($parsedFinding['ok'] && $parsedFinding['probability'] === $score, 'Valid scored finding rejected');
        $check(!tw_deep_parse_finding($finding, false)['ok'], 'Rendered scored finding bypassed failed evidence floor');
    }
    $noVerdict = "VERDICT: INSUFFICIENT EVIDENCE — NO VERDICT\nPROBABILITY: NONE\nThe synthetic evidence remains insufficient.";
    foreach ([false, true] as $floor) {
        $parsedFinding = tw_deep_parse_finding($noVerdict, $floor);
        $check($parsedFinding['ok'] && $parsedFinding['probability'] === null, 'Conservative no-verdict finding rejected');
    }
    foreach ([
        "VERDICT: SUPPORTED\nPROBABILITY: 101\nInvalid score.",
        "VERDICT: SUPPORTED\nPROBABILITY: -1\nInvalid score.",
        "VERDICT: SUPPORTED\nPROBABILITY: 50.5\nInvalid score.",
        "VERDICT: SUPPORTED\nPROBABILITY: NONE\nMissing score.",
        "VERDICT: INVENTED\nPROBABILITY: 99\nUnknown verdict.",
        "VERDICT: INSUFFICIENT EVIDENCE — NO VERDICT\nPROBABILITY: 99\nContradictory score.",
        "VERDICT: SUPPORTED\nPROBABILITY: 99",
        "Narrative first.\nVERDICT: SUPPORTED\nPROBABILITY: 99\nUnexpected header placement.",
        $noVerdict . "\nVERDICT: SUPPORTED\nPROBABILITY: 99",
    ] as $invalidFinding) {
        $check(!tw_deep_parse_finding($invalidFinding, true)['ok'], 'Malformed or contradictory finding was published');
    }

    $secret = str_repeat('a',64);
    $new = tw_paid_create_case(['question'=>'Synthetic payment state regression'], $secret);
    $check($new['ok'], 'Synthetic case not created');
    $id = $new['case_id']; $orderId = 'ORDER123';
    tw_paid_update_case($id, static function(array $r) use($orderId): array { $r['paypal_order_id']=$orderId; return $r; });
    $order = ['id'=>$orderId,'status'=>'COMPLETED','purchase_units'=>[['custom_id'=>$id,'payments'=>['captures'=>[['id'=>'CAPTURE123','status'=>'COMPLETED','amount'=>['currency_code'=>'USD','value'=>'2.99']]]]]]];
    $check(tw_paid_mark_captured($id, 'WRONG123', $order) === null, 'Wrong order was recorded');
    $multiple = $order; $multiple['purchase_units'][0]['payments']['captures'][] = $multiple['purchase_units'][0]['payments']['captures'][0];
    $check(!tw_paid_order_is_exact($multiple, $id), 'Multiple captures accepted as exact one-time payment');
    $check(tw_paid_mark_captured($id, $orderId, $order)['state'] === 'paid', 'Exact payment not recorded');
    $check(tw_paid_mark_captured($id, $orderId, $order) === null, 'Concurrent duplicate payment return rewound paid state');
    $resume = tw_paid_sign_state($id,'resume',$secret,600);
    $check(tw_paid_consume_resume($id,$resume,$secret)['state'] === 'resume_authorized', 'First direct resume rejected');
    $check(tw_paid_consume_resume($id,$resume,$secret) === null, 'Direct resume replay accepted');
    tw_paid_update_case($id, static function(array $r): array { $r['state']='finishing'; return $r; });
    $before = file_get_contents(tw_paid_case_path($id));
    $check(tw_paid_mark_captured($id,$orderId,$order) === null, 'Late return rewound running investigation');
    $check(file_get_contents(tw_paid_case_path($id)) === $before, 'Rejected replay modified the case');
    $failed = tw_paid_mark_failed($id, 'counter');
    $check($failed['state'] === 'needs_review' && $failed['capture_id'] === 'CAPTURE123', 'Interrupted paid case lost its payment receipt');
    $check($failed['failure_stage'] === 'counter' && $failed['payload']['question'] === 'Synthetic payment state regression', 'Interrupted paid case lost its research context');
    $check(tw_paid_consume_resume($id,$resume,$secret) === null, 'Failed paid case silently authorized another paid provider run');
    $check(tw_paid_mark_captured($id,$orderId,$order) === null, 'Payment return rewound a case awaiting operator review');
    tw_paid_update_case($id, static function(array $r): array { $r['state']='completed'; return $r; });
    $check(tw_paid_mark_failed($id,'interrupted') === null, 'Shutdown cleanup corrupted a completed case');
    tw_paid_update_case($id, static function(array $r): array { $r['expires_at']=time()-1; return $r; });
    $check(tw_paid_update_case($id, static fn(array $r): array=>$r) === null, 'Expired case was mutable');
    $check(tw_paid_load_case($id) === null, 'Expired case was loaded');
    $check(!tw_paid_http('GET','http://api-m.paypal.com/v2/checkout/orders/ORDER123',[])['ok'], 'Payment transport permitted plaintext');
    $check(!tw_paid_http('GET','https://api-m.paypal.com.evil.example/test',[])['ok'], 'Payment transport permitted an unrelated host');
    $check(!tw_paid_http('GET','https://user@api-m.paypal.com/test',[])['ok'], 'Payment transport permitted URL credentials');

    $incomplete = tw_paid_create_case(['question'=>'Synthetic incomplete paid research'], $secret);
    $incompleteId = $incomplete['case_id'];
    tw_paid_update_case($incompleteId, static function(array $r): array { $r['state']='resume_authorized'; $r['capture_id']='CAPTURE456'; return $r; });
    $failedRun = tw_funnel_finish_run($incompleteId);
    $check(!$failedRun['ok'] && $failedRun['stage'] === 'stored_state', 'Incomplete paid case attempted provider work');
    $check(str_contains($failedRun['message'],'Do not pay again'), 'Failed paid continuation did not explain the no-double-payment recovery path');
    $check(tw_paid_load_case($incompleteId)['state'] === 'needs_review', 'Actual failed paid runner did not preserve an operator review state');
    echo "Guided release boundary checks passed: {$count}\n";
} finally {
    putenv('TW_GUIDED_RESEARCH_ENABLED');
    foreach (glob($private.'/*') ?: [] as $path) @unlink($path);
    @rmdir($private);
}
