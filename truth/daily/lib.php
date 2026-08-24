<?php
declare(strict_types=1);

function tw_private_dir(): string {
    $home = dirname(__DIR__, 3);
    $dir = $home . '/site-private/trust-worthy';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('Unable to create Trust-Worthy private storage.');
    }
    return $dir;
}

function tw_json_read(string $path, array $fallback = []): array {
    if (!is_file($path)) return $fallback;
    $raw = file_get_contents($path);
    if ($raw === false) return $fallback;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $fallback;
}

function tw_json_write(string $path, array $data): void {
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Unable to write ' . basename($path));
    }
    @chmod($path, 0640);
}

function tw_admin_token(): string {
    $path = tw_private_dir() . '/daily-admin-token.txt';
    if (!is_file($path)) {
        $token = bin2hex(random_bytes(24));
        if (file_put_contents($path, $token, LOCK_EX) === false) {
            throw new RuntimeException('Unable to create daily admin token.');
        }
        @chmod($path, 0640);
        return $token;
    }
    return trim((string)file_get_contents($path));
}

function tw_require_admin(): string {
    $provided = (string)($_REQUEST['key'] ?? '');
    $expected = tw_admin_token();
    if ($provided === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Private Trust-Worthy desk.\nRun the CLI admin-url helper on the server to obtain the private URL.\n";
        exit;
    }
    return $expected;
}

function tw_fetch_feed(string $url): string {
    $ch = curl_init($url);
    if ($ch === false) throw new RuntimeException('Unable to initialize feed request.');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'Trust-Worthy-Daily-Desk/0.1 (+https://bobsome1.com/truth/)',
        CURLOPT_HTTPHEADER => ['Accept: application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9, */*;q=0.1'],
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $status < 200 || $status >= 400) {
        throw new RuntimeException('HTTP ' . $status . ($error !== '' ? ' - ' . $error : ''));
    }
    if (strlen($body) > 3_000_000) throw new RuntimeException('Feed exceeded size limit.');
    return $body;
}

function tw_parse_feed(string $xml, string $source, int $limit): array {
    $prev = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if ($doc === false) throw new RuntimeException('Feed XML could not be parsed.');

    $items = [];
    if (isset($doc->channel->item)) {
        foreach ($doc->channel->item as $item) {
            $title = tw_clean_text((string)$item->title, 500);
            $link = trim((string)$item->link);
            $dateRaw = trim((string)($item->pubDate ?? ''));
            $summary = tw_clean_text((string)($item->description ?? ''), 1000);
            if ($title === '' || !filter_var($link, FILTER_VALIDATE_URL)) continue;
            $items[] = tw_feed_item($source, $title, $link, $dateRaw, $summary);
            if (count($items) >= $limit) break;
        }
    } else {
        foreach ($doc->entry ?? [] as $entry) {
            $title = tw_clean_text((string)$entry->title, 500);
            $link = '';
            foreach ($entry->link as $lnk) {
                $attrs = $lnk->attributes();
                $href = trim((string)($attrs['href'] ?? ''));
                $rel = trim((string)($attrs['rel'] ?? 'alternate'));
                if ($href !== '' && ($rel === '' || $rel === 'alternate')) { $link = $href; break; }
            }
            $dateRaw = trim((string)($entry->published ?? $entry->updated ?? ''));
            $summary = tw_clean_text((string)($entry->summary ?? $entry->content ?? ''), 1000);
            if ($title === '' || !filter_var($link, FILTER_VALIDATE_URL)) continue;
            $items[] = tw_feed_item($source, $title, $link, $dateRaw, $summary);
            if (count($items) >= $limit) break;
        }
    }
    return $items;
}

function tw_feed_item(string $source, string $title, string $link, string $dateRaw, string $summary): array {
    $ts = $dateRaw !== '' ? strtotime($dateRaw) : false;
    if ($ts === false) $ts = time();
    return [
        'id' => substr(hash('sha256', $source . '|' . $link . '|' . $title), 0, 20),
        'source' => $source,
        'title' => $title,
        'url' => $link,
        'published_at_utc' => gmdate('c', $ts),
        'timestamp' => $ts,
        'summary' => $summary,
    ];
}

function tw_clean_text(string $text, int $limit): string {
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    return mb_substr($text, 0, $limit);
}

