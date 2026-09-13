<?php
declare(strict_types=1);
require __DIR__ . '/lib.php';

tw_admin_private_headers();
tw_require_same_origin_form_post(524288);
tw_require_admin();
tw_require_admin_csrf();

function field(string $name, int $limit = 12000): string {
    $value = tw_post_scalar($name, $limit);
    if ($value === null) publish_error(422, 'Invalid or oversized field: ' . $name . '.');
    return mb_substr(trim($value), 0, $limit);
}

function lines(string $name): array {
    $parts = preg_split('/\r?\n/u', field($name)) ?: [];
    $out = [];
    foreach ($parts as $part) {
        $part = tw_clean_text($part, 3000);
        if ($part !== '') $out[] = $part;
        if (count($out) >= 30) break;
    }
    return $out;
}

function publish_error(int $status, string $message): never {
    http_response_code($status);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message . "\n";
    exit;
}

$draftIdValue = tw_post_scalar('draft_id', 32);
$digestValue = tw_post_scalar('draft_digest', 64);
$draftId = $draftIdValue === null ? '' : trim($draftIdValue);
$submittedDigest = $digestValue === null ? '' : trim($digestValue);
if (!preg_match('/^[a-f0-9]{32}$/', $draftId) || !preg_match('/^[a-f0-9]{64}$/', $submittedDigest)) {
    publish_error(422, 'The bound research draft identifier is invalid. Reload the private desk.');
}

$headline = field('headline', 500);
$claim = field('claim', 2000);
$summary = field('summary', 4000);
$bobinatedOpinion = field('bobinated_opinion', 5000);
$requiredLists = [
    'contradictions_missing_pieces' => lines('contradictions_missing_pieces'),
    'counterevidence_alternatives' => lines('counterevidence_alternatives'),
    'unknowns' => lines('unknowns'),
    'what_would_change_the_finding' => lines('what_would_change_the_finding'),
];
if ($headline === '' || $claim === '' || $summary === '' || $bobinatedOpinion === '') {
    publish_error(422, 'Headline, claim, summary, and Bobinated Opinion are required.');
}
foreach ($requiredLists as $name => $items) {
    if ($items === []) publish_error(422, 'Complete every evidence-boundary section before publication: ' . $name . '.');
}

try {
    $publication = tw_with_private_lock('daily-publish', static function () use (
        $draftId,
        $submittedDigest,
        $headline,
        $claim,
        $summary,
        $bobinatedOpinion,
        $requiredLists
    ): array {
        $recovered = tw_recover_daily_publication_transaction();
        if (is_array($recovered)
            && ($recovered['draft_id'] ?? '') === $draftId
            && ($recovered['original_draft_digest'] ?? '') === $submittedDigest
            && is_array($recovered['result'] ?? null)) {
            return $recovered['result'];
        }

        $draft = tw_load_daily_draft($draftId);
        if (($draft['_meta']['publication_status'] ?? '') !== 'private-draft') {
            throw new DomainException('This research draft has already been published or is not publishable.');
        }
        if (!hash_equals(tw_daily_draft_digest($draft), $submittedDigest)) {
            throw new DomainException('The research draft changed after this editor form was opened. Reload and review it again.');
        }
        if (tw_daily_draft_is_private_question($draft)) {
            throw new DomainException('Private user questions and their research cannot be published by the Daily Desk.');
        }

        $providerEvidence = [];
        foreach (($draft['_meta']['provider_sources'] ?? []) as $providerSource) {
            if (!is_array($providerSource) || !is_string($providerSource['url'] ?? null)) continue;
            $key = tw_provider_url_key($providerSource['url']);
            if ($key !== null) $providerEvidence[$key] = true;
        }
        if (count($providerEvidence) < 2) {
            throw new DomainException('The bound draft does not contain enough provider evidence for publication.');
        }

        $sources = [];
        $seen = [];
        foreach (($draft['sources'] ?? []) as $source) {
            if (!is_array($source)) continue;
            $url = is_string($source['url'] ?? null) ? trim($source['url']) : '';
            $urlKey = tw_provider_url_key($url);
            if ($urlKey === null || !isset($providerEvidence[$urlKey])) {
                throw new DomainException('A draft source is not bound to provider evidence.');
            }
            if (isset($seen[$urlKey])) continue;
            $title = is_string($source['title'] ?? null) ? tw_clean_text($source['title'], 500) : '';
            $role = is_string($source['role'] ?? null) ? tw_clean_text($source['role'], 1000) : '';
            if ($title === '' || $role === '') continue;
            $seen[$urlKey] = true;
            $sources[] = ['title' => $title, 'url' => $url, 'role' => $role];
        }
        if (count($sources) < 2) throw new DomainException('At least two usable sources are required before publication.');

        $confidence = strtolower(field('confidence', 20));
        if (!in_array($confidence, ['high', 'medium', 'low'], true)) $confidence = 'low';
        $publicationId = bin2hex(random_bytes(12));
        $publishedAt = gmdate('c');
        $published = [
            'headline' => $headline,
            'claim' => $claim,
            'summary' => $summary,
            'proven' => lines('proven'),
            'strongly_indicated' => lines('strongly_indicated'),
            'contradictions_missing_pieces' => $requiredLists['contradictions_missing_pieces'],
            'motives_incentives_who_benefits' => lines('motives_incentives_who_benefits'),
            'logic_common_sense' => lines('logic_common_sense'),
            'counterevidence_alternatives' => $requiredLists['counterevidence_alternatives'],
            'unknowns' => $requiredLists['unknowns'],
            'bobinated_opinion' => [
                'opinion' => $bobinatedOpinion,
                'reasoning' => lines('bobinated_reasoning'),
            ],
            'what_would_change_the_finding' => $requiredLists['what_would_change_the_finding'],
            'confidence' => $confidence,
            'sources' => $sources,
            // Public metadata is deliberately allow-listed. Candidate details, private
            // question context, provider IDs, usage, model names, and draft IDs stay private.
            '_meta' => [
                'publication_id' => $publicationId,
                'published_at_utc' => $publishedAt,
                'reviewed_by_human' => true,
            ],
        ];

        $archiveName = 'trial-' . gmdate('Ymd-His') . '-' . $publicationId . '.json';
        $transaction = [
            'version' => 1,
            'created_at_utc' => gmdate('c'),
            'draft_id' => $draftId,
            'original_draft_digest' => $submittedDigest,
            'publication_id' => $publicationId,
            'archive_name' => $archiveName,
            'published' => $published,
        ];
        tw_json_write(tw_private_dir() . '/daily-publish-transaction.json', $transaction);
        $completed = tw_recover_daily_publication_transaction();
        if (!is_array($completed['result'] ?? null)) {
            throw new RuntimeException('Publication transaction did not complete.');
        }
        return $completed['result'];
    });
} catch (DomainException $e) {
    publish_error(409, $e->getMessage());
} catch (Throwable) {
    try {
        tw_json_write(tw_private_dir() . '/daily-safe-error.json', [
            'at_utc' => gmdate('c'),
            'category' => 'daily_publication',
            'code' => 'TW_DAILY_PUBLICATION_FAILED',
            'message' => 'Publication stopped safely; the private recovery journal remains authoritative.',
        ]);
    } catch (Throwable $ignored) {
    }
    publish_error(500, 'Publication stopped safely. A complete public file may already be live; the private journal will replay the same transaction on the next publish attempt.');
}

header('Location: /truth/today.php?published=1', true, 303);
exit;
