<?php
declare(strict_types=1);

/**
 * Trust-Worthy Story Scout V1
 *
 * Cheap, deterministic promotion gate for externally collected news/claim
 * candidates. This file does not fetch the web, call a model, publish a story,
 * or issue a verdict. It only normalizes, deduplicates, scores, and ranks
 * candidate claims before an editor chooses whether to promote one into the
 * existing Guided Deep Investigation.
 */

function tw_story_scout_clamp_score(mixed $value): int {
    if (!is_int($value) && !is_float($value) && !is_numeric($value)) return 0;
    return max(0, min(100, (int)round((float)$value)));
}

function tw_story_scout_normalize_claim(string $claim): string {
    $claim = trim(preg_replace('/\s+/u', ' ', $claim) ?? '');
    if ($claim === '' || strlen($claim) > 2000) return '';
    return $claim;
}

function tw_story_scout_fingerprint(string $claim): string {
    $normalized = tw_story_scout_normalize_claim($claim);
    if ($normalized === '') return '';
    $folded = function_exists('mb_strtolower') ? mb_strtolower($normalized, 'UTF-8') : strtolower($normalized);
    $folded = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $folded) ?? $folded;
    return hash('sha256', trim(preg_replace('/\s+/u', ' ', $folded) ?? $folded));
}

function tw_story_scout_domain_allowed(string $domain): bool {
    return in_array($domain, [
        'elections', 'politics', 'science', 'finance', 'health',
        'technology', 'conflict', 'geopolitics', 'public-safety', 'other'
    ], true);
}

function tw_story_scout_score(array $candidate): array {
    $claim = tw_story_scout_normalize_claim((string)($candidate['claim'] ?? ''));
    $domain = strtolower(trim((string)($candidate['domain'] ?? 'other')));
    $publishedAt = trim((string)($candidate['published_at_utc'] ?? ''));
    $sourceUrl = trim((string)($candidate['source_url'] ?? ''));

    $eligible = $claim !== ''
        && tw_story_scout_domain_allowed($domain)
        && $sourceUrl !== ''
        && filter_var($sourceUrl, FILTER_VALIDATE_URL) !== false
        && strtolower((string)parse_url($sourceUrl, PHP_URL_SCHEME)) === 'https';

    $dimensions = [
        'public_importance' => tw_story_scout_clamp_score($candidate['public_importance'] ?? 0),
        'factual_testability' => tw_story_scout_clamp_score($candidate['factual_testability'] ?? 0),
        'evidence_availability' => tw_story_scout_clamp_score($candidate['evidence_availability'] ?? 0),
        'novelty' => tw_story_scout_clamp_score($candidate['novelty'] ?? 0),
        'potential_harm' => tw_story_scout_clamp_score($candidate['potential_harm'] ?? 0),
        'virality' => tw_story_scout_clamp_score($candidate['virality'] ?? 0),
    ];

    // Virality is deliberately the smallest weight. A claim cannot rank highly
    // merely because it is popular; testability and public consequence lead.
    $weighted = (
        $dimensions['public_importance'] * 0.25
        + $dimensions['factual_testability'] * 0.25
        + $dimensions['evidence_availability'] * 0.20
        + $dimensions['novelty'] * 0.15
        + $dimensions['potential_harm'] * 0.10
        + $dimensions['virality'] * 0.05
    );

    // Fail closed when the proposed investigation is not presently testable.
    if ($dimensions['factual_testability'] < 50 || $dimensions['evidence_availability'] < 35) {
        $eligible = false;
    }

    return [
        'claim' => $claim,
        'fingerprint' => tw_story_scout_fingerprint($claim),
        'domain' => $domain,
        'source_url' => $sourceUrl,
        'published_at_utc' => $publishedAt,
        'dimensions' => $dimensions,
        'priority_score' => $eligible ? (int)round($weighted) : 0,
        'eligible_for_editor_review' => $eligible,
        'status' => $eligible ? 'CANDIDATE — NOT INVESTIGATED' : 'REJECTED — INSUFFICIENT SCOUT EVIDENCE',
        'truth_status' => 'UNRESOLVED',
        'publication_status' => 'HOLD',
    ];
}

function tw_story_scout_rank(array $candidates, int $limit = 12): array {
    $limit = max(1, min(50, $limit));
    $bestByFingerprint = [];

    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) continue;
        $scored = tw_story_scout_score($candidate);
        $fingerprint = $scored['fingerprint'];
        if ($fingerprint === '') continue;
        if (!isset($bestByFingerprint[$fingerprint])
            || $scored['priority_score'] > $bestByFingerprint[$fingerprint]['priority_score']) {
            $bestByFingerprint[$fingerprint] = $scored;
        }
    }

    $ranked = array_values($bestByFingerprint);
    usort($ranked, static function(array $a, array $b): int {
        return ($b['priority_score'] <=> $a['priority_score'])
            ?: strcmp($a['fingerprint'], $b['fingerprint']);
    });

    return array_slice($ranked, 0, $limit);
}