function tw_title_tokens(string $title): array {
    $title = mb_strtolower($title);
    $title = preg_replace('/[^\pL\pN\s]+/u', ' ', $title) ?? $title;
    $parts = preg_split('/\s+/u', trim($title)) ?: [];
    $stop = array_flip(['a','an','and','are','as','at','be','but','by','for','from','has','have','he','her','his','how','in','is','it','its','of','on','or','our','she','that','the','their','they','this','to','was','we','what','when','where','who','why','will','with','you']);
    $out = [];
    foreach ($parts as $p) {
        if (mb_strlen($p) < 3 || isset($stop[$p])) continue;
        $out[$p] = true;
    }
    return array_keys($out);
}

function tw_similarity(string $a, string $b): float {
    $ta = tw_title_tokens($a); $tb = tw_title_tokens($b);
    if ($ta === [] || $tb === []) return 0.0;
    $ia = array_intersect($ta, $tb);
    $union = array_unique(array_merge($ta, $tb));
    return count($union) ? count($ia) / count($union) : 0.0;
}

function tw_candidate_score(array $item, array $all, int $lookbackHours): array {
    $ageHours = max(0.0, (time() - (int)$item['timestamp']) / 3600);
    $recency = max(0.0, 20.0 * (1.0 - min($ageHours, $lookbackHours) / max(1, $lookbackHours)));

    $otherSources = [];
    foreach ($all as $other) {
        if ($other['id'] === $item['id'] || $other['source'] === $item['source']) continue;
        if (tw_similarity($item['title'], $other['title']) >= 0.34) $otherSources[$other['source']] = true;
    }
    $crossSource = min(30.0, count($otherSources) * 7.5);

    $importanceTerms = ['president','congress','senate','supreme','court','election','war','military','nuclear','economy','inflation','jobs','market','federal','government','health','disease','vaccine','ai','artificial','intelligence','climate','energy','crime','justice','police','religion','church','bible','scientist','study','report','investigation','claims','says','alleges','accuses','warning','ban','law','policy'];
    $lower = mb_strtolower($item['title']);
    $importance = 0.0;
    foreach ($importanceTerms as $term) if (str_contains($lower, $term)) $importance += 2.0;
    $importance = min(22.0, $importance);

    $questionability = 0.0;
    $signalTerms = ['claims','claim','says','said','alleges','alleged','accuses','denies','report','study','officials','sources','could','may','might','warning','fact','truth','proof','evidence','secret','hidden','hoax','conspiracy','misinformation','propaganda'];
    foreach ($signalTerms as $term) if (str_contains($lower, $term)) $questionability += 2.5;
    $questionability = min(18.0, $questionability);

    $score = round($recency + $crossSource + $importance + $questionability, 1);
    $reasons = [];
    if ($crossSource >= 7.5) $reasons[] = 'covered by multiple independent outlets';
    if ($questionability >= 5) $reasons[] = 'contains a testable or contested claim';
    if ($importance >= 6) $reasons[] = 'high public-impact subject';
    if ($recency >= 12) $reasons[] = 'fresh';
    if ($reasons === []) $reasons[] = 'recent public claim';

    return [$score, array_keys($otherSources), implode('; ', $reasons)];
}

function tw_rank_candidates(array $items, int $lookbackHours, int $max): array {
    $cutoff = time() - ($lookbackHours * 3600);
    $filtered = [];
    $seenUrl = []; $seenTitle = [];
    foreach ($items as $item) {
        if ((int)$item['timestamp'] < $cutoff) continue;
        $urlKey = preg_replace('/[#?].*$/', '', $item['url']) ?? $item['url'];
        $titleKey = mb_strtolower(trim($item['title']));
        if (isset($seenUrl[$urlKey]) || isset($seenTitle[$titleKey])) continue;
        $seenUrl[$urlKey] = true; $seenTitle[$titleKey] = true;
        $filtered[] = $item;
    }
    foreach ($filtered as &$item) {
        [$score, $corroborating, $reason] = tw_candidate_score($item, $filtered, $lookbackHours);
        $item['score'] = $score;
        $item['corroborating_sources'] = $corroborating;
        $item['why_trial_worthy'] = $reason;
    }
    unset($item);
    usort($filtered, fn(array $a, array $b): int => ($b['score'] <=> $a['score']) ?: ($b['timestamp'] <=> $a['timestamp']));
    return array_slice($filtered, 0, $max);
}

function tw_openai_key(): string {
    return (string)(getenv('TRUST_WORTHY_OPENAI_API_KEY') ?: getenv('OPENAI_API_KEY') ?: getenv('INSIDE_OF_ME_OPENAI_API_KEY') ?: '');
}

