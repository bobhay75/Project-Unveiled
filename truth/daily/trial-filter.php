<?php
declare(strict_types=1);

/**
 * Re-rank generic news candidates for Trust-Worthy.
 * Mainstream feeds are raw evidence intake; this layer decides what is actually trial-worthy.
 */
function tw_trial_focus(array $candidates, int $max = 18): array {
    $lanes = [
        'Contradiction / official claim' => [
            'contradict','conflict','dispute','deny','denies','denied','allege','alleges','alleged','accuse','accuses','claim','claims','official','statement','testimony','records','documents','report','investigation','evidence','proof','source','sources'
        ],
        'Propaganda / framing' => [
            'propaganda','misinformation','disinformation','hoax','narrative','viral','censored','censorship','ban','banned','warning','fear','campaign','advertising','influence','manipulat','deepfake','fake','leak','leaked'
        ],
        'Power / money / incentives' => [
            'money','funding','funded','donor','lobby','contract','profit','profits','billion','million','market','corporate','company','government','federal','agency','military','intelligence','election','congress','senate','court','president','policy','law'
        ],
        'Science / evidence dispute' => [
            'study','scientist','science','research','data','health','disease','vaccine','climate','space','nasa','archaeolog','history','historical','discovery','evidence','theory','experiment','ai','artificial intelligence'
        ],
        'Religion / history / meaning' => [
            'jesus','christ','bible','biblical','church','religion','religious','gospel','god','prophecy','ancient','archaeolog','history','historical','manuscript','scripture'
        ],
        'Unusual / hidden / unexplained' => [
            'secret','hidden','mystery','mysterious','unknown','unexplained','revealed','discovered','discovery','inside','behind','what really','why','how','could','may','might'
        ],
    ];

    $routine = [
        'sports','score','season','coach','player','celebrity','red carpet','box office','recipe','travel tips','weather forecast','lottery','horoscope','fashion','shopping','sale','deal'
    ];

    foreach ($candidates as &$candidate) {
        $haystack = mb_strtolower((string)($candidate['title'] ?? '') . ' ' . (string)($candidate['summary'] ?? ''));
        $bestLane = 'General public claim';
        $best = 0;
        foreach ($lanes as $lane => $terms) {
            $laneScore = 0;
            foreach ($terms as $term) {
                if (str_contains($haystack, $term)) $laneScore += 3;
            }
            if ($laneScore > $best) { $best = $laneScore; $bestLane = $lane; }
        }
        $routinePenalty = 0;
        foreach ($routine as $term) if (str_contains($haystack, $term)) $routinePenalty += 18;

        $corroboration = count((array)($candidate['corroborating_sources'] ?? []));
        $tension = $best + min(18, $corroboration * 4);
        $candidate['trial_lane'] = $bestLane;
        $candidate['tension_score'] = $tension;
        $candidate['score'] = round((float)($candidate['score'] ?? 0) + ($tension * 1.8) - $routinePenalty, 1);

        $reasons = [];
        if ($best > 0) $reasons[] = $bestLane;
        if ($corroboration > 0) $reasons[] = 'multiple-source pressure';
        if ($routinePenalty > 0) $reasons[] = 'routine-news penalty applied';
        $candidate['why_trial_worthy'] = $reasons ? implode('; ', $reasons) : (string)($candidate['why_trial_worthy'] ?? 'public claim');
    }
    unset($candidate);

    usort($candidates, fn(array $a, array $b): int => ($b['score'] <=> $a['score']) ?: ($b['timestamp'] <=> $a['timestamp']));

    // Prefer candidates with genuine tension. Fill remaining slots only if needed.
    $focused = array_values(array_filter($candidates, fn(array $c): bool => (int)($c['tension_score'] ?? 0) >= 6));
    $selected = array_slice($focused, 0, $max);
    if (count($selected) < $max) {
        $seen = array_fill_keys(array_map(fn(array $c): string => (string)$c['id'], $selected), true);
        foreach ($candidates as $candidate) {
            if (isset($seen[(string)$candidate['id']])) continue;
            $selected[] = $candidate;
            if (count($selected) >= $max) break;
        }
    }
    return $selected;
}
