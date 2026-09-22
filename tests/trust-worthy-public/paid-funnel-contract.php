<?php
declare(strict_types=1);

$root=dirname(__DIR__,2);
$funnel=file_get_contents($root.'/truth/lib/trust-worthy-funnel-v1.php');
$paid=file_get_contents($root.'/truth/lib/trust-worthy-paid.php');
$stream=file_get_contents($root.'/truth/deep-stream.php');
$start=file_get_contents($root.'/truth/paypal-start.php');
$return=file_get_contents($root.'/truth/paypal-return.php');
$resume=file_get_contents($root.'/truth/paid-resume.php');
$ui=file_get_contents($root.'/truth/investigation-ui.js');
foreach(compact('funnel','paid','stream','start','return','resume','ui') as $name=>$text){if(!is_string($text)||$text==='')throw new RuntimeException("missing paid funnel source: {$name}");}
$has=static function(string $text,string $needle,string $message):void{if(!str_contains($text,$needle))throw new RuntimeException($message);};
$not=static function(string $text,string $needle,string $message):void{if(str_contains($text,$needle))throw new RuntimeException($message);};

$has($paid,"'amount'=>'2.99'",'server price is not fixed at 2.99');
$has($paid,"'currency'=>'USD'",'server currency is not fixed at USD');
$has($paid,"'custom_id'=>\$caseId",'PayPal order is not bound to investigation case');
$has($paid,"(\$amount['value'] ?? '') === '2.99'",'capture verifier does not enforce exact price');
$has($paid,"(\$amount['currency_code'] ?? '') === 'USD'",'capture verifier does not enforce USD');
$has($paid,"(\$order['status'] ?? '') !== 'COMPLETED'",'capture verifier accepts non-completed orders');
$not($start,"\$_POST['amount']",'client can supply checkout amount');
$not($start,"\$_POST['currency']",'client can supply checkout currency');
$has($start,"tw_paid_verify_state(\$state,'checkout'",'checkout start lacks signed state verification');
$has($return,'tw_paid_capture_paypal_order','PayPal return does not verify/capture server-side');
$has($return,"(\$case['state']??'')!=='awaiting_payment'",'PayPal return can replay against an already processed case');

$has($funnel,"foreach (['origin','primary','corroboration'] as \$stage)",'free phase does not stop after corroboration');
$has($funnel,"'locked_stages'=>['dependency','counter','context','synthesis']",'paid stages are not explicit at handoff');
$has($funnel,'tw_paid_create_case','free phase does not persist resumable investigation state');
$has($funnel,'$dependency=tw_deep_provider_pass','paid phase lacks dependency audit');
$has($funnel,'$counter=tw_deep_provider_pass','paid phase lacks counterevidence');
$has($funnel,'$contextPass=tw_deep_provider_pass','paid phase lacks context reconstruction');
$has($funnel,'$synthesis=tw_deep_provider_pass','paid phase lacks final synthesis');
$has($funnel,"if ((\$record['state']??'')!=='paid' || (\$record['resume_consumed_at']??null)!==null) return null;",'resume authorization is not one-time');
$has($funnel,"\$r['state']='finishing'",'paid continuation does not claim case before provider work');

$has($stream,'if (tw_paid_ready())','paid split is not fail-closed behind owner configuration');
$has($stream,"'type'=>'paywall'",'free stream does not emit paid handoff');
$has($stream,'tw_deep_run(','legacy full Deep fallback was removed');
$has($resume,'tw_funnel_consume_resume','paid resume endpoint does not consume one-time resume authorization');
$has($resume,'tw_funnel_finish_run','paid resume endpoint does not resume stored evidence graph');
$has($ui,"event.type==='paywall'",'workspace does not consume paid handoff event');
$has($ui,"checkout.action='/truth/paypal-start.php'",'workspace checkout does not use server-side PayPal start');
$has($ui,'verifies the completed payment server-side','workspace does not disclose server-side payment verification');

echo "Paid investigation funnel contract passed.\n";