function tw_extract_response_text(array $node): ?string {
    if (isset($node['output_text']) && is_string($node['output_text']) && trim($node['output_text']) !== '') return $node['output_text'];
    if (isset($node['text']) && is_string($node['text']) && trim($node['text']) !== '') return $node['text'];
    foreach ($node as $value) {
        if (is_array($value)) {
            $found = tw_extract_response_text($value);
            if ($found !== null) return $found;
        }
    }
    return null;
}

function tw_trim_json_fence(string $text): string {
    $text = trim($text);
    $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
    $text = preg_replace('/\s*```$/', '', $text) ?? $text;
    return trim($text);
}

function tw_run_investigation(array $candidate, string $model): array {
    $key = tw_openai_key();
    if ($key === '') throw new RuntimeException('No OpenAI key is configured for Trust-Worthy.');

    $system = <<<'PROMPT'
You are the research engine for Trust-Worthy AI. Motto: QUESTION EVERYTHING! Final invitation: YOU BE THE JUDGE.
Investigate from first principles. Do not treat a fact-checker, institution, outsider, government, corporation, religious authority, political faction, or media outlet as automatically authoritative or automatically deceptive. Trace claims to primary or earliest accessible evidence when possible. Question provenance, chronology, context, funding, incentives, beneficiaries, omissions, contradictions, and supposedly independent corroboration. Actively seek the strongest counterevidence and strongest reasonable alternative explanation.
No hard proof does not mean nothing reasonable can be inferred. You may identify a conclusion as strongly indicated or a reasonable inference when the total pattern supports it, but never relabel inference as proven fact. Motive is context, not proof by itself.
A Bobinated Opinion is explicitly opinion: state what appears most likely, show the logic, acknowledge material evidence cutting the other way, and name important unknowns.
Do not invent sources, quotations, documents, motives, people, events, or evidence. If a source cannot be verified, say so. For serious allegations about identifiable people, report the record precisely and distinguish allegation, evidence, inference, and opinion.
Return valid JSON only. No Markdown fences. Use this exact top-level schema:
{
  "headline": string,
  "claim": string,
  "summary": string,
  "proven": [string],
  "strongly_indicated": [string],
  "contradictions_missing_pieces": [string],
  "motives_incentives_who_benefits": [string],
  "logic_common_sense": [string],
  "counterevidence_alternatives": [string],
  "unknowns": [string],
  "bobinated_opinion": {"opinion": string, "reasoning": [string]},
  "sources": [{"title": string, "url": string, "role": string}],
  "what_would_change_the_finding": [string],
  "confidence": "high"|"medium"|"low"
}
PROMPT;

    $candidateJson = json_encode([
        'source' => $candidate['source'] ?? '',
        'headline' => $candidate['title'] ?? '',
        'url' => $candidate['url'] ?? '',
        'published_at_utc' => $candidate['published_at_utc'] ?? '',
        'summary' => $candidate['summary'] ?? '',
        'corroborating_sources' => $candidate['corroborating_sources'] ?? [],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $payload = [
        'model' => $model,
        'tools' => [['type' => 'web_search']],
        'input' => [
            ['role' => 'system', 'content' => [['type' => 'input_text', 'text' => $system]]],
            ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => "Investigate this Daily Truth Trial candidate. Search beyond the originating story. Verify the underlying factual propositions and retrieve primary evidence where possible.\n\nCANDIDATE:\n" . $candidateJson]]],
        ],
        'max_output_tokens' => 4200,
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    if ($ch === false) throw new RuntimeException('Unable to initialize research request.');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $status < 200 || $status >= 300) {
        throw new RuntimeException('Research provider failed (HTTP ' . $status . ')' . ($error !== '' ? ': ' . $error : '.'));
    }
    $response = json_decode($body, true);
    if (!is_array($response)) throw new RuntimeException('Research provider returned unreadable JSON.');
    $text = tw_extract_response_text($response);
    if ($text === null) throw new RuntimeException('Research provider returned no readable report.');
    $report = json_decode(tw_trim_json_fence($text), true);
    if (!is_array($report)) throw new RuntimeException('Research report was not valid JSON.');

    $report['_meta'] = [
        'candidate' => $candidate,
        'model' => $model,
        'generated_at_utc' => gmdate('c'),
        'status' => 'human-review-required',
    ];
    return $report;
}

function tw_safe_url(string $url): string {
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '#';
}
