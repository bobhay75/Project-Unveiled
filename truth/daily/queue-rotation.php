<?php
declare(strict_types=1);

function tw_library_candidates(array $rows, string $prefix, string $source, int $baseScore, string $type): array {
    $out = [];
    foreach ($rows as $i => $row) {
        if (!is_array($row) || count($row) < 2) continue;
        $lane = trim((string)$row[0]);
        $question = trim((string)$row[1]);
        if ($question === '') continue;
        $out[] = [
            'id' => $prefix . '-' . substr(hash('sha256', $lane . '|' . $question), 0, 16),
            'source' => $source,
            'title' => $question,
            'url' => '',
            'published_at_utc' => gmdate('c'),
            'timestamp' => time() - $i,
            'summary' => 'Evergreen need-to-know question selected for evidence-based investigation.',
            'score' => $baseScore - ($i * 0.01),
            'trial_lane' => $lane,
            'tension_score' => 50,
            'corroborating_sources' => [],
            'why_trial_worthy' => 'Need-to-know: investigate the source trail, context, counterevidence, and historical development.',
            'candidate_type' => $type,
        ];
    }
    return $out;
}

function tw_rotate_varied_candidates(array $candidates, int $max): array {
    if ($max <= 0 || $candidates === []) return [];

    $byLane = [];
    foreach ($candidates as $candidate) {
        $lane = trim((string)($candidate['trial_lane'] ?? 'Other')) ?: 'Other';
        $byLane[$lane][] = $candidate;
    }

    $lanes = array_keys($byLane);
    sort($lanes, SORT_NATURAL | SORT_FLAG_CASE);
    if ($lanes === []) return [];

    // Stable daily rotation: the desk changes each UTC day without random churn on refresh.
    $daySeed = (int)sprintf('%u', crc32(gmdate('Y-m-d')));
    $laneOffset = $daySeed % count($lanes);
    $lanes = array_merge(array_slice($lanes, $laneOffset), array_slice($lanes, 0, $laneOffset));

    foreach ($byLane as $lane => &$rows) {
        $count = count($rows);
        if ($count < 2) continue;
        $offset = (int)sprintf('%u', crc32(gmdate('Y-m-d') . '|' . $lane)) % $count;
        $rows = array_merge(array_slice($rows, $offset), array_slice($rows, 0, $offset));
    }
    unset($rows);

    $picked = [];
    $round = 0;
    while (count($picked) < $max) {
        $added = false;
        foreach ($lanes as $lane) {
            if (!isset($byLane[$lane][$round])) continue;
            $picked[] = $byLane[$lane][$round];
            $added = true;
            if (count($picked) >= $max) break 2;
        }
        if (!$added) break;
        $round++;
    }
    return $picked;
}

function tw_build_full_meat_queue(string $privateDir, int $max = 18): array {
    $user = tw_user_question_candidates($privateDir, 6);

    $coreRows = [];
    foreach (tw_meat_desk_candidates() as $candidate) {
        // Preserve existing hand-built candidates, but let the variety selector rotate them.
        $coreRows[] = $candidate;
    }
    $traditions = tw_library_candidates(
        tw_traditions_of_men_questions(),
        'trad',
        'Traditions of Men Desk',
        175,
        'traditions-of-men'
    );

    $library = array_merge($traditions, $coreRows);
    $slots = max(0, $max - count($user));
    $meat = tw_rotate_varied_candidates($library, $slots);

    return array_slice(array_merge($user, $meat), 0, $max);
}
