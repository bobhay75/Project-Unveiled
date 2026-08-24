<?php
declare(strict_types=1);

function tw_preview_fallback(string $question, string $topic): array {
    $topicLabel = match ($topic) {
        'news' => 'a current public claim',
        'propaganda' => 'a narrative or persuasion claim',
        'jesus' => 'a claim about Jesus or the Gospel record',
        'doctrine' => 'a theological claim',
        'history' => 'a historical claim',
        'science' => 'a scientific claim',
        'current-events' => 'a current-events claim',
        default => 'a factual claim',
    };
    return [
        'core_claim' => $question,
        'why_it_matters' => 'This question can be tested as ' . $topicLabel . ' by separating what is asserted from what the underlying evidence can actually establish.',
        'pressure_points' => [
            'Where did the claim originate, and what is the earliest accessible evidence behind it?',
            'What is the strongest evidence supporting it — and the strongest evidence cutting against it?',
            'What assumptions, incentives, omissions, or competing explanations could change the conclusion?'
        ],
        'what_would_change_answer' => 'A primary source, independently corroborated evidence, or a contradiction that materially changes the factual premise would move the finding.',
        'hook' => 'The interesting part is not merely whether the claim sounds plausible. It is whether it survives when the source chain, counterevidence, and logic are put on the same table.',
        'generated_by' => 'fallback'
    ];
}

function tw_preview_extract_text(mixed $node): ?string {
    if (is_array($node)) {
        if (isset($node['output_text']) && is_string($node['output_text']) && trim($node['output_text']) !== '') return $node['output_text'];
        if (isset($node['text']) && is_string($node['text']) && trim($node['text']) !== '') return $node['text'];
        foreach ($node as $value) {
            $found = tw_preview_extract_text($value);
            if ($found !== null) return $found;
        }
    }
    return null;
}

function tw_preview_trim_fence(string $text): string {
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
    $text = preg_replace('/\s*```$/', '', $text) ?? $text;
    return trim($text);
}

function tw_generate_question_preview(string $question, string $context, string $topic): array {
    $fallback = tw_preview_fallback($question, $topic);
    $key = (string)(getenv('TRUST_WORTHY_OPENAI_API_KEY') ?: getenv('OPENAI_API_KEY') ?: getenv('INSIDE_OF_ME_OPENAI_API_KEY') ?: '');
    if ($key === '' || !function_exists('curl_init')) return $fallback;

    $model = (string)(getenv('TRUST_WORTHY_PREVIEW_MODEL') ?: getenv('TRUST_WORTHY_OPENAI_MODEL') ?: 'gpt-5.6-luna');
    $system = <<<'PROMPT'
You create the free Question X-Ray for Trust-Worthy AI.
This is NOT the investigation and NOT a verdict. Do not browse the web. Do not invent facts, sources, motives, evidence, history, or conclusions.
Your job is to give the user immediate intellectual value from their own question while creating curiosity about the evidence-based Deep Dive.
Return JSON only with this exact schema:
{
  "core_claim": string,
  "why_it_matters": string,
  "pressure_points": [string, string, string],
  "what_would_change_answer": string,
  "hook": string
}
Rules:
- Reframe the question into the clearest testable claim without changing its meaning.
- Pressure points should identify what must be investigated, not pretend it already has been.
- The hook should be intriguing but never sensationalize or imply hidden wrongdoing without evidence.
- Keep the total response concise, useful, and readable.
PROMPT;

    $user = json_encode(['topic'=>$topic,'question'=>$question,'context'=>$context], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $payload = [
        'model' => $model,
        'input' => [
            ['role'=>'system','content'=>[['type'=>'input_text','text'=>$system]]],
            ['role'=>'user','content'=>[['type'=>'input_text','text'=>(string)$user]]]
        ],
        'max_output_tokens' => 450
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    if ($ch === false) return $fallback;
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $status < 200 || $status >= 300) return $fallback;

    $decoded = json_decode((string)$body, true);
    if (!is_array($decoded)) return $fallback;
    $text = tw_preview_extract_text($decoded);
    if ($text === null) return $fallback;
    $preview = json_decode(tw_preview_trim_fence($text), true);
    if (!is_array($preview)) return $fallback;

    $points = array_values(array_filter(array_map('strval', (array)($preview['pressure_points'] ?? []))));
    if (count($points) !== 3) return $fallback;
    foreach (['core_claim','why_it_matters','what_would_change_answer','hook'] as $required) {
        if (!isset($preview[$required]) || !is_string($preview[$required]) || trim($preview[$required]) === '') return $fallback;
    }
    $preview['pressure_points'] = $points;
    $preview['generated_by'] = 'ai';
    return $preview;
}
